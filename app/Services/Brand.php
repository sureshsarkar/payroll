<?php

namespace App\Services;

use App\Models\CoachBrandSetting;

/**
 * Brand — immutable value object returned by BrandResolver.
 *
 * Holds the COMPOSED view of a coach's brand (overrides + platform
 * defaults + sane fallbacks). Every getter is non-null — even if
 * the coach hasn't set anything and the platform settings are empty,
 * the last-resort fallback hands back a usable value.
 *
 * Blade-friendly. Use as:
 *     {{ $brand->name }}
 *     <img src="{{ $brand->logoUrl() }}" alt="{{ $brand->name }}">
 *     <a href="mailto:{{ $brand->supportEmail }}">
 *     <style>:root { --brand: {{ $brand->primaryColor }}; }</style>
 *
 * The view composer in AppServiceProvider injects this as $brand
 * on every view, so blades NEVER need to call cache('setting')
 * for brand-ish fields again — switch to $brand and you get
 * per-coach override behaviour for free.
 */
class Brand
{
    public function __construct(
        public readonly string  $name,
        public readonly ?string $logoPath,
        public readonly ?string $faviconPath,
        public readonly string  $primaryColor,
        public readonly string  $accentColor,
        public readonly string  $supportEmail,
        public readonly ?string $supportPhone,
        public readonly ?string $termsUrl,
        public readonly ?string $privacyUrl,
        public readonly string  $footerText,
        public readonly ?string $emailSignature,
        public readonly bool    $isPlatformDefault,
        // 2026-06-09 — true only when the LOGO shown is genuinely owned by
        // this context (the coach uploaded their own, or it's the platform's
        // own logo on a platform page). False when a coach has NOT uploaded a
        // logo and we'd otherwise leak the platform logo onto their domain —
        // the view then renders the brand NAME as a text wordmark instead.
        public readonly bool    $ownLogo = false,
    ) {}

    /**
     * Compose a coach override on top of the platform defaults +
     * hardcoded last-resort fallbacks.
     */
    public static function compose(CoachBrandSetting $row, array $platform): self
    {
        $get = fn (string $coachField, string $platformKey, ?string $hardFallback) =>
            $row->{$coachField}
                ?? ($platform[$platformKey] ?? null)
                ?? $hardFallback;

        return new self(
            name:            (string) ($row->brand_name        ?? $platform['app_name']            ?? config('app.name', 'Coaching Platform')),
            logoPath:        $row->logo_path                   ?? ($platform['logo'] ?? null),
            faviconPath:     $row->favicon_path                ?? ($platform['favicon'] ?? null),
            primaryColor:    (string) ($row->primary_color     ?? $platform['primary_color']       ?? '#10b981'),  /* 2026-07-04: last-resort default is the panel's emerald, not the old indigo */
            accentColor:     (string) ($row->accent_color      ?? $platform['secondary_color']     ?? '#059669'),  /* emerald-600 accent to pair with the primary */
            supportEmail:    (string) ($row->support_email     ?? $platform['site_email']
                                                                ?? $platform['mail_sender_email']
                                                                ?? config('mail.from.address', 'support@example.com')),
            supportPhone:    $row->support_phone               ?? ($platform['site_phone'] ?? null),
            termsUrl:        $row->terms_url                   ?? null,
            privacyUrl:      $row->privacy_url                 ?? null,
            footerText:      (string) ($row->footer_text       ?? $platform['copyright_text']      ?? '© ' . date('Y')),
            emailSignature:  $row->email_signature             ?? null,
            isPlatformDefault: ! $row->brand_name && ! $row->logo_path,
            // The logo is "owned" only if THIS coach uploaded one. When null,
            // logoPath above falls back to the platform logo — which must NOT
            // be shown on a coach domain, so flag it so the view uses the name.
            ownLogo:         $row->logo_path !== null,
        );
    }

    /**
     * Platform-only brand, used when no coach is in context.
     */
    public static function platform(array $platform): self
    {
        return new self(
            name:            (string) ($platform['app_name']            ?? config('app.name', 'Coaching Platform')),
            logoPath:        $platform['logo']                          ?? null,
            faviconPath:     $platform['favicon']                       ?? null,
            primaryColor:    (string) ($platform['primary_color']       ?? '#10b981'),  /* 2026-07-04: last-resort default is the panel's emerald, not the old indigo */
            accentColor:     (string) ($platform['secondary_color']     ?? '#059669'),  /* emerald-600 accent to pair with the primary */
            supportEmail:    (string) ($platform['site_email']
                                ?? $platform['mail_sender_email']
                                ?? config('mail.from.address', 'support@example.com')),
            supportPhone:    $platform['site_phone'] ?? null,
            termsUrl:        null,
            privacyUrl:      null,
            footerText:      (string) ($platform['copyright_text']      ?? '© ' . date('Y')),
            emailSignature:  null,
            isPlatformDefault: true,
            // On a platform page the platform logo IS the owned logo → show it.
            ownLogo:         true,
        );
    }

    /**
     * Convenience for templates — returns a usable URL for the
     * logo whether it's stored as an absolute URL or a relative
     * uploads path. Returns null when no logo is set so the blade
     * can fall through to a text-only header.
     */
    public function logoUrl(): ?string
    {
        return $this->assetUrl($this->logoPath);
    }

    public function faviconUrl(): ?string
    {
        return $this->assetUrl($this->faviconPath);
    }

    /**
     * Two-letter initials, useful as an avatar / favicon fallback.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = $words[0]   ?? '';
        $last  = $words[1]   ?? '';
        return strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) ?: 'CP';
    }

    protected function assetUrl(?string $path): ?string
    {
        if (! $path) return null;
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        // Relative — assume public/uploads style
        return asset(ltrim($path, '/'));
    }
}
