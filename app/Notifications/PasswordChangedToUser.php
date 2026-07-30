<?php

namespace App\Notifications;

/**
 * Security confirmation sent when a user's password is changed (profile update
 * or reset completion). White-label: branded as the user's coach when the
 * recipient is a student/staff of a coach; platform otherwise. No preference
 * gating — a security confirmation should always be delivered.
 */
class PasswordChangedToUser extends InAppNotification
{
    public function __construct()
    {
        $this->title     = 'Your password was changed';
        $this->body      = 'If you made this change, no action is needed. '
                          . 'If you did NOT change your password, contact support immediately to secure your account.';
        $this->icon      = 'fa-shield-halved';
        $this->iconColor = '#ef4444';

        try {
            $this->url = route('login');
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }

    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }
}
