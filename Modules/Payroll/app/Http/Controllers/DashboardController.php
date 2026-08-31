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

    /**
     * HR landing dashboard — headline KPIs, a 14-day attendance trend, today's
     * status split, headcount by department, the 6-month payroll cost trend,
     * workforce composition and the two action lists (pending leave, new joiners).
     */
    public function hr(Request $request): View
    {
        $hr      = $request->user();
        $teamIds = EmployeeProfile::teamUserIds($hr);
        $today   = today();

        $profiles = EmployeeProfile::whereIn('user_id', $teamIds)
            ->with(['user:id,name,email', 'department:id,name'])
            ->get();

        $todayRows = Attendance::whereIn('user_id', $teamIds)
            ->whereDate('attendance_date', $today->toDateString())
            ->get();

        $marked        = $todayRows->count();
        $presentToday  = $todayRows->whereIn('status', [Attendance::PRESENT, Attendance::WFH])->count()
                         + $todayRows->where('status', Attendance::HALF_DAY)->count();
        $onLeaveToday  = $todayRows->where('status', Attendance::LEAVE)->count();
        $absentToday   = $todayRows->where('status', Attendance::ABSENT)->count();
        $teamCount     = $teamIds->count();
        $activeCount   = $profiles->where('status', EmployeeProfile::ACTIVE)->count();

        $run = PayrollRun::where('year', $today->year)->where('month', $today->month)->first();

        $pendingList = Leave::whereIn('user_id', $teamIds)
            ->where('status', Leave::PENDING)
            ->with(['employee:id,name', 'type:id,name'])
            ->orderBy('start_date')
            ->limit(6)->get();

        $newJoiners = $profiles
            ->filter(fn ($p) => $p->date_of_joining && $p->date_of_joining->gte($today->copy()->subDays(30)))
            ->sortByDesc('date_of_joining')
            ->take(6)
            ->values();

        return view('payroll::dashboard-hr', [
            'hr'          => $hr,
            'greeting'    => $this->greeting(),
            'periodLabel' => $today->format('F Y'),
            'kpis' => [
                'team'          => $teamCount,
                'active'        => $activeCount,
                'presentToday'  => $presentToday,
                'onLeaveToday'  => $onLeaveToday,
                'absentToday'   => $absentToday,
                'unmarkedToday' => max(0, $teamCount - $marked),
                'pendingLeaves' => $pendingList->count() === 6
                                    ? Leave::whereIn('user_id', $teamIds)->where('status', Leave::PENDING)->count()
                                    : $pendingList->count(),
                'attendancePct' => $this->teamAttendancePct($teamIds),
                'payrollNet'    => $run?->total_net,
                'payrollStatus' => $run?->status,
                'payrollRun'    => $run,
            ],
            'trend'       => $this->attendanceTrend($teamIds, 14),
            'donut'       => $this->todayBreakdown($todayRows, $teamCount),
            'byDept'      => $this->headcountByDepartment($profiles),
            'payrollBars' => $this->payrollTrend(6),
            'composition' => $this->workforceComposition($profiles),
            'pendingList' => $pendingList,
            'newJoiners'  => $newJoiners,
        ]);
    }

    private function greeting(): string
    {
        $h = (int) now()->format('G');

        return match (true) {
            $h < 12 => 'Good morning',
            $h < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * Team attendance rate per calendar day for the last $days days.
     * rate = (present + wfh + ½·half-day) / team size, clamped 0–100.
     *
     * @return array<int, array{label:string,dom:string,rate:float,present:int,total:int,weekend:bool,today:bool,future:bool}>
     */
    private function attendanceTrend(\Illuminate\Support\Collection $teamIds, int $days): array
    {
        $teamCount = max($teamIds->count(), 1);
        $start     = today()->copy()->subDays($days - 1);

        $rows = Attendance::whereIn('user_id', $teamIds)
            ->whereBetween('attendance_date', [$start->toDateString(), today()->toDateString()])
            ->get(['attendance_date', 'status'])
            ->groupBy(fn ($r) => $r->attendance_date->toDateString());

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date  = $start->copy()->addDays($i);
            $day   = $rows->get($date->toDateString(), collect());
            $score = $day->whereIn('status', [Attendance::PRESENT, Attendance::WFH])->count()
                     + 0.5 * $day->where('status', Attendance::HALF_DAY)->count();
            $rate  = $teamIds->isEmpty() ? 0 : min(100, round($score / $teamCount * 100, 1));

            $out[] = [
                'label'   => $date->format('D d M'),
                'dom'     => $date->format('j'),
                'rate'    => (float) $rate,
                'present' => (int) round($score),
                'total'   => $teamIds->count(),
                'weekend' => $date->isSunday(),
                'today'   => $date->isToday(),
                'future'  => false,
            ];
        }

        return $out;
    }

    /**
     * Today's headcount split by attendance status (+ an "unmarked" bucket).
     *
     * @return array<int, array{label:string,value:int,color:string}>
     */
    private function todayBreakdown(\Illuminate\Support\Collection $todayRows, int $teamCount): array
    {
        $c = fn (string $s) => $todayRows->where('status', $s)->count();

        $rows = [
            ['label' => 'Present',   'value' => $c(Attendance::PRESENT),  'color' => '#059669'],
            ['label' => 'Work from home', 'value' => $c(Attendance::WFH), 'color' => '#0ea5e9'],
            ['label' => 'Half day',  'value' => $c(Attendance::HALF_DAY), 'color' => '#d97706'],
            ['label' => 'On leave',  'value' => $c(Attendance::LEAVE),    'color' => '#7c3aed'],
            ['label' => 'Absent',    'value' => $c(Attendance::ABSENT),   'color' => '#dc2626'],
            ['label' => 'Holiday',   'value' => $c(Attendance::HOLIDAY),  'color' => '#94a3b8'],
            ['label' => 'Not marked','value' => max(0, $teamCount - $todayRows->count()), 'color' => '#e2e8f0'],
        ];

        return array_values(array_filter($rows, fn ($r) => $r['value'] > 0));
    }

    /**
     * Active-employee headcount per department, largest first.
     *
     * @return array<int, array{name:string,count:int,pct:float}>
     */
    private function headcountByDepartment(\Illuminate\Support\Collection $profiles): array
    {
        $active = $profiles->where('status', EmployeeProfile::ACTIVE);
        $total  = max($active->count(), 1);

        return $active
            ->groupBy(fn ($p) => $p->department?->name ?: 'Unassigned')
            ->map(fn ($g, $name) => [
                'name'  => $name,
                'count' => $g->count(),
                'pct'   => round($g->count() / $total * 100, 1),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Net payroll cost for each of the last $months months (null where no run).
     *
     * @return array<int, array{label:string,year:int,net:float|null,status:string|null,current:bool}>
     */
    private function payrollTrend(int $months): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m   = now()->copy()->startOfMonth()->subMonths($i);
            $run = PayrollRun::where('year', $m->year)->where('month', $m->month)->first();

            $out[] = [
                'label'   => $m->format('M'),
                'year'    => (int) $m->year,
                'net'     => $run ? (float) $run->total_net : null,
                'status'  => $run?->status,
                'current' => $i === 0,
            ];
        }

        return $out;
    }

    /**
     * Workforce split by employment status and by employment type.
     *
     * @return array{status:array<int,array{label:string,value:int,color:string}>,type:array<int,array{label:string,value:int,color:string}>}
     */
    private function workforceComposition(\Illuminate\Support\Collection $profiles): array
    {
        $statusMeta = [
            EmployeeProfile::ACTIVE     => ['Active', '#059669'],
            EmployeeProfile::ONBOARDING => ['Onboarding', '#d97706'],
            EmployeeProfile::SUSPENDED  => ['Suspended', '#94a3b8'],
            EmployeeProfile::EXITED     => ['Exited', '#dc2626'],
        ];
        $typeMeta = [
            'full_time' => ['Full-time', '#4f46e5'],
            'part_time' => ['Part-time', '#0ea5e9'],
            'contract'  => ['Contract', '#7c3aed'],
            'intern'    => ['Intern', '#d97706'],
        ];

        $bucket = function (array $meta, callable $key) use ($profiles) {
            $out = [];
            foreach ($meta as $k => [$label, $color]) {
                $n = $profiles->filter(fn ($p) => $key($p) === $k)->count();
                if ($n > 0) {
                    $out[] = ['label' => $label, 'value' => $n, 'color' => $color];
                }
            }

            return $out;
        };

        return [
            'status' => $bucket($statusMeta, fn ($p) => $p->status),
            'type'   => $bucket($typeMeta, fn ($p) => $p->employment_type ?: 'full_time'),
        ];
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
