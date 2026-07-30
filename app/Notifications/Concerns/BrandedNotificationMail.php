<?php

namespace App\Notifications\Concerns;

use App\Models\CoachBrandSetting;
use App\Services\BrandResolver;
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

        // ── Brand resolution ──────────────────────────────────────────────
        $brand = null;
        if ($coachId) {
            try {
                $brand = app(BrandResolver::class)->forCoach((int) $coachId);
            } catch (\Throwable $e) {
                \Log::warning('Brand resolution failed for notification email: ' . $e->getMessage());
            }
        }

        $brandName  = $brand?->name ?? $appName;
        // Never leak the platform logo onto a coach email — render the brand
        // NAME wordmark instead (Brand::$ownLogo).
        $brandLogo  = $brand
            ? ($brand->ownLogo ? $brand->logoUrl() : null)
            : $appLogo;
        // 2026-07-07 (white-label QA) — resolve the accent from the coach brand
        // first; platform default is emerald (#10b981), NOT the legacy indigo.
        $brandColor     = $brand?->primaryColor ?? (($content['iconColor'] ?? '') ?: '#10b981');
        // An empty iconColor from the notification means "use the brand accent"
        // (so a coach's email icon matches their colour). A non-empty value is a
        // deliberate semantic colour (e.g. red for a failure) and is preserved.
        $resolvedIconColor = (! empty($content['iconColor'])) ? $content['iconColor'] : $brandColor;
        unset($content['iconColor']);
        $supportEmail   = $brand?->supportEmail ?? ($setting?->site_email ?? null);
        $supportPhone   = $brand?->supportPhone ?? ($setting?->site_phone ?? null);
        $footerText     = $brand?->footerText ?? null;
        $emailSignature = $brand?->emailSignature ?? null;

        // ── Per-coach SMTP ────────────────────────────────────────────────
        $coachMailer = null;
        $coachFromAddress = null;
        if ($coachId) {
            try {
                $row = CoachBrandSetting::firstOrCreateForCoach((int) $coachId);
                // Route through the coach's own SMTP ONLY when it is VERIFIED
                // working (smtp_verified_at set on a successful save/test). An
                // unverified or broken coach SMTP must NOT be used — otherwise
                // every email for that coach silently fails with no fallback.
                // Unverified → fall through to the platform mailer.
                if ($row->smtp_verified_at && $row->smtp_host && $row->smtp_username && $row->smtp_password_encrypted) {
                    $name = 'coach_smtp_' . (int) $coachId;
                    config(['mail.mailers.' . $name => [
                        'transport'  => 'smtp',
                        'host'       => $row->smtp_host,
                        'port'       => (int) ($row->smtp_port ?: ($row->smtp_encryption === 'ssl' ? 465 : 587)),
                        'encryption' => ($row->smtp_encryption && $row->smtp_encryption !== 'none') ? $row->smtp_encryption : null,
                        'username'   => $row->smtp_username,
                        'password'   => $row->smtp_password_encrypted,
                        'timeout'    => (int) env('MAIL_TIMEOUT', 15), // Phase 5.1: bounded coach-SMTP timeout
                    ]]);
                    $coachMailer = $name;
                    $coachFromAddress = $row->mail_from_address ?: $brand?->supportEmail;
                }
            } catch (\Throwable $e) {
                \Log::warning('Coach SMTP setup failed for notification email: ' . $e->getMessage());
            }
        }

        // Manage-preferences URL appropriate to the recipient type.
        $preferencesUrl = null;
        try {
            if ($notifiable instanceof \App\Models\User) {
                $preferencesUrl = route('notifications.preferences.index');
            }
        } catch (\Throwable $e) { /* route may not exist in some contexts */ }

        // ── Tenant-safe URLs ──────────────────────────────────────────────
        // When the coach has a VERIFIED custom domain, rewrite the platform
        // host in the CTA, preferences link and body HTML to the coach's own
        // domain so a white-label email never links a coach's student back to
        // the platform host. Only the platform host is rewritten (external
        // links are left untouched); no coach domain → no change.
        if ($coachId) {
            $coachHost = \App\Models\CoachDomain::primaryHostFor((int) $coachId);
            $appHost   = parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($coachHost && $appHost) {
                $content['url'] = $this->applyCoachHost($content['url'] ?? null, $appHost, $coachHost);
                $preferencesUrl = $this->applyCoachHost($preferencesUrl, $appHost, $coachHost);
                if (!empty($content['bodyHtml'])) {
                    $content['bodyHtml'] = $this->applyCoachHost($content['bodyHtml'], $appHost, $coachHost);
                }
            }
        }

        $mail = (new MailMessage)->subject($subject);

        if ($coachMailer) {
            $mail->mailer($coachMailer);
        }
        if ($brand && $brandName !== '') {
            $fromAddress = $coachFromAddress ?: config('mail.from.address');
            if (!empty($fromAddress)) {
                $mail->from($fromAddress, $brandName);
            }
            // 2026-07-09 (Phase 2.6): set Reply-To to the coach's support email so
            // a student's reply reaches the COACH, not the platform inbox — even
            // when the email is sent from the platform From address (coach without
            // verified SMTP). No coach support email → no Reply-To (unchanged).
            if (!empty($supportEmail)) {
                $mail->replyTo($supportEmail, $brandName);
            }
        }

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
     * Replace the platform host with the coach's verified host in a string
     * (URL or HTML). Only matches the platform host at a host boundary so a
     * platform host that is a substring of another domain is not corrupted,
     * and external links are left untouched. Scheme (https/http) is preserved.
     */
    private function applyCoachHost(?string $value, string $appHost, string $coachHost): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $pattern = '#//' . preg_quote($appHost, '#') . '(?=[/:"\'\s]|$)#';
        return preg_replace($pattern, '//' . $coachHost, $value);
    }

    /**
     * 2026-07-09 (Phase 2.2). Rewrite the platform host in a single URL to the
     * coach's VERIFIED custom domain, so the in-app bell / web-push redirect for
     * a coach's student stays on the coach's own domain — parity with the email
     * CTA, which already does this. No coach id / no verified domain → unchanged.
     */
    protected function coachHostRewrite(?string $url, ?int $coachId): ?string
    {
        if (! $url || ! $coachId) {
            return $url;
        }
        try {
            $coachHost = \App\Models\CoachDomain::primaryHostFor((int) $coachId);
            $appHost   = parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($coachHost && $appHost) {
                return $this->applyCoachHost($url, $appHost, $coachHost);
            }
        } catch (\Throwable $e) {
            // Never let URL rewriting break notification storage.
        }
        return $url;
    }
}
