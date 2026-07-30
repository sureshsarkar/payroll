<?php

namespace App\Notifications;

use App\Models\Theme;

/**
 * 2026-06-16 — sent to the coach after a successful website theme change.
 * Subject: "Website Theme Changed Successfully". Rides the platform's
 * InAppNotification channels (bell + email; email uses the admin SMTP config).
 * Reassures the coach their website DATA is safe (only presentation changed) and
 * prompts them to contact support if they didn't make the change.
 */
class WebsiteThemeChanged extends InAppNotification
{
    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(Theme $newTheme, ?Theme $previousTheme, $site)
    {
        $when    = now()->format('d M Y, h:i A');
        $website = $site->website_name ?? $site->subdomain ?? config('app.name');

        $this->title = 'Website Theme Changed Successfully';
        $this->body  = 'Your website theme is now "' . $newTheme->name . '"'
            . ($previousTheme ? ' (previously "' . $previousTheme->name . '")' : '') . '. '
            . 'Website: ' . $website . '. Changed on ' . $when . '. '
            . 'Your website data — pages, content, images, courses, menus and SEO — remains completely safe; '
            . 'only the design and presentation changed. '
            . 'If you did NOT make this change, please contact support immediately.';
        $this->icon      = 'fa-palette';
        $this->iconColor = '#6366f1';
        $this->url       = url('/instructor/web-page');
    }

    protected function placeholders(object $notifiable): array
    {
        return ['user_name' => $notifiable->name ?? ''];
    }
}
