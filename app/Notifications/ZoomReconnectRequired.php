<?php

namespace App\Notifications;

/**
 * Sent by `php artisan zoom:health-check` when an instructor's Zoom OAuth
 * credential transitions to 'dead' (refresh-token rejected with
 * invalid_grant) or 'expiring' (token expires within 14 days).
 *
 * Only fires on a STATE CHANGE — not daily. Daily reminders for the same
 * status train people to ignore the email. The next-day check that finds
 * the same 'dead' status will not re-notify.
 */
class ZoomReconnectRequired extends InAppNotification
{
    protected string $emailCtaLabel = 'Reconnect Zoom';

    private string $status;
    private string $message;

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(string $status, string $message)
    {
        $this->status  = $status;
        $this->message = $message;

        if ($status === 'dead') {
            $this->title = 'Zoom not connected — students can\'t join your live classes';
            $this->body  = 'Your Zoom OAuth token is no longer accepted by Zoom. Reconnect now so any scheduled live classes work.';
            $this->icon = 'fa-triangle-exclamation';
            $this->iconColor = '#dc2626';
        } else {
            $this->title = 'Zoom connection expiring soon';
            $this->body  = 'Your Zoom token will stop working in less than 14 days. Reconnect now to avoid any interruption.';
            $this->icon = 'fa-clock';
            $this->iconColor = '#f59e0b';
        }

        try {
            $this->url = route('instructor.zoom-setting.index');
        } catch (\Throwable) {
            $this->url = '/instructor/zoom-setting';
        }
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'      => (string) ($notifiable->name ?? ''),
            'status'         => $this->status,
            'health_message' => $this->message,
            'reconnect_url'  => $this->url ?? '/instructor/zoom-setting',
        ];
    }
}
