<?php

namespace Modules\HrEmployee\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\HrEmployee\app\Models\Department;
use Modules\HrEmployee\app\Models\EmployeeProfile;

/**
 * HR-side employee onboarding (role=instructor). HR sees the employees linked
 * to them and maintains each one's HR profile — department, reporting HR,
 * designation, employee code, DOJ and status. Setting reporting_hr_id is what
 * makes team-scoped attendance/leave/payroll resolve on real data instead of
 * the legacy coach_id fallback.
 */
class HrEmployeeController extends Controller
{
    /** List the HR's employees with their profiles + onboarding form. */
    public function index(Request $request): View
    {
        $hr        = $request->user();
        $employees = $this->linkedEmployees($hr);
        $profiles  = EmployeeProfile::whereIn('user_id', $employees->pluck('id'))
            ->get()->keyBy('user_id');

        return view('hremployee::employees', [
            'hr'          => $hr,
            'employees'   => $employees,
            'profiles'    => $profiles,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'statuses'    => [EmployeeProfile::ACTIVE, EmployeeProfile::ONBOARDING, EmployeeProfile::EXITED, EmployeeProfile::SUSPENDED],
        ]);
    }

    /** Create/update one employee's HR profile. */
    public function storeProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id'         => ['required', 'integer'],
            'employee_code'   => ['nullable', 'string', 'max:40'],
            'department_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'designation'     => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:30'],
            'date_of_joining' => ['nullable', 'date'],
            'status'          => ['required', 'string', 'max:20'],
        ]);

        $hr = $request->user();
        if (! $this->linkedEmployees($hr)->pluck('id')->contains((int) $data['user_id'])) {
            return back()->with('error', 'That employee is not linked to you.');
        }

        EmployeeProfile::updateOrCreate(
            ['user_id' => $data['user_id']],
            [
                'employee_code'   => $data['employee_code'] ?? null,
                'department_id'   => $data['department_id'] ?? null,
                'reporting_hr_id' => $hr->id,               // <-- claims the employee onto this HR's team
                'designation'     => $data['designation'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'date_of_joining' => $data['date_of_joining'] ?? null,
                'status'          => $data['status'],
            ],
        );

        return back()->with('success', 'Employee profile saved.');
    }

    /** List + create departments (company-wide). */
    public function departments(Request $request): View
    {
        return view('hremployee::departments', [
            'departments' => Department::withCount('employees')->orderBy('name')->get(),
            'hrs'         => User::where('role', 'instructor')->orderBy('name')->get(),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'code'         => ['nullable', 'string', 'max:40'],
            'head_user_id' => ['nullable', 'integer'],
        ]);

        Department::create($data + ['is_active' => true]);

        return back()->with('success', 'Department created.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Employees this HR can manage: anyone already reporting to them, plus
     * their legacy coach_id-linked students (so onboarding has a starting pool).
     */
    private function linkedEmployees(User $hr): Collection
    {
        $byProfile = EmployeeProfile::where('reporting_hr_id', $hr->id)->pluck('user_id');
        $byCoach   = User::where('role', 'student')->where('coach_id', $hr->id)->pluck('id');

        return User::whereIn('id', $byProfile->merge($byCoach)->unique())
            ->orderBy('name')->get();
    }
}
