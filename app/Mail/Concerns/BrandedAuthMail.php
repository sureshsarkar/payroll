<?php

namespace App\Mail\Concerns;

use App\Services\BrandResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves white-label brand fields for the transactional auth Mailables
 * (email verification, password reset) from the recipient user's coach.
 *
 * A student/staff row carries its coach in `coach_id` → that coach's brand.
 * A coach (instructor, coach_id NULL) or platform user → platform brand.
 *
 * Exposes scalar public props (auto-passed to the Mailable's view): the
 * platform logo is never leaked onto a coach email — when the coach has no
 * own logo, $brandLogoUrl is null and the blade renders the name wordmark.
 */
trait BrandedAuthMail
{
    public ?string $brandName = null;
    public ?string $brandLogoUrl = null;
    public ?string $brandColor = null;
    public bool $brandIsCoach = false;

    protected function resolveBrandFor($user): void
    {
        $coachId = is_object($user) ? ($user->coach_id ?? null) : null;

        if ($coachId) {
            try {
                $b = app(BrandResolver::class)->forCoach((int) $coachId);
                $this->brandIsCoach = true;
                $this->brandName    = $b->name;
                $this->brandLogoUrl = $b->ownLogo ? $b->logoUrl() : null;
                $this->brandColor   = $b->primaryColor;
                return;
            } catch (\Throwable $e) {
                \Log::warning('Auth-mail brand resolution failed: ' . $e->getMessage());
            }
        }

        // Platform fallback (unchanged behaviour for coach/platform users).
        $s = Cache::get('setting');
        $this->brandName    = $s?->app_name ?? config('app.name');
        $logo = $s?->logo ?? null;
        $this->brandLogoUrl = $logo ? asset($logo) : null;
        $this->brandColor   = '#0867ec';
    }
}
