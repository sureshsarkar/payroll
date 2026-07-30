<?php

namespace App\Http\Middleware;

use App\Models\CoachDomain;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * White-label tenant resolver.
 *
 * Determines which coach "owns" the current request, in priority order:
 *   1. Route parameter — /coach/{coachSlug}/...  (path mode, dev + fallback)
 *   2. Host header     — acme.mbsguru.com        (subdomain mode, production)
 *   3. Session         — tenant_coach_id stored from an earlier hop
 *                        (used to survive gateway callback round-trips)
 *
 * Once resolved, the following are made available to every downstream
 * controller and Blade view:
 *
 *   request()->attributes->get('tenant_coach_id')   int|null
 *   request()->attributes->get('tenant_coach')      User|null
 *   request()->attributes->get('tenant_coach_slug') string|null
 *   session('tenant_coach_id')                      survives the gateway callback hop
 *
 * If no coach can be resolved, the middleware does NOT block the request
 * — it just leaves tenant_coach_id null. The downstream controller can
 * then choose to 404 or fall back to platform behaviour.
 *
 * Sister middleware ResolveCoachByDomain remains the host-based brand
 * resolver for the marketing-page side; TenantContext extends the same
 * idea to the cart/checkout/auth/dashboard surfaces so the URL stays
 * coach-branded end-to-end.
 */
class TenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $coach = $this->resolveFromRouteParam($request)
              ?? $this->resolveFromHost($request)
              ?? $this->resolveFromSession($request);

        if ($coach) {
            $request->attributes->set('tenant_coach',       $coach);
            $request->attributes->set('tenant_coach_id',    (int) $coach->id);
            $request->attributes->set('tenant_coach_slug',  $this->slugFor($coach));
            $request->session()->put('tenant_coach_id', (int) $coach->id);
        }

        return $next($request);
    }

    /**
     * Route param `coachSlug` (or older `site_slug`) — matches a coach's
     * landing-page slug in coach_landing_pages, or their name slug.
     */
    protected function resolveFromRouteParam(Request $request): ?User
    {
        $slug = $request->route('coachSlug') ?? $request->route('site_slug');
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        // Match either the landing-page slug or a slug-of-the-name fallback
        $byLanding = \App\Models\CoachLandingPage::query()
            ->where('slug', $slug)
            ->whereNotNull('added_by')
            ->first();
        if ($byLanding) {
            return User::query()->where('id', $byLanding->added_by)->first();
        }

        // Fallback: scan instructor users for slug-of-name match
        return User::query()
            ->where('role', 'instructor')
            ->whereRaw('LOWER(REPLACE(name, " ", "-")) = ?', [strtolower($slug)])
            ->first();
    }

    /**
     * Host header → coach_domains lookup. Production subdomain mode.
     */
    protected function resolveFromHost(Request $request): ?User
    {
        $host = strtolower((string) $request->getHost());
        if ($host === '' || $host === parse_url(config('app.url'), PHP_URL_HOST)) {
            // The platform's own host doesn't belong to any coach.
            return null;
        }

        // 2026-06-17 — honour domain governance like ResolveCoachByDomain does:
        // a SUSPENDED coach domain (admin-disabled) must stop resolving, so its
        // commerce / student-panel / auth surfaces go dark too. Previously this
        // host resolver only required verified_at, so a suspended domain kept
        // serving. Excluding suspended (rather than requiring ACTIVE) closes the
        // hole without breaking legacy 'verified'-but-not-'active' domains.
        $domain = CoachDomain::query()
            ->where('hostname', $host)
            ->whereNotNull('verified_at')
            ->where('status', '!=', CoachDomain::STATUS_SUSPENDED)
            ->first();
        if (! $domain) {
            return null;
        }

        return User::query()->where('id', $domain->coach_id)->first();
    }

    /**
     * Session fallback — survives the payment-gateway callback hop.
     */
    protected function resolveFromSession(Request $request): ?User
    {
        $id = (int) $request->session()->get('tenant_coach_id', 0);
        if ($id <= 0) {
            return null;
        }
        return User::query()->where('id', $id)->first();
    }

    /**
     * Best-effort coach slug for URL generation. Tries landing-page slug
     * first; falls back to a slug of the coach's name.
     */
    protected function slugFor(User $coach): string
    {
        $landing = \App\Models\CoachLandingPage::query()
            ->where('added_by', $coach->id)
            ->first();
        if ($landing && ! empty($landing->slug)) {
            return $landing->slug;
        }
        return \Illuminate\Support\Str::slug($coach->name ?: ('coach-' . $coach->id));
    }
}
