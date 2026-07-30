<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\NewBatchAnnouncement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\Order\app\Models\Enrollment;

/**
 * Audit 2026-05-18 — resolves the audience for an announcement and
 * dispatches NewBatchAnnouncement to each enrolled student.
 *
 * Audience rule (mirrors Announcement::scopeVisibleToStudent):
 *   - Announcement has batch_id   → only students enrolled in that
 *                                   batch (enrollments.batch_id match),
 *                                   regardless of has_access.
 *   - Announcement has NO batch_id → every student enrolled in that
 *                                    course, regardless of has_access.
 * 2026-06-02 — a fee-pending member (has_access=0) is still a batch member
 * and must receive announcements (e.g. "fees due Friday"). has_access gates
 * course CONTENT, not announcement delivery.
 *
 * Safety:
 *   - Chunked in batches of 200 to avoid memory blowups on large rosters.
 *   - Uses Notification::send(Collection) so a single failing recipient
 *     does not abort the whole fanout.
 *   - status='inactive' announcements are NOT dispatched (idempotency:
 *     if a coach later toggles status to inactive in admin, no extra
 *     dispatch happens — and the bell already hides inactive rows via
 *     the scope on the read path).
 */
class AnnouncementNotifier
{
    public function fanOut(Announcement $announcement): int
    {
        if (($announcement->status ?? 'active') !== 'active') {
            return 0;
        }

        // 2026-06-01 (audit [10]) — platform-wide announcements target EVERY
        // student, not an enrollment subset. Previously fanOut always keyed on
        // course_id; for an all_students row course_id is NULL, so the
        // enrollment query below matched nobody and a scheduled dispatch
        // reached 0 recipients (push + email silently dropped) even though the
        // bell still showed it via scopeVisibleToStudent PATH 1. Route
        // all_students through a users(role=student) fanout that mirrors that
        // read-path scope.
        if (($announcement->audience_type ?? null) === 'all_students') {
            return $this->fanOutToAllStudents($announcement);
        }

        $sent = 0;
        $coachName = (string) (optional($announcement->instructor()->first())->name ?? '');
        $courseTitle = (string) (optional($announcement->course()->first())->title ?? '');

        // Audit 2026-05-18 — multi-batch awareness.
        //   - If pivot has any rows → fan out to ALL students whose
        //     enrolment batch is in the pivot list.
        //   - If pivot is empty AND batch_id is set (legacy single)
        //     → fan out to that one batch.
        //   - Else → course-wide.
        $batchIds = $announcement->batches()->pluck('course_batches.id')->all();
        if (empty($batchIds) && $announcement->batch_id) {
            $batchIds = [(int) $announcement->batch_id];
        }

        $batchTitle = null; $batchStart = null; $batchEnd = null;
        if (!empty($batchIds)) {
            $batches = \App\Models\CourseBatch::whereIn('id', $batchIds)->get(['id', 'title', 'start_time', 'end_time']);
            if ($batches->count() === 1) {
                $batchTitle = $batches[0]->title;
                // 2026-06-16 — carry the batch times into the announcement email.
                $batchStart = $batches[0]->start_time;
                $batchEnd   = $batches[0]->end_time;
            } else {
                $batchTitle = $batches->count() . ' ' . __('batches');
            }
        }

        // 2026-06-02 — no has_access=1 filter: notify every course/batch member,
        // including fee-pending (has_access=0) students. Matches the read-path
        // scope (Announcement::scopeVisibleToStudent) and the fee fix.
        $query = Enrollment::where('course_id', $announcement->course_id);

        if (!empty($batchIds)) {
            // 2026-06-03 — a batch-specific announcement also reaches course
            // members who have NO batch assigned (batch_id NULL). Most students
            // are batch-less (the widespread NULL batch_id), so a strict
            // whereIn(batch) reached almost nobody — they got no mail + nothing
            // in their panel. Batch-less = "unassigned course member", so they
            // receive the course's announcements; students pinned to a DIFFERENT
            // batch are still excluded (isolation preserved).
            $query->where(function ($q) use ($batchIds) {
                $q->whereIn('batch_id', $batchIds)->orWhereNull('batch_id');
            });
        }

        $query->chunkById(200, function ($enrollments) use ($announcement, $courseTitle, $batchTitle, $coachName, $batchStart, $batchEnd, &$sent) {
            $userIds = $enrollments->pluck('user_id')->unique()->all();
            if (empty($userIds)) return;

            $users = User::whereIn('id', $userIds)->get();
            if ($users->isEmpty()) return;

            try {
                Notification::send($users, new NewBatchAnnouncement(
                    $announcement, $courseTitle, $batchTitle, $coachName, $batchStart, $batchEnd
                ));
                $sent += $users->count();
            } catch (\Throwable $e) {
                Log::warning('Announcement fanout chunk failed', [
                    'announcement_id' => $announcement->id,
                    'user_count'      => $users->count(),
                    'error'           => $e->getMessage(),
                ]);
            }
        });

        Log::info('Announcement fanout complete', [
            'announcement_id' => $announcement->id,
            'recipients'      => $sent,
        ]);

        return $sent;
    }

    /**
     * 2026-06-01 (audit [10]) — fan a platform-wide (audience_type=
     * all_students) announcement out to every student user. Mirrors the
     * read-path scopeVisibleToStudent PATH 1, which shows all_students rows
     * to every student regardless of enrollment. Chunked by 200 and
     * per-chunk try/catch so one bad recipient never aborts the broadcast.
     */
    private function fanOutToAllStudents(Announcement $announcement): int
    {
        $sent       = 0;
        $coachName  = (string) (optional($announcement->instructor()->first())->name ?? '');
        $scopeLabel = __('All students'); // shown as the notification scope (no single course/batch)

        User::where('role', 'student')
            ->chunkById(200, function ($users) use ($announcement, $scopeLabel, $coachName, &$sent) {
                if ($users->isEmpty()) {
                    return;
                }
                try {
                    Notification::send($users, new NewBatchAnnouncement(
                        $announcement, $scopeLabel, null, $coachName
                    ));
                    $sent += $users->count();
                } catch (\Throwable $e) {
                    Log::warning('All-students announcement fanout chunk failed', [
                        'announcement_id' => $announcement->id,
                        'user_count'      => $users->count(),
                        'error'           => $e->getMessage(),
                    ]);
                }
            });

        Log::info('All-students announcement fanout complete', [
            'announcement_id' => $announcement->id,
            'recipients'      => $sent,
        ]);

        return $sent;
    }
}
