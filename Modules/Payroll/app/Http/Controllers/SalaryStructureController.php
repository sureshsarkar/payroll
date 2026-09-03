<?php

namespace Modules\Payroll\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Payroll\app\Models\SalaryStructure;

/**
 * HR sets each employee's salary structure. A simple Basic + HRA% + Special
 * allowance form covers the common case; statutory deductions are auto-applied
 * by the engine, so they are not entered here.
 */
class SalaryStructureController extends Controller
{
    /** Team list with their current structure summary + a set/edit form. */
    public function index(Request $request): View
    {
        $team = $this->teamMembers($request->user());
        $current = SalaryStructure::with('components')
            ->whereIn('user_id', $team->pluck('id'))
            ->where('is_current', true)->get()->keyBy('user_id');

        return view('payroll::structures', compact('team', 'current'));
    }

    /** Create a new current structure (supersedes any previous one). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id'     => ['required', 'integer'],
            'basic'       => ['required', 'numeric', 'min:0'],
            'hra_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'special'     => ['required', 'numeric', 'min:0'],
        ]);

        // access guard — HR may only set structures for their own team
        if (! $this->teamMembers($request->user())->pluck('id')->contains((int) $data['user_id'])) {
            return back()->with('error', 'That employee is not in your team.');
        }

        $basic = (float) $data['basic'];
        $hra   = round($basic * (float) $data['hra_percent'] / 100, 2);
        $gross = round($basic + $hra + (float) $data['special'], 2);

        DB::transaction(function () use ($data, $basic, $gross, $request) {
            SalaryStructure::where('user_id', $data['user_id'])->update(['is_current' => false]);

            $structure = SalaryStructure::create([
                'user_id'        => $data['user_id'],
                'ctc_annual'     => $gross * 12,
                'gross_monthly'  => $gross,
                'effective_from' => now()->startOfMonth(),
                'is_current'     => true,
                'created_by'     => $request->user()->id,
            ]);

            $structure->components()->createMany([
                ['type' => 'earning', 'name' => 'Basic', 'code' => 'BASIC', 'calc_type' => 'fixed', 'value' => $basic, 'sort_order' => 1],
                ['type' => 'earning', 'name' => 'HRA', 'code' => 'HRA', 'calc_type' => 'percent_of_basic', 'value' => $data['hra_percent'], 'sort_order' => 2],
                ['type' => 'earning', 'name' => 'Special Allowance', 'code' => 'SPL', 'calc_type' => 'fixed', 'value' => $data['special'], 'sort_order' => 3],
            ]);
        });

        return back()->with('success', 'Salary structure saved.');
    }

    /**
     * Employees an HR manages in the active company. Delegates to
     * EmployeeProfile::teamUserIds() — see AttendanceController::teamMembers()
     * for why falling back to coach_id whenever the scoped list was empty
     * leaked cross-company employees into salary structures.
     */
    private function teamMembers(User $hr): Collection
    {
        return User::whereIn('id', EmployeeProfile::teamUserIds($hr))->orderBy('name')->get();
    }
}
