<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CourseChapterLesson;
use App\Models\ZoomCredential;
use App\Services\ZoomApiService;
use App\Services\ZoomSignatureService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Order\app\Models\Enrollment;

/**
 * Issues short-lived Zoom Meeting SDK signatures so the browser never sees
 * the instructor's `client_secret`.
 *
 * Authorisation model:
 *   - Caller must be authenticated.
 *   - Caller must be the course instructor (gets host role) OR enrolled
 *     in the course with has_access=1 (gets attendee role).
 *   - The signature itself is the LMS-side credential — Zoom verifies it
 *     server-side. Access control lives in THIS endpoint, not in the
 *     meeting passcode.
 *
 * Passcode handling (2026-05-11 revision):
 *   - Zoom's "Require one security option" account policy is locked-on
 *     for Basic/free accounts. Even when we request `password: false` on
 *     create, Zoom auto-assigns a passcode. Fighting that is a losing
 *     game, so the controller forwards the passcode to client.join() via
 *     the signature-endpoint XHR response. The value travels server →
 *     XHR → in-memory JS and is never rendered into HTML source — same
 *     transport as the SDK signature itself.
 *   - LiveClassController + ZoomRecreateMeetings persist the value from
 *     Zoom's create response into `course_live_classes.password`.
 */
class ZoomSignatureController extends Controller
{
    public function __construct(
        private readonly ZoomSignatureService $signatures,
        private readonly ZoomApiService $api,
    ) {
    }

    public function issue(Request $request, string $lesson_id): JsonResponse
    {
        $user = userAuth();
        abort_unless($user, 401);

        // ZAK fetch (host only) needs account_id + client_id + client_secret +
        // zoom_access_token + zoom_token_expires_at, on top of the SDK pair
        // used for signing. account_id / client_secret / zoom_access_token are
        // encrypted-cast in the model so they need to be SELECTed here.
        //
        // 2026-05-20 — live.batch_id pulled in so the per-batch student
        // gate can match it against the student's enrollment.batch_id.
        $lesson = CourseChapterLesson::with([
            'course:id,instructor_id,slug,title',
            'course.instructor:id',
            'course.instructor.zoom_credential:id,instructor_id,account_id,client_id,client_secret,sdk_key,sdk_secret,zoom_access_token,zoom_token_expires_at',
            'live:id,lesson_id,type,meeting_id,password,batch_id,start_time,ended_at,expected_duration_minutes,coach_joined_at,cancelled_at',
        ])->findOrFail($lesson_id);

        if (!$lesson->live || $lesson->live->type !== 'zoom') {
            abort(404, 'No Zoom session for this lesson.');
        }

        $course     = $lesson->course;
        $instructor = $course?->instructor;
        $cred       = $instructor?->zoom_credential;

        // SDK Key + Secret come from a Meeting SDK app in Zoom Marketplace.
        // These are DIFFERENT from the S2S OAuth Client ID + Secret used by
        // ZoomApiService for REST calls — the Meeting SDK signature must be
        // signed with SDK Secret, otherwise Zoom returns "Signature is invalid".
        if (!$course || !$instructor || !$cred || !$cred->sdk_key || !$cred->sdk_secret) {
            abort(503, 'Zoom Meeting SDK credentials not configured. Add SDK Key + SDK Secret in /instructor/zoom-setting.');
        }

        // 2026-05-20 — Host role accepted from either:
        //   (a) the course's owning coach (course.instructor_id matches), OR
        //   (b) a CoachStaff teacher whose coach_id matches the course
        //       owner AND who has an active assignment for the live
        //       class's batch.
        $courseInstructorId = (int) $course->instructor_id;
        $callerCoachId      = ($user->role ?? null) === 'instructor'
            ? (int) $user->id
            : (int) ($user->coach_id ?? 0);

        $isHost = (int) $user->id === $courseInstructorId;

        if (! $isHost && $callerCoachId === $courseInstructorId && $lesson->live->batch_id) {
            $isAssignedTeacher = \App\Models\TeacherBatchAssignment::active()
                ->where('teacher_id', $user->id)
                ->where('batch_id', $lesson->live->batch_id)
                ->exists();
            if ($isAssignedTeacher) {
                $isHost = true;
            }
        }

        // 2026-06-03 (#7/#9) — a finished live class must NOT be joinable, by
        // anyone. This is the access choke point: no signature → the SDK can't
        // enter the meeting. The coach (host) is blocked only once the class is
        // DEFINITIVELY finished (ended_at set, or its batch has ended) so a
        // class running past its scheduled slot never kicks the host out.
        // Students are held to the stricter window-based "over" as well (see
        // CourseLiveClass::isOver / isFinished).
        if ($isHost) {
            abort_if($lesson->live->isFinished(), 403, 'This live class has ended.');
        } else {
            abort_if($lesson->live->isOver(), 403, 'This live class has ended.');
        }

        if (!$isHost) {
            // 2026-05-20 — Student gate is now per-batch. A student
            // joining must be enrolled in the course AND either have a
            // NULL batch (legacy enrollment, preserve old behavior) or
            // be in the same batch as the live class. Course-wide
            // classes (live.batch_id NULL) remain open to all enrolled
            // students.
            // 2026-06-06 — a student may now be in MULTIPLE batches of the same
            // course, so gate against the SET of their enrolled batches.
            $enrollments = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('has_access', 1)
                ->get(['batch_id']);

            abort_unless($enrollments->isNotEmpty(), 403, 'You are not enrolled in this course.');

            // Allow when the class is course-wide (null batch), OR the student
            // has a legacy null-batch enrollment (sees all), OR the class's batch
            // is one of the student's enrolled batches.
            $liveBatchId = $lesson->live->batch_id ? (int) $lesson->live->batch_id : null;
            if ($liveBatchId !== null) {
                $enrBatchIds = $enrollments->pluck('batch_id')
                    ->map(fn ($b) => $b !== null ? (int) $b : null);
                if (! $enrBatchIds->contains(null) && ! $enrBatchIds->contains($liveBatchId)) {
                    abort(403, 'This live class is for a different batch than the one you are enrolled in.');
                }
            }

            // 2026-06-02 (enterprise H-B) — ONE live class at a time per
            // student. If this student has an OPEN (un-left) attendance in a
            // DIFFERENT live class whose scheduled window is still active, deny
            // issuing a signature for this one — this is the access-grant choke
            // point, so a shared account can't drive two meetings at once.
            // Stale open rows from past classes are ignored (their window has
            // elapsed), so a legitimate student is never falsely blocked.
            $openElsewhere = \App\Models\LiveClassAttendance::with('liveClass.lesson:id,duration')
                ->where('user_id', $user->id)
                ->where('role', 'student')
                ->whereNull('left_at')
                ->where('course_live_class_id', '!=', $lesson->live->id)
                ->get();
            foreach ($openElsewhere as $att) {
                $other = $att->liveClass;
                if (! $other || ! $other->start_time) {
                    continue;
                }
                $start  = \Illuminate\Support\Carbon::parse($other->start_time);
                $durMin = (int) ($other->lesson?->duration ?: 60);
                if (now()->between($start, $start->copy()->addMinutes($durMin))) {
                    abort(409, 'You are already in another live class. Please leave it before joining this one.');
                }
            }

            // Audit 2026-05-18 Req 2 — demo-user expiry gate. We refuse to
            // issue a Zoom signature past the 15-day demo window so the
            // SDK never gets a chance to join the meeting.
            if ((int) ($user->is_demo ?? 0) === 1) {
                $expiresAt = $user->demo_expires_at ?? null;
                if ($expiresAt && \Carbon\Carbon::parse($expiresAt)->isPast()) {
                    abort(403, 'Your 15-day demo period has ended. Please contact support to continue.');
                }
            }
        }

        // 2026-06-05 — Timing / coach-presence gate (the core of the "wait for
        // the coach" requirement). Enforced HERE at the signature choke point —
        // not just in the UI — so it cannot be bypassed:
        //   - HOST may start only within the early-join buffer (or later); too
        //     early → countdown.
        //   - STUDENT enters only once the scheduled start has passed AND the
        //     coach has joined; otherwise countdown / "waiting for your coach".
        // Returns 425 (Too Early) with a structured payload so the launcher
        // renders the countdown / waiting screen instead of a hard error.
        if ($isHost) {
            if (! $lesson->live->hostEarlyJoinOpen()) {
                return $this->notYetJoinableResponse($lesson->live, 'waiting_for_start', true);
            }
        } else {
            if (! $lesson->live->hasStarted()) {
                return $this->notYetJoinableResponse($lesson->live, 'waiting_for_start', false);
            }
            if (! $lesson->live->isCoachJoined()) {
                return $this->notYetJoinableResponse($lesson->live, 'waiting_for_coach', false);
            }
        }

        $role      = $isHost ? 1 : 0;
        // Audit 2026-05-18 Req 4 — both roles return to their own
        // live-classes index, not the course learning page.
        $leaveUrl  = $isHost
            ? route('instructor.live-classes.index')
            : route('student.live-classes.index');

        // 2026-06-05 — Concurrency-aware host-slot management. Zoom permits up
        // to TWO concurrent meetings per host WHEN the account has "Allow host
        // to start concurrent meetings" enabled. We therefore must NOT blanket-
        // end the host's other live meetings (the old behaviour) — that would
        // kick students out of a genuinely-running parallel class. Instead we
        // reclaim ONLY the slots held by STUCK/ABANDONED meetings: the live
        // class is over (finished / past its window) OR nobody is currently in
        // it. A class with students actively present is left running, so the
        // coach can host concurrent live classes. endMeeting() is idempotent (a
        // not-live meeting returns 400, treated as success). Best-effort: a
        // failure here must never block issuing the signature.
        if ($isHost) {
            try {
                $zoom = app(\App\Services\ZoomApiService::class);
                $currentMeetingId = (string) $lesson->live->meeting_id;

                $others = \App\Models\CourseLiveClass::query()
                    ->whereIn('course_id', \App\Models\Course::where('instructor_id', $courseInstructorId)->pluck('id'))
                    ->whereNotNull('meeting_id')
                    ->where('meeting_id', '!=', $currentMeetingId)
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get();

                foreach ($others as $other) {
                    // Active concurrent class (someone in it, inside its window)
                    // → leave running. Only end stuck/abandoned meetings.
                    if (! $other->isAbandonedForSlotReuse()) {
                        continue;
                    }
                    $zoom->endMeeting($cred, (string) $other->meeting_id);
                }
            } catch (\Throwable $e) {
                \Log::warning('Zoom host-slot cleanup failed: ' . $e->getMessage());
            }
        }

        $signature = $this->signatures->generate(
            sdkKey:        $cred->sdk_key,
            sdkSecret:     $cred->sdk_secret,
            meetingNumber: (string) $lesson->live->meeting_id,
            role:          $role,
        );

        // ZAK token — required by Zoom for cross-account host joins as of
        // their March-2026 OBF/ZAK/RTMS rule. We fetch it for hosts only;
        // attendees joining a meeting in the SDK app's own account don't
        // need one. If the fetch fails (S2S creds missing, token endpoint
        // down, scope not granted) we still return a usable signature —
        // Zoom will simply reject host privileges with an SDK error which
        // surfaces in the launcher console, instead of silently breaking
        // the whole join.
        $zak = $isHost ? $this->fetchHostZak($cred) : null;

        // Forward the meeting passcode to the launcher. The value comes
        // from Zoom's create-meeting response (LiveClassController +
        // ZoomRecreateMeetings persist it onto course_live_classes.password)
        // and is non-empty whenever Zoom's "Require one security option"
        // account policy is locked-on (Basic/free plans). The launcher
        // hands it to client.join() so Zoom's passcode handshake clears
        // without ever rendering the value into HTML source.
        $password = (string) ($lesson->live->password ?? '');

        return response()->json([
            'signature'     => $signature,
            'sdkKey'        => $cred->sdk_key,
            'meetingNumber' => (string) $lesson->live->meeting_id,
            'password'      => $password,
            'role'          => $role,
            'userName'      => $user->name,
            'userEmail'     => $user->email,
            'leaveUrl'      => $leaveUrl,
            'zak'           => $zak,
        ]);
    }

    /**
     * 2026-06-05 — Live-class status for the pre-join countdown / waiting screen.
     * The launcher polls this (every few seconds) BEFORE attempting to fetch a
     * signature, so students see a countdown until start_time and then a
     * "waiting for your coach" screen until the coach joins — at which point
     * can_join flips true and the launcher proceeds to join.
     *
     * Applies the SAME hard authorisation as issue() (enrollment / batch /
     * ownership) so it never leaks status to users who can't access the class.
     * It does NOT abort for timing — that is reported via status/can_join.
     *
     * All times are returned as epoch MILLISECONDS plus server_now, so the
     * countdown is driven by SERVER time (browser-clock skew is neutralised).
     */
    public function status(Request $request, string $lesson_id): JsonResponse
    {
        $user = userAuth();
        abort_unless($user, 401);

        // F30 (audit 2026-06-26) — mirror issue(): an expired demo account must
        // not even poll live-class status (metadata leak). is_demo is a student
        // flag, so this never blocks a real coach/host.
        if ((int) ($user->is_demo ?? 0) === 1) {
            $expiresAt = $user->demo_expires_at ?? null;
            if ($expiresAt && \Carbon\Carbon::parse($expiresAt)->isPast()) {
                abort(403, 'Your 15-day demo period has ended. Please contact support to continue.');
            }
        }

        $lesson = CourseChapterLesson::with([
            'course:id,instructor_id,slug,title',
            'course.instructor:id,name',
            'live',
        ])->findOrFail($lesson_id);

        if (!$lesson->live || $lesson->live->type !== 'zoom') {
            abort(404, 'No Zoom session for this lesson.');
        }

        $course     = $lesson->course;
        $instructor = $course?->instructor;
        abort_unless($course && $instructor, 404, 'This live session no longer exists.');

        // Host detection — mirrors issue() exactly (owning coach OR assigned
        // teacher with an active assignment for this class's batch).
        $courseInstructorId = (int) $course->instructor_id;
        $callerCoachId      = ($user->role ?? null) === 'instructor'
            ? (int) $user->id
            : (int) ($user->coach_id ?? 0);

        $isHost = (int) $user->id === $courseInstructorId;
        if (! $isHost && $callerCoachId === $courseInstructorId && $lesson->live->batch_id) {
            $isHost = \App\Models\TeacherBatchAssignment::active()
                ->where('teacher_id', $user->id)
                ->where('batch_id', $lesson->live->batch_id)
                ->exists();
        }

        // Students must be enrolled + batch-matched even to read status — no
        // info leak about another coach's / batch's class.
        if (! $isHost) {
            $enrollments = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('has_access', 1)
                ->get(['batch_id']);

            abort_unless($enrollments->isNotEmpty(), 403, 'You are not enrolled in this course.');

            // 2026-06-06 — multi-batch: allow if course-wide, OR a null-batch
            // (legacy) enrollment, OR the class's batch is one the student is in.
            $liveBatchId = $lesson->live->batch_id ? (int) $lesson->live->batch_id : null;
            if ($liveBatchId !== null) {
                $enrBatchIds = $enrollments->pluck('batch_id')->map(fn ($b) => $b !== null ? (int) $b : null);
                if (! $enrBatchIds->contains(null) && ! $enrBatchIds->contains($liveBatchId)) {
                    abort(403, 'This live class is for a different batch than the one you are enrolled in.');
                }
            }
        }

        $live        = $lesson->live;
        $status      = $live->lifecycleStatus();
        $coachJoined = $live->isCoachJoined();

        if ($isHost) {
            $canJoin = ! $live->isFinished() && $live->hostEarlyJoinOpen();
        } else {
            $canJoin = ! $live->isOver() && $live->hasStarted() && $coachJoined;
        }

        return response()->json([
            'ok'               => true,
            'role'             => $isHost ? 'host' : 'student',
            'status'           => $status,
            'coach_joined'     => $coachJoined,
            'can_join'         => $canJoin,
            'server_now'       => (int) now()->getTimestampMs(),
            'scheduled_start'  => $live->start_time ? (int) $live->start_time->getTimestampMs() : null,
            'early_buffer_min' => \App\Models\CourseLiveClass::EARLY_JOIN_BUFFER_MINUTES,
            'title'            => (string) ($lesson->title ?: $course->title),
            'coach_name'       => (string) ($instructor->name ?? ''),
            'message'          => $this->statusMessage($status, $isHost, $coachJoined),
        ]);
    }

    /**
     * Structured 425 (Too Early) payload returned by issue() when the caller is
     * authorised but it is not yet time to join — the launcher renders the
     * countdown / waiting screen from this rather than showing an error.
     */
    private function notYetJoinableResponse(\App\Models\CourseLiveClass $live, string $reason, bool $isHost): JsonResponse
    {
        return response()->json([
            'ok'              => false,
            'code'            => 'not_yet_joinable',
            'reason'          => $reason, // waiting_for_start | waiting_for_coach
            'status'          => $live->lifecycleStatus(),
            'coach_joined'    => $live->isCoachJoined(),
            'server_now'      => (int) now()->getTimestampMs(),
            'scheduled_start' => $live->start_time ? (int) $live->start_time->getTimestampMs() : null,
            'message'         => $reason === 'waiting_for_coach'
                ? __('Please wait. Your coach has not joined the live class yet.')
                : __('This live class has not started yet.'),
        ], 425);
    }

    /**
     * Human-friendly status line for the waiting/countdown screen.
     */
    private function statusMessage(string $status, bool $isHost, bool $coachJoined): string
    {
        return match ($status) {
            'cancelled'         => __('This live class was cancelled.'),
            'completed'         => __('This live class has ended.'),
            'live'              => __('Your live class is in progress.'),
            'ready_to_start'    => $isHost
                ? __('You can start the live class now.')
                : __('Please wait. Your coach has not joined the live class yet.'),
            'waiting_for_start' => $isHost
                ? __('The live class will be ready to start soon.')
                : __('Your live class has not started yet. Please wait for the countdown.'),
            default             => __('This live class is being prepared. Please wait or contact support.'),
        };
    }

    /**
     * Fetch the host's ZAK token from Zoom. Required for cross-account host
     * joins under Zoom's 2026-03-02 enforcement of OBF / ZAK / RTMS, and
     * harmless to include in same-account joins.
     *
     * S2S OAuth scope required: `user:read:zak:admin` (granted by enabling
     * the "View user's ZAK token" scope on the Zoom Marketplace app).
     *
     * Returns null on any failure — the caller treats that as "no host
     * authorisation token available" and lets the SDK report the
     * appropriate error to the user, rather than 500ing the signature
     * endpoint and breaking joins for everybody.
     */
    private function fetchHostZak(ZoomCredential $cred): ?string
    {
        $accessToken = $this->api->ensureFreshAccessToken($cred);
        if (!$accessToken) {
            // S2S credentials incomplete or Zoom rejected token mint.
            // Already logged inside ensureFreshAccessToken on the failure
            // path; nothing more to say here.
            return null;
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(5)
                ->get('https://api.zoom.us/v2/users/me/token', ['type' => 'zak']);
        } catch (ConnectionException $e) {
            Log::warning('ZAK fetch network error', ['msg' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('ZAK fetch threw', ['msg' => $e->getMessage()]);
            return null;
        }

        if (!$response->successful()) {
            Log::info('ZAK fetch non-2xx', [
                'status' => $response->status(),
                // Truncate the body — Zoom error pages can be long.
                'body'   => substr((string) $response->body(), 0, 240),
            ]);
            return null;
        }

        $token = $response->json('token');
        return is_string($token) && $token !== '' ? $token : null;
    }
}
