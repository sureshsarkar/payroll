<?php

namespace Modules\Attendance\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        // withTrashed(): re-marking a day HR had deleted resurrects the same row
        // rather than colliding with the (user_id, attendance_date) unique index.
        $attendance = Attendance::withTrashed()->firstOrNew([
            'user_id'         => $userId,
            'attendance_date' => $date,
        ]);
        $attendance->deleted_at = null;

        $attendance->status         = $status;
        $attendance->day_type       = array_key_exists('day_type', $attrs) ? $attrs['day_type'] : $attendance->day_type;
        $attendance->check_in       = array_key_exists('check_in', $attrs) ? $attrs['check_in'] : $attendance->check_in;
        $attendance->check_out      = array_key_exists('check_out', $attrs) ? $attrs['check_out'] : $attendance->check_out;
        $attendance->source         = $attrs['source']     ?? ($attendance->source ?: 'manual');
        $attendance->marked_by      = $attrs['marked_by']  ?? $attendance->marked_by;
        $attendance->remarks        = array_key_exists('remarks', $attrs) ? $attrs['remarks'] : $attendance->remarks;
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
        $attendance = Attendance::withTrashed()->firstOrNew([
            'user_id'         => $userId,
            'attendance_date' => $now->toDateString(),
        ]);
        $attendance->deleted_at = null;

        if (empty($attendance->check_in)) {
            $attendance->check_in = $now->format('H:i:s');
        }
        $attendance->status = $attendance->status ?: Attendance::PP;
        if (! $attendance->exists || $attendance->status === Attendance::AA) {
            $attendance->status = Attendance::PP;
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
        $attendance = Attendance::withTrashed()->firstOrNew([
            'user_id'         => $userId,
            'attendance_date' => $now->toDateString(),
        ]);
        $attendance->deleted_at = null;

        $attendance->check_out = $now->format('H:i:s');
        $attendance->status = $attendance->status ?: Attendance::PP;
        $attendance->source = $attendance->source ?: 'self';
        $attendance->worked_minutes = $this->computeWorkedMinutes($attendance->check_in, $attendance->check_out);
        $attendance->save();

        return $attendance;
    }

    /**
     * HR bulk-marks the same status for a set of employees on one date.
     * Returns the number of rows written.
     */
    public function bulkMark(array $userIds, string|Carbon $date, string $status, ?int $markedBy = null, ?string $dayType = null): int
    {
        $count = 0;
        foreach (array_unique($userIds) as $userId) {
            $this->mark((int) $userId, $date, $status, [
                'marked_by' => $markedBy,
                'source'    => 'manual',
                'day_type'  => $dayType,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * A realistic random office day: check-in 09:30–09:40, check-out 18:30–19:00.
     * Single source of truth for the "quick fill" time generation.
     *
     * @return array{check_in:string, check_out:string, worked_minutes:int}
     */
    public function randomOfficeTimes(): array
    {
        $in  = (9 * 60) + random_int(30, 40);
        $out = (18 * 60) + random_int(30, 60);

        return [
            'check_in'       => sprintf('%02d:%02d', intdiv($in, 60), $in % 60),
            'check_out'      => sprintf('%02d:%02d', intdiv($out, 60), $out % 60),
            'worked_minutes' => $out - $in,
        ];
    }

    /**
     * Bulk-fill a month of Present attendance with random office times for one
     * or many employees.
     *
     * Rules (identical whether it is one employee or the whole team):
     *   - Sundays are skipped; Saturdays are filled like any working day.
     *   - Any day that already has an attendance row — including a soft-deleted
     *     one — is left untouched (nothing is overwritten).
     *   - Everything else becomes a full Present (PP) day with random times.
     *
     * Scales to large teams: one SELECT for the whole set, then chunked bulk
     * INSERTs inside a transaction — no per-day, per-employee round trips.
     *
     * @param  array<int>  $userIds
     * @return array{employees:int, days_filled:int}
     */
    public function fillMonthForUsers(array $userIds, int $year, int $month, ?int $markedBy = null): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return ['employees' => 0, 'days_filled' => 0];
        }

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        // One query for every (user, date) that already has a row — trashed
        // included, because the (user_id, attendance_date) unique index counts
        // soft-deleted rows and a bulk INSERT would collide with them.
        $taken = Attendance::withTrashed()
            ->whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get(['user_id', 'attendance_date'])
            ->map(fn ($r) => $r->user_id.'|'.Carbon::parse($r->attendance_date)->toDateString())
            ->flip();

        $companyId = currentCompany()?->id;
        $now       = now();
        $rows      = [];
        $touched   = [];

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            if ($date->isSunday()) {
                continue;
            }
            $dateStr = $date->toDateString();

            foreach ($userIds as $uid) {
                if ($taken->has($uid.'|'.$dateStr)) {
                    continue;
                }

                $t = $this->randomOfficeTimes();
                $rows[] = [
                    'company_id'      => $companyId,
                    'user_id'         => $uid,
                    'attendance_date' => $dateStr,
                    'status'          => Attendance::PP,
                    'day_type'        => null,
                    'check_in'        => $t['check_in'],
                    'check_out'       => $t['check_out'],
                    'worked_minutes'  => $t['worked_minutes'],
                    'source'          => 'manual',
                    'marked_by'       => $markedBy,
                    'remarks'         => 'Monthly attendance quick fill',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
                $touched[$uid] = true;
            }
        }

        if ($rows !== []) {
            DB::transaction(function () use ($rows) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    Attendance::insert($chunk);
                }
            });
        }

        return ['employees' => count($touched), 'days_filled' => count($rows)];
    }

    /**
     * Roll a user's month up into counts + LOP + payable days.
     *
     * The count keys keep their historical meaning so every downstream consumer
     * (payroll engine, Form IV/XI slips, dashboards) keeps working:
     *   present            full worked days (PP, no reason tag)
     *   absent             full unpaid off days (AA that is a plain absence or unpaid leave)
     *   half_day           any day with one absent half (AP or PA), any reason
     *   first_half_absent  AP days   second_half_absent  PA days
     *   leave              paid-leave days       holiday  holiday days       wfh  WFH days
     *
     * @return array{present:int,absent:int,half_day:int,first_half_absent:int,second_half_absent:int,leave:int,holiday:int,wfh:int,marked_days:int,working_days:int,lop_days:float,payable_days:float}
     */
    public function monthlySummary(int $userId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        $workingDays = $start->daysInMonth; // simple model; weekly-offs config comes later

        $rows = Attendance::where('user_id', $userId)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $present = $absent = $firstHalf = $secondHalf = $leave = $holiday = $wfh = 0;
        $lop = 0.0;

        foreach ($rows as $row) {
            $lop += $row->lopWeight();

            match ($row->day_type) {
                Attendance::DAY_WFH        => $wfh++,
                Attendance::DAY_HOLIDAY    => $holiday++,
                Attendance::DAY_PAID_LEAVE => $leave++,
                default                    => null,
            };

            match ($row->status) {
                Attendance::AP => $firstHalf++,
                Attendance::PA => $secondHalf++,
                Attendance::PP => ($row->day_type === null ? $present++ : null),
                Attendance::AA => (! $row->isPaidDayType() ? $absent++ : null),
                default        => null,
            };
        }

        return [
            'present'            => $present,
            'absent'             => $absent,
            'half_day'           => $firstHalf + $secondHalf,
            'first_half_absent'  => $firstHalf,
            'second_half_absent' => $secondHalf,
            'leave'              => $leave,
            'holiday'            => $holiday,
            'wfh'               => $wfh,
            'marked_days'        => $rows->count(),
            'working_days'       => $workingDays,
            'lop_days'           => round($lop, 1),
            'payable_days'       => round($workingDays - $lop, 1),
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
