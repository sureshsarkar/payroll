<?php

namespace App\Notifications;

use App\Models\UserMembership;

/**
 * Sent at 3 days, 1 day, and 0 days (today) before a coach's free trial ends.
 * Drives upgrade conversion before the membership lapses + features lock.
 */
class TrialExpiringSoonToCoach extends InAppNotification
{
    protected string $event = 'trial_expiring';
    protected string $emailTemplate = 'notif_trial_expiring';
    protected string $emailCtaLabel = 'Choose a plan';

    private UserMembership $membership;
    private int $daysLeft;

    public function __construct(UserMembership $membership, int $daysLeft)
    {
        $this->membership = $membership;
        $this->daysLeft   = max(0, $daysLeft);

        if ($this->daysLeft === 0) {
            $this->title = 'Your free trial ends today';
            $this->body  = 'Pick a plan to keep using coach features without interruption.';
            $this->iconColor = '#ef4444';
        } elseif ($this->daysLeft === 1) {
            $this->title = 'Your free trial ends tomorrow';
            $this->body  = 'Upgrade now to avoid losing access to courses + live classes.';
            $this->iconColor = '#f59e0b';
        } else {
            $this->title = 'Your free trial ends in ' . $this->daysLeft . ' days';
            $this->body  = 'Browse plans and pick one that fits.';
            $this->iconColor = '#3b82f6';
        }

        $this->icon = 'fa-hourglass-half';
        $this->url  = route('membership.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'  => $notifiable->name ?? '',
            'days_left'  => (string) $this->daysLeft,
            'expires_at' => $this->membership->expires_at?->format('M d, Y') ?? '',
        ];
    }
}
