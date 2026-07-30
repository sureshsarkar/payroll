<?php

namespace App\Services;

use App\Models\CourseLiveClass;
use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Order\app\Models\Enrollment;

/**
 * 2026-06-05 — Single source of truth for "who should be notified about a live
 * class". The recipient set is BATCH-SPECIFIC, not course-wide:
 *
 *   - the student has PAID access (enrollments.has_access = 1) to the class's
 *     course (course ownership = course.instructor_id, so this is implicitly
 *     scoped to the correct coach — a student of another coach's course can
 *     never match),
 *   - their enrolled batch matches the class's batch — or the class is
 *     course-wide (batch_id null) / the enrollment is a legacy null-batch one
 *     (which, like the join/visibility gate, sees every class in the course),
 *   - the user account is active and not banned,
 *   - deduplicated by user id.
 *
 * This mirrors CourseLiveClass's join/visibility rules exactly, so a student is
 * notified about precisely the classes they can actually join. Used by every
 * live-class-created notification path (coach OR staff) so the logic never
 * drifts between controllers.
 */
class LiveClassNotificationService
{
    /**
     * @return Collection<int, User>
     */
    public function recipients(CourseLiveClass $liveClass): Collection
    {
        $courseId = (int) ($liveClass->course_id ?: optional($liveClass->lesson)->course_id);
        if ($courseId <= 0) {
            return collect();
        }

        $query = Enrollment::query()
            ->where('course_id', $courseId)
            ->where('has_access', 1);

        if ($liveClass->batch_id !== null) {
            $query->where(function ($w) use ($liveClass) {
                $w->whereNull('batch_id')->orWhere('batch_id', $liveClass->batch_id);
            });
        }

        $userIds = $query->pluck('user_id')->unique()->values()->all();
        if (empty($userIds)) {
            return collect();
        }

        // Exclude inactive / banned accounts. Email-presence is enforced per
        // channel by the notification itself (mail channel is added only when
        // the user has an email), so we keep no-email students here for the
        // in-app/bell channel.
        return User::whereIn('id', $userIds)
            ->active()
            ->unbanned()
            ->get(['id', 'name', 'email']);
    }

    /**
     * 2026-06-06 — Send the "live class scheduled" notification to the eligible
     * batch recipients. Derives course/batch/coach context from the live class
     * itself, so both create (store) and edit (update) use identical logic and
     * the email always carries Course, Batch, Live Class, Date & Time, Coach.
     *
     * In-app (bell/push) is always delivered; the email channel fires only when
     * $sendEmail is true (and the student hasn't opted out). Never throws.
     *
     * @return int number of recipients notified
     */
    public function notifyScheduled(CourseLiveClass $liveClass, ?string $lessonTitle = null, bool $sendEmail = true): int
    {
        $courseId    = (int) ($liveClass->course_id ?: optional($liveClass->lesson)->course_id);
        $courseTitle = (string) (\App\Models\Course::where('id', $courseId)->value('title') ?? '');
        $batchName   = $liveClass->batch_id
            ? (string) (\App\Models\CourseBatch::where('id', $liveClass->batch_id)->value('title') ?? '')
            : '';
        $coachName   = (string) (User::where('id', \App\Models\Course::where('id', $courseId)->value('instructor_id'))->value('name') ?? '');

        $recipients = $this->recipients($liveClass);

        if ($recipients->isNotEmpty()) {
            try {
                \Illuminate\Support\Facades\Notification::send(
                    $recipients,
                    new \App\Notifications\LiveClassScheduledToStudent(
                        $liveClass, $courseTitle, $lessonTitle, $sendEmail, $batchName, $coachName
                    )
                );
            } catch (\Throwable $e) {
                \Log::warning('live-class notify failed: ' . $e->getMessage());
            }
        }

        \Log::info('live-class-notify', [
            'live_class_id' => $liveClass->id,
            'course_id'     => $courseId,
            'batch_id'      => $liveClass->batch_id,
            'recipients'    => $recipients->count(),
            'email'         => $sendEmail,
        ]);

        return $recipients->count();
    }

    /**
     * 2026-06-22 — notify the eligible BATCH students that a live class's start
     * time changed. Same batch-scoped recipient logic as notifyScheduled; the
     * RescheduledToStudent notification is coach-branded and gates email by the
     * student's 'live_class_scheduled' mail preference. Never throws.
     *
     * @return int number of recipients notified
     */
    public function notifyRescheduled(CourseLiveClass $liveClass, ?string $previousStartTime = null): int
    {
        $courseId    = (int) ($liveClass->course_id ?: optional($liveClass->lesson)->course_id);
        $courseTitle = (string) (\App\Models\Course::where('id', $courseId)->value('title') ?? '');
        $batchName   = $liveClass->batch_id
            ? (string) (\App\Models\CourseBatch::where('id', $liveClass->batch_id)->value('title') ?? '')
            : '';

        $recipients = $this->recipients($liveClass);

        if ($recipients->isNotEmpty()) {
            try {
                \Illuminate\Support\Facades\Notification::send(
                    $recipients,
                    new \App\Notifications\LiveClassRescheduledToStudent(
                        $liveClass, $courseTitle, $batchName, $previousStartTime
                    )
                );
            } catch (\Throwable $e) {
                \Log::warning('live-class reschedule notify failed: ' . $e->getMessage());
            }
        }

        \Log::info('live-class-reschedule-notify', [
            'live_class_id' => $liveClass->id,
            'course_id'     => $courseId,
            'batch_id'      => $liveClass->batch_id,
            'recipients'    => $recipients->count(),
        ]);

        return $recipients->count();
    }

    /**
     * 2026-06-22 — notify the eligible BATCH students that a live class was
     * CANCELLED. Must be called BEFORE the live class / lesson row is deleted
     * (recipients() reads course_id + batch_id off the row). Coach-branded.
     *
     * @return int number of recipients notified
     */
    public function notifyCancelled(CourseLiveClass $liveClass): int
    {
        $courseId    = (int) ($liveClass->course_id ?: optional($liveClass->lesson)->course_id);
        $courseTitle = (string) (\App\Models\Course::where('id', $courseId)->value('title') ?? '');
        $batchName   = $liveClass->batch_id
            ? (string) (\App\Models\CourseBatch::where('id', $liveClass->batch_id)->value('title') ?? '')
            : '';

        $recipients = $this->recipients($liveClass);

        if ($recipients->isNotEmpty()) {
            try {
                \Illuminate\Support\Facades\Notification::send(
                    $recipients,
                    new \App\Notifications\LiveClassCancelledToStudent($liveClass, $courseTitle, $batchName)
                );
            } catch (\Throwable $e) {
                \Log::warning('live-class cancel notify failed: ' . $e->getMessage());
            }
        }

        \Log::info('live-class-cancel-notify', [
            'live_class_id' => $liveClass->id,
            'course_id'     => $courseId,
            'batch_id'      => $liveClass->batch_id,
            'recipients'    => $recipients->count(),
        ]);

        return $recipients->count();
    }

    /**
     * Admin/coach visibility for the "class started" reminder (2026-06-30).
     * Per live class: total eligible students, how many joined, how many didn't,
     * and how many start-emails were sent vs failed. All tenant-scoped through
     * recipients() (course + batch) and the class's own ledger rows.
     *
     * @return array{total:int, joined:int, not_joined:int, emails_sent:int, emails_failed:int}
     */
    public function startNotificationStats(CourseLiveClass $liveClass): array
    {
        $total = $this->recipients($liveClass)->count();

        $joined = (int) \Illuminate\Support\Facades\DB::table('live_class_attendances')
            ->where('course_live_class_id', $liveClass->id)
            ->distinct()
            ->count('user_id');

        $sent = (int) \Illuminate\Support\Facades\DB::table('live_class_start_notifications')
            ->where('live_class_id', $liveClass->id)
            ->where('notification_type', 'class_started')
            ->where('email_status', 'sent')
            ->count();

        $failed = (int) \Illuminate\Support\Facades\DB::table('live_class_start_notifications')
            ->where('live_class_id', $liveClass->id)
            ->where('notification_type', 'class_started')
            ->where('email_status', 'failed')
            ->count();

        return [
            'total'         => $total,
            'joined'        => min($joined, $total),
            'not_joined'    => max(0, $total - $joined),
            'emails_sent'   => $sent,
            'emails_failed' => $failed,
        ];
    }
}
