<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;

/**
 * Renders a notification email through the shared, white-label
 * `emails.notification` template.
 *
 * Single source of truth for: coach-brand resolution (logo/name/colour/
 * footer/signature/support), per-coach SMTP routing, and the From header.
 * Used by InAppNotification (the base for most notifications) AND the few
 * notifications that extend Notification directly with a custom toMail().
 *
 * Pass an explicit $coachId (null → platform branding, fully backward
 * compatible) plus the notification's content; everything brand-derived is
 * filled in here.
 */
trait BrandedNotificationMail
{
    /**
     * Apply admin-configured platform SMTP from the cached `setting` row,
     * mirroring App\Traits\MailSenderTrait::setMailConfig() without coupling.
     */
    protected function applyAdminMailConfig(): void
    {
        try {
            $s = Cache::get('setting');
            if (!$s) return;

            if (!empty($s->mail_host))       config(['mail.mailers.smtp.host' => $s->mail_host]);
            if (!empty($s->mail_port))       config(['mail.mailers.smtp.port' => $s->mail_port]);
            if (!empty($s->mail_encryption)) config(['mail.mailers.smtp.encryption' => $s->mail_encryption]);
            if (!empty($s->mail_username))   config(['mail.mailers.smtp.username' => $s->mail_username]);
            if (!empty($s->mail_password))   config(['mail.mailers.smtp.password' => $s->mail_password]);

            if (!empty($s->mail_sender_email)) config(['mail.from.address' => $s->mail_sender_email]);
            if (!empty($s->mail_sender_name))  config(['mail.from.name'    => $s->mail_sender_name]);
        } catch (\Throwable $e) {
            // Fall back to .env-configured mail settings on any failure.
        }
    }

    /**
     * Build a MailMessage rendering `emails.notification` with the coach's
     * brand (or platform defaults when $coachId is null/unresolvable).
     *
     * @param array $content title, body, bodyHtml, url, icon, iconColor,
     *                       recipientName, ctaLabel — all optional.
     */
    protected function buildBrandedMail(object $notifiable, ?int $coachId, string $subject, array $content = []): MailMessage
    {
        $this->applyAdminMailConfig();

        $setting = Cache::get('setting');
        $appName = $setting?->app_name ?? config('app.name');
        $settingLogo = $setting?->logo ?? null;
        $appLogo = $settingLogo ? url($settingLogo) : null;

        // LMS removal phase 2 (2026-08-27) — three white-label mechanisms are
        // gone from this method, and with them every $coachId branch:
        //   - BrandResolver::forCoach($coachId), which composed the coach's
        //     CoachBrandSetting over the platform brand;
        //   - per-coach SMTP routing (a coach with verified credentials had
        //     their mail sent from their own server, with their own From and a
        //     Reply-To pointing at their support inbox);
        //   - the tenant-safe URL rewrite, which swapped the platform host in
        //     the CTA, the preferences link and the body HTML for the coach's
        //     verified custom domain.
        // Everything now renders the platform brand and sends on the platform
        // mailer. $coachId is still accepted so callers need not change.
        $brandName = $appName;
        $brandLogo = $appLogo;
        // Platform accent is emerald (#10b981), NOT the legacy indigo.
        $brandColor = ($content['iconColor'] ?? '') ?: '#10b981';
        // An empty iconColor from the notification means "use the brand accent".
        // A non-empty value is a deliberate semantic colour (e.g. red for a
        // failure) and is preserved.
        $resolvedIconColor = (! empty($content['iconColor'])) ? $content['iconColor'] : $brandColor;
        unset($content['iconColor']);
        $supportEmail   = $setting?->site_email ?? null;
        $supportPhone   = $setting?->site_phone ?? null;
        $footerText     = null;
        $emailSignature = null;

        // Manage-preferences URL appropriate to the recipient type.
        $preferencesUrl = null;
        try {
            if ($notifiable instanceof \App\Models\User) {
                $preferencesUrl = route('notifications.preferences.index');
            }
        } catch (\Throwable $e) { /* route may not exist in some contexts */ }

        $mail = (new MailMessage)->subject($subject);

        return $mail->view('emails.notification', array_merge([
            'title'          => '',
            'body'           => '',
            'bodyHtml'       => null,
            'url'            => null,
            'icon'           => 'fa-bell',
            'iconColor'      => $resolvedIconColor,
            'recipientName'  => '',
            'ctaLabel'       => 'View details',
            'appName'        => $brandName,
            'appLogo'        => $brandLogo,
            'brandColor'     => $brandColor,
            'supportEmail'   => $supportEmail,
            'supportPhone'   => $supportPhone,
            'footerText'     => $footerText,
            'emailSignature' => $emailSignature,
            'preferencesUrl' => $preferencesUrl,
        ], $content));
    }
    /**
     * LMS removal phase 2 (2026-08-27) — applyCoachHost() rewrote the platform
     * host to a coach's verified custom domain; coachHostRewrite() was its
     * single-URL wrapper, used so the in-app bell and web-push redirect landed
     * on the coach's domain. There is one host now, so this is a pass-through.
     * Kept (rather than deleted) because InAppNotification still calls it on
     * both the database and web-push payloads.
     */
    protected function coachHostRewrite(?string $url, ?int $coachId): ?string
    {
        return $url;
    }
}
