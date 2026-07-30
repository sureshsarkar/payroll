<?php

namespace App\Mail\Concerns;

use App\Services\BrandResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves white-label brand fields for generic Mailables from an explicit
 * coach id (vs BrandedAuthMail which resolves from a recipient user). Pass the
 * coach that OWNS the email's subject (order's coach, course's instructor, …);
 * null → platform branding (fully backward compatible).
 *
 * Exposes the same public scalar props the auth mails use, so a shared blade
 * can render either. The platform logo is never leaked onto a coach email:
 * when the coach has no own logo, $brandLogoUrl is null and the blade falls
 * back to the brand-name wordmark.
 */
trait BrandedMailable
{
    public ?string $brandName = null;
    public ?string $brandLogoUrl = null;
    public ?string $brandColor = null;
    public bool $brandIsCoach = false;

    protected function resolveBrandForCoach(?int $coachId): void
    {
        if ($coachId) {
            try {
                $b = app(BrandResolver::class)->forCoach((int) $coachId);
                $this->brandIsCoach = true;
                $this->brandName    = $b->name;
                $this->brandLogoUrl = $b->ownLogo ? $b->logoUrl() : null;
                $this->brandColor   = $b->primaryColor;
                return;
            } catch (\Throwable $e) {
                \Log::warning('Mailable brand resolution failed: ' . $e->getMessage());
            }
        }

        // Platform fallback (unchanged behaviour when no coach context).
        $s = Cache::get('setting');
        $this->brandName    = $s?->app_name ?? config('app.name');
        $logo = $s?->logo ?? null;
        $this->brandLogoUrl = $logo ? asset($logo) : null;
        $this->brandColor   = '#0867ec';
    }
}
