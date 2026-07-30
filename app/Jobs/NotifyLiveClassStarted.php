<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\CourseLiveClass;
use App\Notifications\LiveClassStartedToStudent;
use App\Services\LiveClassNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queued fan-out for the "Live class started" notification (2026-06-30).
 *
 * Runs ASYNCHRONOUSLY so starting the class stays instant. Tenant-safe by
 * construction: recipients() scopes to the class's OWN course + batch + active
 * enrolled students, and the class's coach is its course owner — so a coach's
 * students can never receive another coach's class. Idempotent: students who
 * already JOINED (live_class_attendances) or were already NOTIFIED
 * (live_class_start_notifications) are excluded, and the per-student insert is
 * guarded by a unique index, so duplicate start events never double-send.
 */
class NotifyLiveClassStarted implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $liveClassId)
    {
    }

    public function handle(LiveClassNotificationService $recipientsService): void
    {
        $liveClass = CourseLiveClass::find($this->liveClassId);
        if (! $liveClass || $liveClass->cancelled_at !== null) {
            return;
        }

        $courseId    = (int) ($liveClass->course_id ?: optional($liveClass->lesson)->course_id);
        $coachId     = (int) (Course::where('id', $courseId)->value('instructor_id') ?? 0);
        $courseTitle = (string) (Course::where('id', $courseId)->value('title') ?? '');

        // All eligible students for THIS class (course + batch + has_access +
        // active + unbanned). Tenant scope is enforced here, not by us.
        $recipients = $recipientsService->recipients($liveClass);
        if ($recipients->isEmpty()) {
            return;
        }

        // Exclude students who have ALREADY JOINED this class (any attendance row).
        $joined = array_flip(array_map('intval', DB::table('live_class_attendances')
            ->where('course_live_class_id', $liveClass->id)
            ->pluck('user_id')->all()));

        // Exclude students already NOTIFIED for this class start (dedup).
        $notified = array_flip(array_map('intval', DB::table('live_class_start_notifications')
            ->where('live_class_id', $liveClass->id)
            ->where('notification_type', 'class_started')
            ->pluck('student_id')->all()));

        $toNotify = $recipients->reject(
            fn ($u) => isset($joined[(int) $u->id]) || isset($notified[(int) $u->id])
        );

        $sent = 0;
        $failed = 0;

        foreach ($toNotify as $student) {
            $status = 'sent';
            try {
                $student->notify(new LiveClassStartedToStudent($liveClass, $courseTitle, $coachId ?: null));
            } catch (\Throwable $e) {
                $status = 'failed';
                $failed++;
                Log::warning('LiveClassStarted notify failed', [
                    'live_class_id' => $liveClass->id, 'student_id' => $student->id, 'err' => $e->getMessage(),
                ]);
            }

            // Ledger row (also the duplicate guard via the unique index).
            DB::table('live_class_start_notifications')->insertOrIgnore([[
                'live_class_id'     => $liveClass->id,
                'student_id'        => $student->id,
                'coach_id'          => $coachId ?: null,
                'notification_type' => 'class_started',
                'email_status'      => $status,
                'sent_at'           => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]]);

            if ($status === 'sent') {
                $sent++;
            }
        }

        // Admin/coach observability — one summary line per class start.
        Log::info('live-class-started-notify', [
            'live_class_id'  => $liveClass->id,
            'course_id'      => $courseId,
            'coach_id'       => $coachId,
            'recipients'     => $recipients->count(),
            'already_joined' => count($joined),
            'sent'           => $sent,
            'failed'         => $failed,
        ]);
    }
}
