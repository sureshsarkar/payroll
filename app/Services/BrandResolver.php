<?php

namespace App\Services;

use App\Models\CoachBrandSetting;
use Illuminate\Support\Facades\Cache;

/**
 * BrandResolver — the single read path for "what brand do we render
 * for the current request / for this coach".
 *
 * Composition rule (in order):
 *
 *   1. coach_brand_settings.<field> if non-null   (coach explicitly set it)
 *   2. cache('setting')-><field>    if present    (platform default)
 *   3. hardcoded sane fallback                    (last resort)
 *
 * This guarantees a coach who hasn't customised ANY field sees the
 * platform brand (no regression for existing coaches), and a coach
 * who customises one or two fields gets a mix — coach logo, platform
 * support email, coach brand name, platform colors, etc.
 *
 * Used by:
 *   - View composer registered in AppServiceProvider — every view
 *     gets $brand automatically.
 *   - Anywhere in PHP that needs branded values:
 *         $brand = app(BrandResolver::class)->current();
 *         echo $brand->name;
 *
 * Tenant resolution:
 *   - Today: forCoach() takes an explicit coach id.
 *   - P2 will add forRequest() that resolves the coach from the
 *     request host (custom domain or subdomain) and stamps the
 *     result onto the request. current() will read that stamp.
 *   - Until P2 ships, current() falls back to the LOGGED-IN coach
 *     if any, otherwise the platform default.
 */
class BrandResolver
{
    /**
     * Resolve the brand for an explicit coach id.
     */
    public function forCoach(int $coachId): Brand
    {
        $row = CoachBrandSetting::firstOrCreateForCoach($coachId);
        return Brand::compose($row, $this->platformDefaults());
    }

    /**
     * Resolve the brand for the current request.
     *
     * Until P2 (host-based tenant resolution) ships:
     *   - If a coach is set on the request (request()->attributes
     *     ->get('resolved_coach_id')) use it.
     *   - Else if the logged-in user is a coach, use their id.
     *   - Else if the logged-in user is staff/student with a known
     *     coach (coach_id column), use their parent coach.
     *   - Else return the platform default brand.
     */
    public function current(): Brand
    {
        $coachId = $this->resolveCurrentCoachId();
        if ($coachId !== null) {
            return $this->forCoach($coachId);
        }
        return Brand::platform($this->platformDefaults());
    }

    /**
     * Best-effort coach-id resolution for the current request.
     * Returns null when no coach context is in play (e.g. the
     * platform home page, login form, admin dashboard).
     */
    protected function resolveCurrentCoachId(): ?int
    {
        // Brand follows the SURFACE (which site you're on), NEVER the logged-in
        // user. 2026-06-18 — the old code fell back to the logged-in coach when
        // no domain was resolved, so a coach logged in on the PLATFORM site
        // (mbsguru.com — no resolved_coach_id) leaked their brand name onto the
        // main site + every other surface. White-label rule: coach brand only on
        // that coach's domain/subdomain/path; the platform always shows the
        // default MBSGuru brand regardless of who is logged in.

        // 1. Custom domain / subdomain — ResolveCoachByDomain stamp.
        $resolved = request()->attributes->get('resolved_coach_id');
        if ($resolved) {
            return (int) $resolved;
        }

        // 2. Path surface (/coach/{slug}) — TenantContext stamp (attribute or
        //    the session value that survives the gateway-callback hop).
        $tenant = request()->attributes->get('tenant_coach_id')
            ?: (function () {
                try {
                    return request()->hasSession() ? request()->session()->get('tenant_coach_id') : null;
                } catch (\Throwable $e) {
                    return null;
                }
            })();
        if ($tenant) {
            return (int) $tenant;
        }

        // Otherwise: NO coach surface → platform default brand. (Deliberately
        // no auth-based fallback — that was the cross-tenant brand leak.)
        return null;
    }

    /**
     * Pulls the platform settings into an array. cache('setting') is
     * a stdClass — cast to array and we're good.
     */
    protected function platformDefaults(): array
    {
        return Cache::remember('brand:platform_defaults', 60, function () {
            $s = cache('setting');
            return $s ? (array) $s : [];
        });
    }
}
