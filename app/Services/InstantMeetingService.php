<?php

namespace App\Services;

use App\Exceptions\ActiveMeetingExistsException;
use App\Models\InstantMeeting;
use App\Models\User;
use App\Notifications\InstantMeetingInviteToStudent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates a 1:1 instant Zoom meeting between a coach and one student. Reuses the
 * platform's Zoom engine (per-coach credential + S2S token) and the shared
 * one-active-meeting mutex, but stays entirely separate from the batch/group
 * live-class pipeline (no course, lesson, batch or enrollment involved).
 */
class InstantMeetingService
{
    public function __construct(
        private ZoomApiService $zoom,
        private LiveMeetingGuard $guard
    ) {}

    /**
     * Start a 1:1 meeting. Returns a result array for the controller:
     *   ['ok'=>true, 'meeting'=>InstantMeeting]  OR  ['ok'=>false, 'code'=>..., 'message'=>...]
     */
    public function start(int $coachId, int $studentId, array $opts = []): array
    {
        $coach   = User::find($coachId);
        $student = User::where('id', $studentId)->where('role', 'student')->first();
        if (! $coach || ! $student) {
            return ['ok' => false, 'code' => 422, 'message' => __('Student not found.')];
        }

        $cred = $coach->zoom_credential;
        if (! $cred || ! $cred->account_id || ! $cred->client_id || ! $cred->client_secret || ! $cred->sdk_key) {
            return ['ok' => false, 'code' => 400, 'message' => __('Please connect your Zoom account in Zoom Settings before starting a meeting.')];
        }

        $duration = (int) ($opts['duration'] ?? 30);
        $duration = max(5, min(240, $duration));
        $purpose  = in_array($opts['purpose'] ?? '', ['consultation', 'doubt', 'training', 'general'], true) ? $opts['purpose'] : 'general';
        $topic    = trim((string) ($opts['topic'] ?? '')) ?: (__('1:1 with') . ' ' . $student->name);

        // Serialise per-coach so two "Start" clicks can't both pass the mutex.
        return $this->guard->withCoachLock($coachId, function () use ($coach, $coachId, $student, $duration, $purpose, $topic, $cred, $opts) {

            $meeting = InstantMeeting::create([
                'coach_id'                  => $coachId,
                'student_id'                => $student->id,
                'topic'                     => mb_substr($topic, 0, 190),
                'purpose'                   => $purpose,
                'status'                    => InstantMeeting::STATUS_ACTIVE,
                'expected_duration_minutes' => $duration,
                'started_at'                => now(),
                'expires_at'                => now()->addMinutes(LiveMeetingGuard::DEFAULT_TTL_MINUTES),
                'created_ip'                => $opts['ip'] ?? null,
            ]);

            // Claim the shared one-active-meeting slot BEFORE hitting Zoom.
            try {
                $this->guard->claimInstant($coachId, $meeting->id);
            } catch (ActiveMeetingExistsException $e) {
                $meeting->delete();
                return ['ok' => false, 'code' => 409, 'message' => $e->getMessage()];
            }

            // Create the Zoom meeting on the coach's own account.
            $accessToken = $this->zoom->ensureFreshAccessToken($cred);
            if (! $accessToken) {
                $this->guard->releaseInstant($coachId, $meeting->id);
                $meeting->update(['status' => InstantMeeting::STATUS_CANCELLED, 'ended_at' => now()]);
                return ['ok' => false, 'code' => 400, 'message' => __('Could not authenticate with Zoom. Please re-check your Zoom Settings.')];
            }

            try {
                $response = Http::withToken($accessToken)->timeout(20)->post(
                    'https://api.zoom.us/v2/users/me/meetings',
                    $this->meetingPayload($topic, $duration)
                );
                $body = $response->json();
            } catch (\Throwable $e) {
                Log::error('instant-meeting-zoom-fail: ' . $e->getMessage(), ['coach_id' => $coachId]);
                $body = null;
            }

            $meetingId = $body['id'] ?? null;
            if (! $meetingId) {
                Log::warning('instant-meeting-zoom-rejected', ['coach_id' => $coachId, 'response' => $body]);
                $this->guard->releaseInstant($coachId, $meeting->id);
                $meeting->update(['status' => InstantMeeting::STATUS_CANCELLED, 'ended_at' => now()]);
                return ['ok' => false, 'code' => 502, 'message' => __('Zoom rejected the meeting. Please verify your Zoom credentials and try again.')];
            }

            $meeting->update([
                'meeting_id' => (string) $meetingId,
                'join_url'   => $body['join_url'] ?? null,
                'password'   => $body['password'] ?? null,
            ]);

            // Invite the student (bell + real-time toast + coach-branded email).
            try {
                $student->notify(new InstantMeetingInviteToStudent($meeting->fresh(), $coachId));
            } catch (\Throwable $e) {
                Log::warning('instant-meeting-notify-fail: ' . $e->getMessage(), ['meeting_id' => $meeting->id]);
            }

            return ['ok' => true, 'meeting' => $meeting->fresh()];
        });
    }

    /** End a meeting (coach action) and free the shared slot. Idempotent. */
    public function end(InstantMeeting $meeting): void
    {
        if ($meeting->status === InstantMeeting::STATUS_ACTIVE) {
            $meeting->update(['status' => InstantMeeting::STATUS_ENDED, 'ended_at' => now()]);
        }
        $this->guard->releaseInstant((int) $meeting->coach_id, (int) $meeting->id);
    }

    /**
     * The Zoom REST create payload. Mirrors the batch live-class settings
     * (no passcode/waiting-room, join-before-host) so the launcher behaves
     * identically. `type: 2` + start=now is the proven shape already in use.
     */
    private function meetingPayload(string $topic, int $durationMinutes): array
    {
        return [
            'topic'      => $topic,
            'type'       => 2,
            'start_time' => now()->toIso8601String(),
            'duration'   => $durationMinutes,
            'timezone'   => 'Asia/Kolkata',
            'password'   => '',
            'settings'   => [
                'password'                       => false,
                'meeting_authentication'         => false,
                'waiting_room'                   => false,
                'approval_type'                  => 2,
                'join_before_host'               => true,
                'jbh_time'                       => 0,
                'host_video'                     => true,
                'participant_video'              => true,
                'mute_upon_entry'                => false,
                'audio'                          => 'both',
                'registrants_email_notification' => false,
                'registration_type'              => 1,
                'auto_recording'                 => 'none',
            ],
        ];
    }
}
