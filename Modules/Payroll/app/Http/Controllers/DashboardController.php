<?php

namespace Modules\Payroll\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Attendance\app\Models\Attendance;
use Modules\Attendance\app\Services\AttendanceService;
use Modules\HrEmployee\app\Models\Department;
use Modules\HrEmployee\app\Models\EmployeeProfile;
use Modules\Leave\app\Models\Leave;
use Modules\Leave\app\Models\LeaveBalance;
use Modules\Payroll\app\Models\PayrollItem;
use Modules\Payroll\app\Models\PayrollRun;

/**
 * Role landing dashboards for the payroll system (attendance %, payroll cost,
 * pending approvals). Non-destructive summary surfaces that sit alongside the
 * existing LMS dashboards.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    /** HR overview: team size, today's presence, pending approvals, month payroll. */
    public function hr(Request $request): View
    {
        $hr      = $request->user();
        $teamIds = EmployeeProfile::teamUserIds($hr);
        $today   = today()->toDateString();

        $presentToday = Attendance::whereIn('user_id', $teamIds)
            ->whereDate('attendance_date', $today)
            ->whereIn('status', [Attendance::PRESENT, Attendance::WFH])->count();

        $run = PayrollRun::where('year', now()->year)->where('month', now()->month)->first();

        return view('payroll::dashboard-hr', [
            'hr'             => $hr,
            'teamCount'      => $teamIds->count(),
            'presentToday'   => $presentToday,
            'onLeaveToday'   => Attendance::whereIn('user_id', $teamIds)->whereDate('attendance_date', $today)->where('status', Attendance::LEAVE)->count(),
            'pendingLeaves'  => Leave::whereIn('user_id', $teamIds)->where('status', Leave::PENDING)->count(),
            'attendancePct'  => $this->teamAttendancePct($teamIds),
            'monthRun'       => $run,
        ]);
    }

    /** Employee overview: month attendance, leave balance, latest payslip. */
    public function employee(Request $request): View
    {
        $user    = $request->user();
        $summary = $this->attendance->monthlySummary($user->id, now()->year, now()->month);

        $latest = PayrollItem::where('user_id', $user->id)
            ->whereHas('run', fn ($q) => $q->whereIn('status', [PayrollRun::ADMIN_APPROVED, PayrollRun::PAID]))
            ->with('run')->get()
            ->sortByDesc(fn ($i) => sprintf('%04d%02d', $i->run->year, $i->run->month))->first();

        return view('payroll::dashboard-employee', [
            'user'        => $user,
            'summary'     => $summary,
            'leaveLeft'   => (float) LeaveBalance::where('user_id', $user->id)->where('year', now()->year)->sum('allotted')
                             - (float) LeaveBalance::where('user_id', $user->id)->where('year', now()->year)->sum('used'),
            'latest'      => $latest,
            'today'       => Attendance::where('user_id', $user->id)->whereDate('attendance_date', today())->first(),
        ]);
    }

    /** Super Admin overview: company-wide headcount, pending approvals, cost. */
    public function admin(Request $request): View
    {
        $lastApproved = PayrollRun::where('status', PayrollRun::ADMIN_APPROVED)
            ->orderByDesc('year')->orderByDesc('month')->first();

        return view('payroll::dashboard-admin', [
            'employeeCount' => User::where('role', 'student')->count(),
            'hrCount'       => User::where('role', 'instructor')->count(),
            'deptCount'     => Department::count(),
            'pendingRuns'   => PayrollRun::where('status', PayrollRun::HR_SUBMITTED)->count(),
            'lastApproved'  => $lastApproved,
        ]);
    }

    /** Average payable/working ratio across a team for the current month. */
    private function teamAttendancePct(\Illuminate\Support\Collection $teamIds): int
    {
        if ($teamIds->isEmpty()) {
            return 0;
        }

        $ratios = [];
        foreach ($teamIds as $id) {
            $s = $this->attendance->monthlySummary($id, now()->year, now()->month);
            $ratios[] = $s['working_days'] > 0 ? ($s['payable_days'] / $s['working_days']) : 0;
        }

        return (int) round(array_sum($ratios) / count($ratios) * 100);
    }
}
