<?php

namespace App\Notifications;

use App\Models\CourseLiveClass;

/**
 * Sent by `php artisan live-class:verify` when an upcoming live class
 * (starting within ~30 minutes) points at a Zoom meeting that no longer
 * exists on Zoom's servers — typically because Zoom auto-deleted it after
 * 30 days of inactivity, or the host removed it manually.
 *
 * Goal: give the instructor enough lead time to recreate the meeting
 * BEFORE students show up and hit a stuck "Joining Meeting…".
 *
 * Like ZoomReconnectRequired, this only fires once per row — the verify
 * command stores `verification_status='missing'` after sending and won't
 * re-notify on the next probe.
 */
class ZoomMeetingMissingForUpcomingClass extends InAppNotification
{
    protected string $emailCtaLabel = 'Recreate live class';

    private CourseLiveClass $liveClass;

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(CourseLiveClass $liveClass)
    {
        $this->liveClass = $liveClass;

        $this->title = 'Your upcoming live class isn\'t reachable on Zoom';
        $this->body  = sprintf(
            'The Zoom meeting %s for your %s class can\'t be found. Recreate the live class now so students can join when it starts.',
            $liveClass->meeting_id ?? '',
            $liveClass->start_time?->diffForHumans() ?? 'upcoming'
        );
        $this->icon = 'fa-triangle-exclamation';
        $this->iconColor = '#dc2626';

        try {
            // Best-effort link to the live class management page; instructors
            // can edit/delete from there. Falls through to a static path if
            // the route isn't named yet on this codebase.
            $this->url = route('instructor.live-classes.index');
        } catch (\Throwable) {
            $this->url = '/instructor/live-classes';
        }
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'   => (string) ($notifiable->name ?? ''),
            'meeting_id'  => (string) ($this->liveClass->meeting_id ?? ''),
            'start_time'  => $this->liveClass->start_time?->format('M d, Y h:i A') ?? '',
            'starts_in'   => $this->liveClass->start_time?->diffForHumans() ?? '',
        ];
    }
}
