<?php

namespace App\Services;

use App\Exceptions\AccessPermissionDeniedException;
use App\Models\CourseBatch;
use App\Models\StudentTemporarySlot;
use App\Models\User;
use App\Notifications\StudentTemporarySlotAssigned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\app\Models\Enrollment;

/**
 * Date-specific temporary batch slots (2026-07-15). A student attends a DIFFERENT
 * batch of the SAME course on one date, keeping their primary batch untouched.
 * Every mutation is tenant-gated, same-course validated, date-wise capacity
 * enforced, duplicate-blocked, transaction-safe, audit-logged and notified.
 * Reused by the coach panel. The roster union lives in BatchAttendanceService.
 */
class TemporarySlotService
{
    /** Active, tenant-owned batches of the course, excluding the primary. */
    public function eligibleBatches(int $coachId, int $courseId, ?int $excludePrimaryId = null): Collection
    {
        return CourseBatch::query()
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->whereHas('course', fn ($q) => $q->where(fn ($w) => $w->where('instructor_id', $coachId)->orWhere('added_by', $coachId)))
            ->when($excludePrimaryId, fn ($q) => $q->where('id', '!=', $excludePrimaryId))
            ->orderBy('title')
            ->get();
    }

    public function assertOwnedBatch(int $coachId, CourseBatch $batch): void
    {
        $batch->loadMissing('course:id,title,instructor_id,added_by');
        if (! $batch->course
            || ((int) $batch->course->instructor_id !== $coachId && (int) $batch->course->added_by !== $coachId)) {
            throw new AccessPermissionDeniedException();
        }
    }

    /**
     * Seats used on a batch for a specific date = permanent roster + scheduled
     * temporary guests coming in that date.
     */
    public function seatsUsedOnDate(CourseBatch $batch, string $date): int
    {
        $guests = StudentTemporarySlot::where('target_batch_id', $batch->id)
            ->whereDate('slot_date', $date)
            ->where('status', StudentTemporarySlot::STATUS_SCHEDULED)
            ->distinct('student_id')->count('student_id');

        return $batch->seatsUsed() + (int) $guests;
    }

    /**
     * Assign a temporary slot. Validates same-course enrolment, active batch,
     * tenant ownership, date-wise capacity, and blocks duplicates.
     */
    public function assign(int $coachId, int $studentId, CourseBatch $target, string $slotDate, ?string $reason, int $createdBy): array
    {
        $this->assertOwnedBatch($coachId, $target);

        if ($target->status !== 'active') {
            return $this->fail(__('The selected batch is not active.'));
        }

        try {
            $date = Carbon::parse($slotDate)->toDateString();
        } catch (\Throwable $e) {
            return $this->fail(__('Please choose a valid date.'));
        }
        if (Carbon::parse($date)->lt(Carbon::today())) {
            return $this->fail(__('The date cannot be in the past.'));
        }

        $courseId = (int) $target->course_id;

        // Student must already be enrolled in the target batch's course.
        $enrollment = Enrollment::where('user_id', $studentId)->where('course_id', $courseId)->first();
        if (! $enrollment) {
            return $this->fail(__('This student is not enrolled in the related course.'));
        }
        $primaryBatchId = $enrollment->batch_id ? (int) $enrollment->batch_id : null;

        // Attending their own primary batch on that date is a no-op.
        if ($primaryBatchId === (int) $target->id) {
            return $this->fail(__('This is already the student\'s batch for that date.'));
        }

        // Duplicate (same student + target batch + date, still scheduled).
        $dup = StudentTemporarySlot::where('student_id', $studentId)
            ->where('target_batch_id', $target->id)
            ->whereDate('slot_date', $date)
            ->where('status', StudentTemporarySlot::STATUS_SCHEDULED)
            ->exists();
        if ($dup) {
            return $this->fail(__('This student already has a temporary slot in this batch on that date.'));
        }

        // Date-wise capacity (permanent roster + guests that day).
        if ($target->hasCapacityLimit() && $this->seatsUsedOnDate($target, $date) + 1 > (int) $target->capacity) {
            return $this->fail(__('The selected batch is full on that date.'));
        }

        try {
            $slot = null;
            DB::transaction(function () use (&$slot, $coachId, $studentId, $courseId, $primaryBatchId, $target, $date, $reason, $createdBy) {
                $slot = StudentTemporarySlot::create([
                    'coach_id'         => $coachId,
                    'student_id'       => $studentId,
                    'course_id'        => $courseId,
                    'primary_batch_id' => $primaryBatchId,
                    'target_batch_id'  => (int) $target->id,
                    'slot_date'        => $date,
                    'reason'           => is_string($reason) && trim($reason) !== '' ? trim($reason) : null,
                    'status'           => StudentTemporarySlot::STATUS_SCHEDULED,
                    'created_by'       => $createdBy,
                ]);

                ActivityLogger::log(
                    \App\Models\ActivityLog::CREATED,
                    'temporary_slot',
                    $slot,
                    null,
                    ['student_id' => $studentId, 'target_batch_id' => (int) $target->id, 'slot_date' => $date],
                    'Temporary slot: student #' . $studentId . ' → batch "' . ($target->title ?? $target->id) . '" on ' . $date
                );
            });
        } catch (\Throwable $e) {
            Log::error('Temporary slot assign failed: ' . $e->getMessage(), ['student_id' => $studentId, 'target_batch_id' => $target->id]);
            return $this->fail(__('Could not assign the temporary slot. Please try again.'));
        }

        try {
            $student = User::find($studentId);
            $coach   = User::find($coachId);
            if ($student) {
                $student->notify(new StudentTemporarySlotAssigned($target->loadMissing('course:id,title'), $date, $coach));
            }
        } catch (\Throwable $e) {
            Log::warning('Temporary slot notify failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'slot_id' => $slot?->id, 'message' => __('Temporary slot assigned successfully.')];
    }

    /** Cancel a scheduled slot (tenant-gated). */
    public function cancel(int $coachId, int $slotId): array
    {
        $slot = StudentTemporarySlot::forCoach($coachId)->find($slotId);
        if (! $slot) {
            return $this->fail(__('Temporary slot not found.'));
        }
        if ($slot->status === StudentTemporarySlot::STATUS_CANCELLED) {
            return ['ok' => true, 'message' => __('Already cancelled.')];
        }
        $slot->update(['status' => StudentTemporarySlot::STATUS_CANCELLED]);
        ActivityLogger::log(
            \App\Models\ActivityLog::UPDATED, 'temporary_slot', $slot,
            ['status' => 'scheduled'], ['status' => 'cancelled'],
            'Temporary slot #' . $slot->id . ' cancelled'
        );

        return ['ok' => true, 'message' => __('Temporary slot cancelled.')];
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
