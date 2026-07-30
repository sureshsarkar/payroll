<?php

namespace Modules\Attendance\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Attendance\app\Models\Attendance;
use Modules\Attendance\app\Services\AttendanceService;
use Modules\HrEmployee\app\Models\EmployeeProfile;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service)
    {
    }

    /* ---------------------------------------------------------------------
     |  EMPLOYEE (role = student)  — self service
     * -------------------------------------------------------------------*/

    /** Employee's own monthly attendance sheet + calendar. */
    public function myAttendance(Request $request): View
    {
        $user  = $request->user();
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return view('attendance::my', [
            'user'    => $user,
            'year'    => $year,
            'month'   => $month,
            'summary' => $this->service->monthlySummary($user->id, $year, $month),
            'map'     => $this->service->monthMap($user->id, $year, $month),
            'today'   => Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', today())->first(),
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $this->service->checkIn($request->user()->id);

        return back()->with('success', 'Checked in. Have a productive day!');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $this->service->checkOut($request->user()->id);

        return back()->with('success', 'Checked out. See you tomorrow!');
    }

    /* ---------------------------------------------------------------------
     |  HR (role = instructor)  — team management
     * -------------------------------------------------------------------*/

    /** HR view of their team with today's status + a bulk-mark form. */
    public function team(Request $request): View
    {
        $hr   = $request->user();
        $date = $request->input('date', today()->toDateString());
        $team = $this->teamMembers($hr);

        $marked = Attendance::whereIn('user_id', $team->pluck('id'))
            ->whereDate('attendance_date', $date)
            ->get()->keyBy('user_id');

        return view('attendance::team', [
            'hr'       => $hr,
            'date'     => $date,
            'team'     => $team,
            'marked'   => $marked,
            'statuses' => Attendance::STATUSES,
        ]);
    }

    /** HR bulk/single marks attendance for selected team members. */
    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date'       => ['required', 'date'],
            'status'     => ['required', 'string'],
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        if (! in_array($data['status'], Attendance::STATUSES, true)) {
            return back()->with('error', 'Invalid status.');
        }

        $hr      = $request->user();
        $allowed = $this->teamMembers($hr)->pluck('id')->all();
        $targets = array_values(array_intersect($data['user_ids'], $allowed)); // access guard

        if (empty($targets)) {
            return back()->with('error', 'None of the selected employees are in your team.');
        }

        $n = $this->service->bulkMark($targets, $data['date'], $data['status'], $hr->id);

        return back()->with('success', "Attendance marked for {$n} employee(s).");
    }

    /** HR team monthly sheet (per-employee summary rows). */
    public function teamSheet(Request $request): View
    {
        $hr    = $request->user();
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $team  = $this->teamMembers($hr);

        $rows = $team->map(fn (User $u) => [
            'user'    => $u,
            'summary' => $this->service->monthlySummary($u->id, $year, $month),
        ]);

        return view('attendance::team-sheet', compact('hr', 'year', 'month', 'rows'));
    }

    /* ------------------------------------------------------------------ */

    /**
     * Employees an HR manages. Primary link is employee_profiles.reporting_hr_id;
     * during the LMS->payroll transition we fall back to the legacy coach_id link
     * so the screen isn't empty before profiles are assigned.
     */
    private function teamMembers(User $hr): Collection
    {
        $ids = EmployeeProfile::where('reporting_hr_id', $hr->id)->pluck('user_id');

        if ($ids->isEmpty()) {
            $ids = User::where('role', 'student')->where('coach_id', $hr->id)->pluck('id');
        }

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }
}
