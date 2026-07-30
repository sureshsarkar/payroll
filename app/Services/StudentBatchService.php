<?php

namespace App\Services;

use App\Exceptions\AccessPermissionDeniedException;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\StudentBatchAssignment;
use App\Models\User;
use App\Notifications\StudentBatchAssignedToStudent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\app\Models\Enrollment;

/**
 * Student batch assignment / reassignment — the single source of truth
 * (2026-07-15). Every mutation is tenant-gated, capacity-checked, wrapped in a
 * DB transaction, written to the student_batch_assignments ledger, audit-logged
 * and (best-effort) notified. Reused by the batch-roster "assign into batch"
 * flow AND the student-panel reassign/bulk flow so there is ZERO logic drift.
 *
 * Membership lives on enrollments.batch_id (unchanged schema). Same-course only:
 * a student can be moved/added ONLY among batches of a course they are already
 * enrolled in — never creates new course access.
 */
class StudentBatchService
{
    /** Active, tenant-owned batches of a course (for the reassign dropdown). */
    public function eligibleBatches(int $coachId, int $courseId, ?int $excludeBatchId = null): Collection
    {
        return CourseBatch::query()
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->whereHas('course', fn ($q) => $q->where(fn ($w) => $w->where('instructor_id', $coachId)->orWhere('added_by', $coachId)))
            ->when($excludeBatchId, fn ($q) => $q->where('id', '!=', $excludeBatchId))
            ->orderBy('title')
            ->get();
    }

    /** THE tenant gate for a batch (coach owns the course the batch belongs to). */
    public function assertOwnedBatch(int $coachId, CourseBatch $batch): void
    {
        $batch->loadMissing('course:id,title,instructor_id,added_by');
        if (! $batch->course
            || ((int) $batch->course->instructor_id !== $coachId && (int) $batch->course->added_by !== $coachId)) {
            throw new AccessPermissionDeniedException();
        }
    }

    /**
     * Put a student INTO a batch — assign (was batch-less) or reassign/move (was
     * in another batch of the SAME course). Mutates the existing enrollment row's
     * batch_id in place; never creates a second row. Returns a result array.
     *
     * @param int|null $fromBatchId when set (student-panel move), only the
     *                 enrollment currently in that batch is moved.
     */
    public function putIntoBatch(int $coachId, int $studentId, CourseBatch $batch, ?string $reason, int $changedBy, ?int $fromBatchId = null): array
    {
        $this->assertOwnedBatch($coachId, $batch);

        if ($batch->status !== 'active') {
            return $this->fail(__('The selected batch is not active.'));
        }

        $courseId = (int) $batch->course_id;

        // The student MUST already be enrolled in this course (never invent access).
        $enrollments = Enrollment::where('user_id', $studentId)->where('course_id', $courseId)->get();
        if ($enrollments->isEmpty()) {
            return $this->fail(__('This student is not enrolled in the related course.'));
        }

        // Already in the target batch?
        if ($enrollments->contains(fn ($e) => (int) $e->batch_id === (int) $batch->id)) {
            return $this->fail(__('This student is already assigned to the selected batch.'));
        }

        // Pick the enrollment row to move.
        if ($fromBatchId) {
            $current = $enrollments->firstWhere('batch_id', $fromBatchId);
            if (! $current) {
                return $this->fail(__('The student is not in the batch you are moving them from.'));
            }
        } else {
            // Batch-roster flow: prefer the assigned one, else the batch-less row.
            $current = $enrollments->firstWhere('batch_id', '!==', null) ?? $enrollments->first();
        }

        // Capacity guard (enforced only in this flow; warn-mode elsewhere).
        if ($batch->wouldExceedCapacity()) {
            $batch->logCapacityBreach('student_batch_reassign', $studentId);
            return $this->fail(__('The selected batch has reached its maximum capacity.'));
        }

        $previousBatchId = $current->batch_id ? (int) $current->batch_id : null;
        $action = $previousBatchId ? StudentBatchAssignment::ACTION_REASSIGN : StudentBatchAssignment::ACTION_ASSIGN;

        return $this->commit($coachId, $studentId, $courseId, $batch, $previousBatchId, $action, $reason, $changedBy, function () use ($current, $batch) {
            $current->batch_id = $batch->id;
            $current->has_access = 1;
            $current->save();
        });
    }

    /**
     * ADD an extra batch WITHOUT removing the current one — creates a second
     * enrollment row for the same course (multi-batch model). Reason optional.
     */
    public function addToBatch(int $coachId, int $studentId, CourseBatch $batch, ?string $reason, int $changedBy): array
    {
        $this->assertOwnedBatch($coachId, $batch);

        if ($batch->status !== 'active') {
            return $this->fail(__('The selected batch is not active.'));
        }

        $courseId = (int) $batch->course_id;

        $enrollments = Enrollment::where('user_id', $studentId)->where('course_id', $courseId)->get();
        if ($enrollments->isEmpty()) {
            return $this->fail(__('This student is not enrolled in the related course.'));
        }
        if ($enrollments->contains(fn ($e) => (int) $e->batch_id === (int) $batch->id)) {
            return $this->fail(__('This student is already assigned to the selected batch.'));
        }
        if ($batch->wouldExceedCapacity()) {
            $batch->logCapacityBreach('student_batch_add', $studentId);
            return $this->fail(__('The selected batch has reached its maximum capacity.'));
        }

        return $this->commit($coachId, $studentId, $courseId, $batch, null, StudentBatchAssignment::ACTION_ADD, $reason, $changedBy, function () use ($studentId, $courseId, $batch) {
            Enrollment::firstOrCreate(
                ['user_id' => $studentId, 'course_id' => $courseId, 'batch_id' => $batch->id],
                ['order_id' => null, 'has_access' => 1]
            );
        });
    }

    /**
     * Shared commit: run the mutation + ledger + audit inside a transaction, then
     * (best-effort, post-commit) notify the student. Rolls back everything on
     * any failure and surfaces a user-friendly error.
     */
    private function commit(int $coachId, int $studentId, int $courseId, CourseBatch $batch, ?int $previousBatchId, string $action, ?string $reason, int $changedBy, callable $mutate): array
    {
        $reason = is_string($reason) ? trim($reason) : null;

        try {
            $history = null;
            DB::transaction(function () use (&$history, $mutate, $coachId, $studentId, $courseId, $batch, $previousBatchId, $action, $reason, $changedBy) {
                $mutate();

                $history = StudentBatchAssignment::create([
                    'coach_id'          => $coachId,
                    'student_id'        => $studentId,
                    'course_id'         => $courseId,
                    'previous_batch_id' => $previousBatchId,
                    'new_batch_id'      => (int) $batch->id,
                    'action'            => $action,
                    'reason'            => $reason ?: null,
                    'changed_by'        => $changedBy,
                    'effective_at'      => now(),
                ]);

                ActivityLogger::log(
                    \App\Models\ActivityLog::UPDATED,
                    'student_batch',
                    $history,
                    ['batch_id' => $previousBatchId],
                    ['batch_id' => (int) $batch->id, 'action' => $action, 'reason' => $reason],
                    'Student #' . $studentId . ' ' . $action . ' to batch "' . ($batch->title ?? $batch->id) . '"'
                        . ($previousBatchId ? ' (from batch #' . $previousBatchId . ')' : '')
                );
            });
        } catch (\Throwable $e) {
            Log::error('Student batch ' . $action . ' failed: ' . $e->getMessage(), ['student_id' => $studentId, 'batch_id' => $batch->id]);
            return $this->fail(__('Could not update the batch assignment. Please try again.'));
        }

        // Best-effort notification (coach settings gate it) — never blocks the change.
        try {
            $student = User::find($studentId);
            $coach   = User::find($coachId);
            if ($student) {
                $student->notify(new StudentBatchAssignedToStudent($batch->loadMissing('course:id,title'), $coach));
            }
        } catch (\Throwable $e) {
            Log::warning('Student batch notify failed: ' . $e->getMessage());
        }

        return [
            'ok'                => true,
            'action'            => $action,
            'previous_batch_id' => $previousBatchId,
            'new_batch_id'      => (int) $batch->id,
            'history_id'        => $history?->id,
            'message'           => __('Student batch assignment updated successfully.'),
        ];
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'action' => null, 'message' => $message];
    }
}
