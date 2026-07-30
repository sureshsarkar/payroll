<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CourseLiveClass;
use App\Models\LiveClassAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Enrollment;

/**
 * Records student/instructor attendance on live classes. Called by the
 * launcher (resources/views/frontend/student-dashboard/live/zoom.blade.php)
 * when the Zoom Component View SDK fires `connection-change` events.
 *
 * Authorization:
 *   - The user must be the course instructor OR be enrolled with access.
 *     This mirrors the gate in ZoomSignatureController so a logged-in user
 *     can't manufacture attendance for a class they don't belong to.
 *
 * Role:
 *   - The launcher tells us join/leave; it does NOT tell us role. Role is
 *     decided server-side: caller.id == course.instructor_id → 'host',
 *     else 'attendee'. Same logic as ZoomSignatureController.
 *
 * Idempotency:
 *   - Multiple `join` posts (e.g. a flaky network reconnects) reuse the
 *     latest row that has `left_at IS NULL` instead of opening a fresh
 *     row, so we don't over-count attendance.
 *   - `leave` posts close the latest open row and write duration_seconds.
 *     A second `leave` with no open row is a no-op.
 */
class LiveClassAttendanceController extends Controller
{
    public function track(Request $request, int $live_class_id): JsonResponse
    {
        $user = userAuth();
        abort_unless($user, 401);

        // Audit 2026-05-18 Req 2 — demo-user 15-day cap.
        // is_demo=1 users may only join while demo_expires_at is in the
        // future. Hosts (coaches) are exempt — they're never demo students.
        if ((int) ($user->is_demo ?? 0) === 1) {
            $expiresAt = $user->demo_expires_at ?? null;
            if ($expiresAt && \Carbon\Carbon::parse($expiresAt)->isPast()) {
                return response()->json([
                    'ok'      => false,
                    'code'    => 'demo_expired',
                    'message' => __('Your 15-day demo period has ended. Please contact support to continue.'),
                ], 403);
            }
        }

        $request->validate([
            'event' => 'required|in:join,leave',
        ]);

        // Mirror ZoomSignatureController's authorisation shape — read
        // course.instructor_id rather than lesson.instructor_id. The lesson
        // copy can drift; the course row is the authoritative owner.
        $liveClass = CourseLiveClass::with(['lesson.course:id,instructor_id'])
            ->findOrFail($live_class_id);

        $course       = $liveClass->lesson?->course;
        $courseId     = $liveClass->course_id ?: $course?->id;
        $instructorId = (int) ($course?->instructor_id ?? 0);
        $isHost       = (int) $user->id === $instructorId && $instructorId > 0;

        // 2026-05-20 — assigned teachers are recognized as hosts for
        // their granted batches. Without this they'd be treated as
        // students by the attendance tracker, which fails the enrollment
        // check.
        $callerCoachId = ($user->role ?? null) === 'instructor'
            ? (int) $user->id
            : (int) ($user->coach_id ?? 0);

        if (! $isHost && $callerCoachId === $instructorId && $liveClass->batch_id) {
            $isAssignedTeacher = \App\Models\TeacherBatchAssignment::active()
                ->where('teacher_id', $user->id)
                ->where('batch_id', $liveClass->batch_id)
                ->exists();
            if ($isAssignedTeacher) {
                $isHost = true;
            }
        }

        if (!$isHost) {
            // 2026-05-20 — per-batch student gate. Same predicate as the
            // signature endpoint: NULL on either side is permissive,
            // both-set must match.
            $enrollments = Enrollment::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->where('has_access', 1)
                ->get(['batch_id']);
            abort_unless($enrollments->isNotEmpty(), 403, 'You are not enrolled in this course.');

            // 2026-06-06 — multi-batch: allow if course-wide, OR a null-batch
            // (legacy) enrollment, OR the class's batch is one the student is in.
            $liveBatchId = $liveClass->batch_id ? (int) $liveClass->batch_id : null;
            if ($liveBatchId !== null) {
                $enrBatchIds = $enrollments->pluck('batch_id')->map(fn ($b) => $b !== null ? (int) $b : null);
                if (! $enrBatchIds->contains(null) && ! $enrBatchIds->contains($liveBatchId)) {
                    abort(403, 'This live class is for a different batch than the one you are enrolled in.');
                }
            }
        }

        $event = $request->string('event')->toString();

        if ($event === 'join') {
            // 2026-06-22 — ONE COACH = ONE ACTIVE LIVE MEETING. When the HOST
            // goes live, claim the coach's single active-meeting slot. This is
            // the universal "go live" chokepoint (scheduled start, instant
            // meeting, direct join URL, extra tabs, other devices, raw API all
            // funnel here to register the host join + stamp coach_joined_at).
            // DB-level + race-safe (UNIQUE(coach_id) + row lock); tenant-scoped
            // by the OWNING coach (instructorId) so Coach A never blocks Coach B.
            if ($isHost) {
                try {
                    app(\App\Services\LiveMeetingGuard::class)->claim($instructorId, (int) $liveClass->id);
                } catch (\App\Exceptions\ActiveMeetingExistsException $e) {
                    return response()->json([
                        'ok'      => false,
                        'code'    => 'active_meeting_exists',
                        'message' => $e->getMessage(),
                    ], 409);
                }
            }

            // Audit 2026-05-18 Req 1 — single-login restriction.
            // Stamp the Laravel session id on the open row. A second join
            // from a different browser/device carries a different session
            // id; we refuse with 409 Conflict + a friendly message.
            //
            // Hosts (coaches) are exempt — they may legitimately host the
            // same class from multiple windows (e.g. dual-screen). The
            // restriction targets students.
            $currentSession = (string) $request->session()->getId();

            $row = LiveClassAttendance::where('course_live_class_id', $liveClass->id)
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->orderByDesc('id')
                ->first();

            if ($row && !$isHost) {
                $rowSession = (string) ($row->session_token ?? '');
                if ($rowSession !== '' && $rowSession !== $currentSession) {
                    // Different browser/device — refuse.
                    return response()->json([
                        'ok'      => false,
                        'code'    => 'already_joined_elsewhere',
                        'message' => __('You are already attending this class from another device or browser. Close the other session before joining here.'),
                    ], 409);
                }
                // Same session re-joining (e.g. tab reload) → reuse the open row.
            }

            if (!$row) {
                $row = LiveClassAttendance::create([
                    'course_live_class_id' => $liveClass->id,
                    'user_id'              => $user->id,
                    // 2026-06-01 (audit H7) — was 'attendee', but every
                    // counter + the verifier filter role='student'
                    // (LiveClassAttendanceVerifier, CourseBatch,
                    // BatchAttendanceService). So auto-tracked Zoom attendance
                    // was written but NEVER counted/verified. Write 'student'
                    // for non-hosts so it actually registers.
                    'role'                 => $isHost ? 'host' : 'student',
                    'joined_at'            => now(),
                    'client_ip'            => $request->ip(),
                    'user_agent'           => substr((string) $request->userAgent(), 0, 255),
                    'session_token'        => $currentSession,
                ]);
            } elseif ($row->session_token === null) {
                // Legacy row (pre-migration) — stamp the session id now.
                $row->session_token = $currentSession;
                $row->save();
            }

            // 2026-06-05 — Stamp the first HOST join. This is the signal the
            // student "wait for the coach" gate (ZoomSignatureController +
            // status endpoint) reads to flip students from "waiting" to
            // "allowed in". Sticky: set once, never cleared, so a brief coach
            // reconnect never locks students back out mid-class.
            if ($isHost && $liveClass->coach_joined_at === null) {
                $liveClass->forceFill(['coach_joined_at' => now()])->save();

                // LIVE CLASS STARTED (2026-06-30) — the host just joined for the
                // first time. Queue the "class started, join now" email to every
                // enrolled same-course+batch student who HASN'T joined yet. The
                // `coach_joined_at === null` guard makes this fire exactly once
                // per class; the job itself is tenant-safe + duplicate-safe. Queued
                // so starting the class stays instant.
                \App\Jobs\NotifyLiveClassStarted::dispatch((int) $liveClass->id);
            }

            return response()->json(['ok' => true, 'attendance_id' => $row->id]);
        }

        // leave
        $row = LiveClassAttendance::where('course_live_class_id', $liveClass->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return response()->json(['ok' => true, 'note' => 'no open session']);
        }

        $leftAt = now();
        // Cap at 24h — a missed leave (browser crash, network gone) shouldn't
        // attribute a multi-day session to one user. The instructor can
        // adjust manually if the row is genuinely longer.
        $duration = min(86400, max(0, $leftAt->diffInSeconds($row->joined_at)));

        $row->forceFill([
            'left_at'          => $leftAt,
            'duration_seconds' => $duration,
        ])->save();

        return response()->json(['ok' => true, 'duration_seconds' => $duration]);
    }
}
