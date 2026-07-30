<?php

namespace App\Notifications;

/**
 * Sent to a coach when their custom domain / subdomain becomes live (verified +
 * active). White-label: branded as the coach (it's their site/brand going
 * live), not the platform.
 */
class CoachDomainLiveToCoach extends InAppNotification
{
    public function __construct(?string $hostname = null)
    {
        $host = trim((string) $hostname);

        $this->title     = 'Your website is live';
        $this->body      = $host !== ''
            ? 'Your domain ' . $host . ' is now verified and serving your branded site.'
            : 'Your domain is now verified and serving your branded site.';
        $this->icon      = 'fa-globe';
        $this->iconColor = '#10b981';

        try {
            // 2026-06-25 (route audit) — was route('instructor.web-page'), not a
            // registered name (builder home is 'instructor.web-page.index') → the
            // "your site is live" link silently fell back to null.
            $this->url = route('instructor.web-page.index');
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }

    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }
}
