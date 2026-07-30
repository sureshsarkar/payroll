<?php

namespace Modules\Attendance\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Attendance\app\Models\Attendance;

/**
 * Core attendance domain logic: marking, check-in/out and monthly rollups.
 *
 * "Employee" == a user with role=student; "HR" == role=instructor. This service
 * is role-agnostic — access scoping is enforced in the controller/policies.
 */
class AttendanceService
{
    /**
     * Create or update the single attendance row for a user on a date.
     */
    public function mark(int $userId, string|Carbon $date, string $status, array $attrs = []): Attendance
    {
        $date = Carbon::parse($date)->toDateString();

        if (! in_array($status, Attendance::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid attendance status: {$status}");
        }

        $attendance = Attendance::firstOrNew([
            'user_id'         => $userId,
            'attendance_date' => $date,
        ]);

        $attendance->status         = $status;
        $attendance->check_in       = $attrs['check_in']   ?? $attendance->check_in;
        $attendance->check_out      = $attrs['check_out']  ?? $attendance->check_out;
        $attendance->source         = $attrs['source']     ?? ($attendance->source ?: 'manual');
        $attendance->marked_by      = $attrs['marked_by']  ?? $attendance->marked_by;
        $attendance->remarks        = $attrs['remarks']    ?? $attendance->remarks;
        $attendance->worked_minutes = $this->computeWorkedMinutes($attendance->check_in, $attendance->check_out);
        $attendance->save();

        return $attendance;
    }

    /**
     * Employee self check-in for today (idempotent — keeps the first punch).
     */
    public function checkIn(int $userId, ?Carbon $now = null): Attendance
    {
        $now = $now ?: Carbon::now();
        $attendance = Attendance::firstOrNew([
            'user_id'         => $userId,
            'attendance_date' => $now->toDateString(),
        ]);

        if (empty($attendance->check_in)) {
            $attendance->check_in = $now->format('H:i:s');
        }
        $attendance->status = $attendance->status ?: Attendance::PRESENT;
        if (! $attendance->exists || $attendance->status === Attendance::ABSENT) {
            $attendance->status = Attendance::PRESENT;
        }
        $attendance->source = $attendance->source ?: 'self';
        $attendance->worked_minutes = $this->computeWorkedMinutes($attendance->check_in, $attendance->check_out);
        $attendance->save();

        return $attendance;
    }

    /**
     * Employee self check-out for today.
     */
    public function checkOut(int $userId, ?Carbon $now = null): Attendance
    {
        $now = $now ?: Carbon::now();
        $attendance = Attendance::firstOrNew([
            'user_id'         => $userId,
            'attendance_date' => $now->toDateString(),
        ]);

        $attendance->check_out = $now->format('H:i:s');
        $attendance->status = $attendance->status ?: Attendance::PRESENT;
        $attendance->source = $attendance->source ?: 'self';
        $attendance->worked_minutes = $this->computeWorkedMinutes($attendance->check_in, $attendance->check_out);
        $attendance->save();

        return $attendance;
    }

    /**
     * HR bulk-marks the same status for a set of employees on one date.
     * Returns the number of rows written.
     */
    public function bulkMark(array $userIds, string|Carbon $date, string $status, ?int $markedBy = null): int
    {
        $count = 0;
        foreach (array_unique($userIds) as $userId) {
            $this->mark((int) $userId, $date, $status, [
                'marked_by' => $markedBy,
                'source'    => 'manual',
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Roll a user's month up into counts + LOP + payable days.
     *
     * @return array{present:int,absent:int,half_day:int,leave:int,holiday:int,wfh:int,marked_days:int,working_days:int,lop_days:float,payable_days:float}
     */
    public function monthlySummary(int $userId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        $workingDays = $start->daysInMonth; // simple model; weekly-offs config comes later

        $rows = Attendance::where('user_id', $userId)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $counts = array_fill_keys(Attendance::STATUSES, 0);
        $lop = 0.0;
        foreach ($rows as $row) {
            $counts[$row->status] = ($counts[$row->status] ?? 0) + 1;
            $lop += $row->lopWeight();
        }

        return [
            'present'      => $counts[Attendance::PRESENT],
            'absent'       => $counts[Attendance::ABSENT],
            'half_day'     => $counts[Attendance::HALF_DAY],
            'leave'        => $counts[Attendance::LEAVE],
            'holiday'      => $counts[Attendance::HOLIDAY],
            'wfh'          => $counts[Attendance::WFH],
            'marked_days'  => $rows->count(),
            'working_days' => $workingDays,
            'lop_days'     => round($lop, 1),
            'payable_days' => round($workingDays - $lop, 1),
        ];
    }

    /**
     * All attendance rows for a user in a month, keyed by day-of-month —
     * ready for a calendar view.
     *
     * @return Collection<int, Attendance>
     */
    public function monthMap(int $userId, int $year, int $month): Collection
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        return Attendance::where('user_id', $userId)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a) => (int) $a->attendance_date->day);
    }

    private function computeWorkedMinutes(?string $checkIn, ?string $checkOut): ?int
    {
        if (empty($checkIn) || empty($checkOut)) {
            return null;
        }
        $in  = Carbon::parse($checkIn);
        $out = Carbon::parse($checkOut);
        if ($out->lessThanOrEqualTo($in)) {
            return null;
        }

        return $in->diffInMinutes($out);
    }
}
