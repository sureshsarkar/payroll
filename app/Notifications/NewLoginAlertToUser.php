<?php

namespace App\Notifications;

/**
 * Security alert sent when a user signs in from a NEW device/location.
 * White-label: branded as the user's coach (student/staff) or platform.
 */
class NewLoginAlertToUser extends InAppNotification
{
    public function __construct(?string $ip = null, ?string $userAgent = null)
    {
        $where = $ip ? (' from IP ' . $ip) : '';
        $this->title     = 'New sign-in to your account';
        $this->body      = 'We noticed a sign-in to your account' . $where . '. '
                          . 'If this was you, no action is needed. If you do NOT recognise it, '
                          . 'change your password immediately and contact support.';
        $this->icon      = 'fa-shield-halved';
        $this->iconColor = '#f59e0b';

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
