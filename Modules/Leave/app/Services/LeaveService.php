<?php

namespace Modules\Leave\app\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\app\Models\Attendance;
use Modules\Attendance\app\Services\AttendanceService;
use Modules\Leave\app\Models\Leave;
use Modules\Leave\app\Models\LeaveBalance;
use Modules\Leave\app\Models\LeaveType;

/**
 * Leave workflow with two integrations:
 *   - paid-leave balances (leave_balances ledger), and
 *   - attendance: approving a leave writes attendance rows so payroll's LOP
 *     picks it up automatically (paid -> "Leave" = no LOP; unpaid -> "Absent").
 */
class LeaveService
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    /** Inclusive working-day count for a range (half-day => 0.5 on a single date). */
    public function computeDays(Carbon|string $start, Carbon|string $end, bool $halfDay = false): float
    {
        $start = Carbon::parse($start)->startOfDay();
        $end   = Carbon::parse($end)->startOfDay();

        if ($halfDay && $start->equalTo($end)) {
            return 0.5;
        }

        return (float) ($start->diffInDays($end) + 1);
    }

    /** Get (or lazily create) a year's balance row, seeded from the type quota. */
    public function balanceFor(int $userId, LeaveType $type, ?int $year = null): LeaveBalance
    {
        $year = $year ?: (int) now()->year;

        return LeaveBalance::firstOrCreate(
            ['user_id' => $userId, 'leave_type_id' => $type->id, 'year' => $year],
            ['allotted' => $type->annual_quota, 'used' => 0],
        );
    }

    /**
     * Employee applies for leave. Throws on overlap or insufficient paid balance.
     */
    public function apply(int $userId, LeaveType $type, Carbon|string $start, Carbon|string $end, ?string $reason, bool $halfDay = false, ?string $halfSession = null): Leave
    {
        $start = Carbon::parse($start)->toDateString();
        $end   = Carbon::parse($end)->toDateString();

        if ($end < $start) {
            throw new \InvalidArgumentException('End date cannot be before start date.');
        }

        $days = $this->computeDays($start, $end, $halfDay);

        if ($this->hasOverlap($userId, $start, $end)) {
            throw new \RuntimeException('You already have a leave request covering these dates.');
        }

        if ($type->is_paid) {
            $balance = $this->balanceFor($userId, $type, (int) Carbon::parse($start)->year);
            if ($balance->available() < $days) {
                throw new \RuntimeException("Insufficient {$type->name} balance ({$balance->available()} day(s) left).");
            }
        }

        return Leave::create([
            'user_id'       => $userId,
            'leave_type_id' => $type->id,
            'start_date'    => $start,
            'end_date'      => $end,
            'days'          => $days,
            'half_session'  => ($days === 0.5) ? ($halfSession === Leave::HALF_FIRST ? Leave::HALF_FIRST : Leave::HALF_SECOND) : null,
            'reason'        => $reason,
            'status'        => Leave::PENDING,
        ]);
    }

    /**
     * HR approves: consume paid balance + write attendance rows for the range.
     */
    public function approve(Leave $leave, ?int $approverId = null): bool
    {
        if (! $leave->isPending()) {
            return false;
        }

        DB::transaction(function () use ($leave, $approverId) {
            $type = $leave->type;

            $leave->update([
                'status'      => Leave::APPROVED,
                'approved_by' => $approverId,
                'reviewed_at' => now(),
            ]);

            if ($type->is_paid) {
                $balance = $this->balanceFor($leave->user_id, $type, (int) $leave->start_date->year);
                $balance->increment('used', (float) $leave->days);
            }

            $this->writeAttendance($leave, $type->is_paid, $approverId);
        });

        return true;
    }

    public function reject(Leave $leave, ?int $approverId = null, ?string $note = null): bool
    {
        if (! $leave->isPending()) {
            return false;
        }

        $leave->update([
            'status'      => Leave::REJECTED,
            'approved_by' => $approverId,
            'review_note' => $note,
            'reviewed_at' => now(),
        ]);

        return true;
    }

    /** Employee cancels their own still-pending request. */
    public function cancel(Leave $leave): bool
    {
        if (! $leave->isPending()) {
            return false;
        }

        return (bool) $leave->update(['status' => Leave::CANCELLED]);
    }

    /* ------------------------------------------------------------------ */

    private function hasOverlap(int $userId, string $start, string $end): bool
    {
        return Leave::where('user_id', $userId)
            ->whereIn('status', [Leave::PENDING, Leave::APPROVED])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();
    }

    /** Post one attendance row per day in the leave range. */
    private function writeAttendance(Leave $leave, bool $isPaid, ?int $markedBy): void
    {
        $cursor = $leave->start_date->copy();
        $isHalf = ((float) $leave->days === 0.5);

        // Half day: AP if the first half is taken, PA if the second half is.
        // Full day: AA (the day-type tag keeps paid leave from causing LOP).
        $status = $isHalf
            ? ($leave->half_session === Leave::HALF_FIRST ? Attendance::AP : Attendance::PA)
            : Attendance::AA;
        $dayType = $isPaid ? Attendance::DAY_PAID_LEAVE : Attendance::DAY_UNPAID_LEAVE;

        while ($cursor->lte($leave->end_date)) {
            $this->attendance->mark($leave->user_id, $cursor->toDateString(), $status, [
                'marked_by' => $markedBy,
                'source'    => 'leave',
                'day_type'  => $dayType,
                'remarks'   => 'Auto from approved leave #'.$leave->id,
            ]);
            $cursor->addDay();
        }
    }
}
