<?php

namespace Modules\Payroll\app\Support;

use Carbon\Carbon;
use Modules\Attendance\app\Models\Attendance;
use Modules\Attendance\app\Services\AttendanceService;
use Modules\Leave\app\Models\Leave;

/**
 * The day columns of the Form XI wage slip for one employee-month.
 *
 * Form XI splits the month into OT hours, days present, weekly-offs, holidays
 * and each leave head (CL / EL / SL) separately. The attendance table only
 * records a single "Leave" status, so the per-head split comes from the Leave
 * module's approved leaves matched on their leave-type code.
 */
class SlipAttendance
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    /**
     * @return array{ot_hours:float, present:float, wo:float, hd:float, cl:float,
     *               el:float, sl:float, sp:float, payable_days:float, working_days:float,
     *               absent:float, paid_leave:float, unpaid_leave:float}
     */
    public function forMonth(int $userId, int $year, int $month): array
    {
        $summary   = $this->attendance->monthlySummary($userId, $year, $month);
        $leaveDays = $this->leaveDays($userId, $year, $month);
        $rows      = $this->monthRows($userId, $year, $month);

        // Both an approved unpaid leave and a plain unexplained absence land in
        // the summary's `absent` bucket as a full-day AA (see
        // LeaveService::writeAttendance) — the only way to tell them apart is by
        // cross-referencing the Leave module. Whatever absence isn't covered by
        // an approved unpaid leave is a plain absence.
        $unpaidLeave = $leaveDays['unpaid'];
        $absent      = max(0.0, (float) $summary['absent'] - $unpaidLeave);

        return [
            'ot_hours'     => $this->overtimeHours($rows),
            'present'      => (float) $summary['present'] + (float) $summary['wfh'],
            'wo'           => $this->weeklyOffs($rows, $year, $month),
            'hd'           => (float) $summary['holiday'],
            'cl'           => $leaveDays['byCode']['CL'] ?? 0.0,
            'el'           => $leaveDays['byCode']['EL'] ?? 0.0,
            'sl'           => $leaveDays['byCode']['SL'] ?? 0.0,
            'sp'           => (float) $summary['half_day'],
            'payable_days' => (float) $summary['payable_days'],
            'working_days' => (float) $summary['working_days'],
            'absent'       => $absent,
            'paid_leave'   => $leaveDays['paid'],
            'unpaid_leave' => $unpaidLeave,
        ];
    }

    /**
     * Hours worked beyond the standard working day, summed over the month.
     * Only days with both punches contribute — a missing punch-out is an
     * incomplete record, not free overtime.
     */
    private function overtimeHours($rows): float
    {
        $standard = (int) config('payroll.attendance.standard_day_minutes', 480);

        $minutes = $rows
            ->whereNotNull('worked_minutes')
            ->sum(fn (Attendance $row) => max(0, (int) $row->worked_minutes - $standard));

        return round($minutes / 60, 1);
    }

    /**
     * Weekly-off days: the configured rest day of the week, counting only those
     * with no attendance row — an employee who worked their weekly off is
     * marked Present that day and must not be double-counted.
     */
    private function weeklyOffs($rows, int $year, int $month): float
    {
        $offDay = (int) config('payroll.attendance.weekly_off_day', Carbon::SUNDAY);
        $marked = $rows
            ->map(fn (Attendance $row) => $row->attendance_date->day)
            ->all();

        $cursor = Carbon::create($year, $month, 1)->startOfMonth();
        $count  = 0;
        for ($day = 1; $day <= $cursor->daysInMonth; $day++) {
            if ($cursor->day($day)->dayOfWeek === $offDay && ! in_array($day, $marked, true)) {
                $count++;
            }
        }

        return (float) $count;
    }

    /**
     * Approved leave days in the month, both keyed by leave-type code
     * (CL/EL/SL/…) and split into paid vs unpaid totals.
     *
     * A leave wholly inside the month contributes its stored `days` (which may
     * already exclude weekends); one straddling a month boundary contributes
     * only the calendar days that fall inside this month, since the stored
     * total cannot be split reliably.
     *
     * @return array{byCode: array<string, float>, paid: float, unpaid: float}
     */
    private function leaveDays(int $userId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        $leaves = Leave::with('type')
            ->where('user_id', $userId)
            ->where('status', Leave::APPROVED)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();

        $byCode = [];
        $paid   = 0.0;
        $unpaid = 0.0;

        foreach ($leaves as $leave) {
            $inside = $leave->start_date->gte($start) && $leave->end_date->lte($end);
            $days   = $inside
                ? (float) $leave->days
                : $leave->start_date->max($start)->diffInDays($leave->end_date->min($end)) + 1;

            $isPaid = (bool) ($leave->type->is_paid ?? true);
            $isPaid ? $paid += $days : $unpaid += $days;

            $code = strtoupper((string) ($leave->type->code ?? ''));
            if ($code !== '') {
                $byCode[$code] = ($byCode[$code] ?? 0.0) + $days;
            }
        }

        return ['byCode' => $byCode, 'paid' => $paid, 'unpaid' => $unpaid];
    }

    /** @return \Illuminate\Support\Collection<int, Attendance> */
    private function monthRows(int $userId, int $year, int $month)
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        return Attendance::where('user_id', $userId)
            ->whereBetween('attendance_date', [$start->toDateString(), (clone $start)->endOfMonth()->toDateString()])
            ->get();
    }
}
