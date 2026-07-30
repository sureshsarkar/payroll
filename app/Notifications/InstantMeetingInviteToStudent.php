<?php

namespace App\Notifications;

use App\Models\CoachDomain;
use App\Models\InstantMeeting;

/**
 * "Your coach is inviting you to a 1:1 meeting — Join now." Sent to the single
 * invited student the instant the coach starts the room. Student-facing →
 * coach-branded email + bell + real-time broadcast toast. The Join URL points at
 * the coach's own domain (white-label), never the platform host.
 */
class InstantMeetingInviteToStudent extends InAppNotification
{
    protected string $event         = 'instant_meeting_invite';
    protected string $emailTemplate = 'notif_instant_meeting_invite';
    protected string $emailCtaLabel = 'Join meeting';

    private string $coachName;
    private string $topic;

    public function __construct(InstantMeeting $meeting, ?int $coachId)
    {
        $this->coachId   = $coachId ?: (int) $meeting->coach_id ?: null;
        $this->coachName = (string) ($meeting->coach?->name ?? '');
        $this->topic     = (string) ($meeting->topic ?? '');

        $this->title     = __('Your coach is inviting you to a meeting');
        $this->body      = $this->coachName
            ? __(':coach wants to start a 1:1 session with you now. Tap to join.', ['coach' => $this->coachName])
            : __('Your coach wants to start a 1:1 session with you now. Tap to join.');
        $this->icon      = 'fa-video';
        $this->iconColor = '#16a34a';

        // Coach-domain join link (white-label), fallback to platform host.
        $host = $this->coachId ? CoachDomain::primaryHostFor((int) $this->coachId) : null;
        $base = $host ? ('https://' . $host) : rtrim((string) config('app.url'), '/');
        $this->url = $base . '/instant-meeting/' . $meeting->id . '/room';
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'    => $notifiable->name ?? '',
            'student_name' => $notifiable->name ?? '',
            'coach_name'   => $this->coachName,
            'topic'        => $this->topic,
            'join_url'     => $this->url,
        ];
    }
}
