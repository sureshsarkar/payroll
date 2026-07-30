<?php

namespace App\Http\Middleware;

use App\Models\CoachDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-coach white-label — Phase 2.
 *
 * Looks up the request's host header in coach_domains. On match,
 * stamps the coach id onto the request as a custom attribute:
 *
 *     $request->attributes->set('resolved_coach_id', $id);
 *
 * BrandResolver::current() reads that stamp first (before falling
 * back to logged-in-user resolution), so a student visiting
 * coach1.com sees coach1's brand even before they sign in.
 *
 * No match = no stamp = no behavioural change. The platform's own
 * root domain, the admin panel host, the API host — none of these
 * appear in coach_domains so they fall through to the existing
 * platform brand.
 *
 * Registered globally in the 'web' middleware group (Kernel.php).
 * Runs BEFORE auth so the brand is correct on the login page.
 *
 * Performance: one cached lookup per request (CoachDomain::coachIdForHost
 * caches 60s in Laravel's cache store). Hot path on every page load.
 */
class ResolveCoachByDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $coachId = CoachDomain::coachIdForHost($request->getHost());
        if ($coachId) {
            // 2026-07-10 — Canonical host (New Changes for UI #2). When the
            // coach has an ACTIVE, verified primary CUSTOM domain, their
            // MBSGuru subdomain is no longer the canonical home for the site:
            // permanently (301) send subdomain traffic to the main domain,
            // preserving the exact path + query string. Guards against loops:
            // fires ONLY when the CURRENT host is the coach's *subdomain*
            // (kind='subdomain') AND a live custom domain exists AND the
            // target differs from the current host — so requests already on
            // the custom domain, and coaches without one, are never touched.
            if (CoachDomain::kindForHost($request->getHost()) === 'subdomain') {
                $mainHost = CoachDomain::activeCustomPrimaryHostFor($coachId);
                if ($mainHost && $mainHost !== CoachDomain::normalise($request->getHost())) {
                    $target = $request->getScheme() . '://' . $mainHost . '/' . ltrim($request->path(), '/');
                    if ($qs = $request->getQueryString()) {
                        $target .= '?' . $qs;
                    }
                    return redirect()->to($target, 301);
                }
            }

            $request->attributes->set('resolved_coach_id', $coachId);

            // 2026-06-09 — White-label isolation. The platform SUPERADMIN
            // surface (/admin/*) must NEVER be reachable on a coach domain
            // (custom or subdomain): only the coach site + coach panel
            // (/instructor) + student surface live here. We block it HERE —
            // the very first middleware in the web stack — so it runs BEFORE
            // the admin auth guard (which, by middleware priority, would
            // otherwise bounce a guest to /admin/login first). All methods, so
            // the admin login POST is blocked too. Send to the coach site root
            // — no platform admin login on the coach's brand, no superadmin
            // access through a tenant host. The platform's own host has no
            // coach match, so admin keeps working there.
            $path = trim($request->path(), '/');
            if ($path === 'admin' || str_starts_with($path, 'admin/')) {
                return redirect()->to('/');
            }
        }

        return $next($request);
    }
}
