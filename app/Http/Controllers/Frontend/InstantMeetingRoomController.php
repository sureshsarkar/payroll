<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\InstantMeeting;
use App\Models\User;
use App\Services\LiveMeetingGuard;
use App\Services\ZoomApiService;
use App\Services\ZoomSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Shared room surface for a 1:1 instant meeting — reachable by BOTH the coach
 * (host) and the invited student (attendee). Renders the Zoom launcher and backs
 * it with signature / status / attendance endpoints. Access is limited to the
 * two participants of the meeting; the coach's Zoom credentials host it.
 */
class InstantMeetingRoomController extends Controller
{
    public function __construct(
        private ZoomSignatureService $signatures,
        private ZoomApiService $zoom,
        private LiveMeetingGuard $guard
    ) {}

    /** The coach that OWNS the meeting id for the current user (self or staff). */
    private function callerCoachId(): int
    {
        $u = userAuth();
        if (! $u) {
            return 0;
        }
        return $u->role === 'instructor' ? (int) $u->id : (int) ($u->coach_id ?? 0);
    }

    /** Resolve role: 'host' (coach/staff) | 'student' | null (no access). */
    private function roleFor(InstantMeeting $meeting): ?string
    {
        $u = userAuth();
        if (! $u) {
            return null;
        }
        if ($this->callerCoachId() === (int) $meeting->coach_id) {
            return 'host';
        }
        if ((int) $u->id === (int) $meeting->student_id) {
            return 'student';
        }
        return null;
    }

    public function room(Request $request, $id)
    {
        $meeting = InstantMeeting::with(['coach:id,name', 'student:id,name'])->findOrFail($id);
        $role = $this->roleFor($meeting);
        abort_if($role === null, 403, __('You do not have access to this meeting.'));

        $brand = app(\App\Services\BrandResolver::class)->forCoach((int) $meeting->coach_id);

        return view('frontend.instant-meeting.room', [
            'meeting' => $meeting,
            'isHost'  => $role === 'host',
            'brand'   => $brand,
        ]);
    }

    public function status(Request $request, $id): JsonResponse
    {
        $meeting = InstantMeeting::findOrFail($id);
        $role = $this->roleFor($meeting);
        if ($role === null) {
            return response()->json(['ok' => false], 403);
        }

        $active   = $meeting->status === InstantMeeting::STATUS_ACTIVE;
        $canJoin  = $active && ($role === 'host' || $meeting->coach_joined_at !== null);
        $reason   = ! $active ? 'ended' : ($canJoin ? null : 'waiting_for_coach');

        return response()->json([
            'ok'           => true,
            'role'         => $role,
            'status'       => $meeting->status,
            'coach_joined' => $meeting->coach_joined_at !== null,
            'can_join'     => $canJoin,
            'reason'       => $reason,
            'server_now'   => now()->valueOf(),
        ], 200);
    }

    public function issue(Request $request, $id): JsonResponse
    {
        $meeting = InstantMeeting::findOrFail($id);
        $role = $this->roleFor($meeting);
        abort_if($role === null, 403, __('You do not have access to this meeting.'));

        if ($meeting->status !== InstantMeeting::STATUS_ACTIVE) {
            return response()->json(['message' => __('This meeting has ended.')], 403);
        }
        // The student waits until the coach is actually in the room.
        if ($role === 'student' && $meeting->coach_joined_at === null) {
            return response()->json(['reason' => 'waiting_for_coach', 'message' => __('Please wait — your coach has not joined yet.')], 425);
        }
        if (! $meeting->meeting_id) {
            return response()->json(['message' => __('The meeting is not ready yet.')], 425);
        }

        $coach = User::find($meeting->coach_id);
        $cred  = $coach?->zoom_credential;
        if (! $cred || ! $cred->sdk_key || ! $cred->sdk_secret) {
            return response()->json(['message' => __('Zoom is not configured for this coach.')], 503);
        }

        $isHost    = $role === 'host';
        $signature = $this->signatures->generate(
            (string) $cred->sdk_key,
            (string) $cred->sdk_secret,
            (string) $meeting->meeting_id,
            $isHost ? 1 : 0,
        );

        $user = userAuth();
        try {
            $leaveUrl = $isHost ? route('instructor.instant-meetings.index') : route('student.live-classes.index');
        } catch (\Throwable $e) {
            $leaveUrl = url('/');
        }

        return response()->json([
            'signature'     => $signature,
            'sdkKey'        => $cred->sdk_key,
            'meetingNumber' => (string) $meeting->meeting_id,
            'password'      => (string) ($meeting->password ?? ''),
            'role'          => $isHost ? 1 : 0,
            'userName'      => $user->name,
            'userEmail'     => $user->email,
            'leaveUrl'      => $leaveUrl,
            'zak'           => $isHost ? $this->fetchHostZak($cred) : null,
        ], 200);
    }

    public function track(Request $request, $id): JsonResponse
    {
        $meeting = InstantMeeting::findOrFail($id);
        $role = $this->roleFor($meeting);
        if ($role === null) {
            return response()->json(['ok' => false], 403);
        }

        $event = $request->input('event') === 'leave' ? 'leave' : 'join';

        if ($event === 'join') {
            if ($role === 'host') {
                // First host join opens the room for the student + refreshes the slot.
                if ($meeting->coach_joined_at === null) {
                    $meeting->forceFill(['coach_joined_at' => now()])->save();
                }
                try {
                    $this->guard->claimInstant((int) $meeting->coach_id, (int) $meeting->id);
                } catch (\Throwable $e) { /* already held by this meeting — fine */ }
            } elseif ($meeting->student_joined_at === null) {
                $meeting->forceFill(['student_joined_at' => now()])->save();
            }
        }

        return response()->json(['ok' => true], 200);
    }

    /**
     * Fetch a ZAK for the host so the Web SDK can start the meeting under the
     * coach's Zoom account (2026 OBF/ZAK rule). Best-effort — omit on failure.
     */
    private function fetchHostZak($cred): ?string
    {
        try {
            $token = $this->zoom->ensureFreshAccessToken($cred);
            if (! $token) {
                return null;
            }
            $r = Http::withToken($token)->timeout(12)->get('https://api.zoom.us/v2/users/me/token', ['type' => 'zak']);
            return $r->successful() ? ($r->json()['token'] ?? null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
