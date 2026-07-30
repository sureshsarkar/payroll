<?php

namespace App\Notifications;

use App\Models\UserMembership;

/**
 * Sent right after a new coach signs up and gets the auto-granted free trial.
 * Distinct from MembershipActivatedToUser (which fires for paid memberships)
 * — this one is positioned as onboarding, not transactional confirmation.
 */
class CoachTrialWelcomeToUser extends InAppNotification
{
    protected string $event = 'coach_trial_welcome';
    protected string $emailTemplate = 'notif_coach_trial_welcome';
    protected string $emailCtaLabel = 'Open coach dashboard';

    private UserMembership $membership;

    public function __construct(UserMembership $membership)
    {
        $this->membership = $membership;
        $expiresAt = $membership->expires_at;

        $this->title = 'Welcome — your free trial is active';
        $this->body  = $expiresAt
            ? 'Full coach access until ' . $expiresAt->format('M d, Y') . '.'
            : 'Full coach access during your trial.';
        $this->icon = 'fa-flask';
        $this->iconColor = '#7c3aed';
        $this->url = route('instructor.dashboard');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'   => $notifiable->name ?? '',
            'trial_days'  => $this->membership->expires_at ? (string) (int) round(now()->diffInDays($this->membership->expires_at, false)) : '',
            'expires_at'  => $this->membership->expires_at?->format('M d, Y') ?? '',
        ];
    }
}
