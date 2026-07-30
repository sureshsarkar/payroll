<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * NoStoreAuthenticated — sets HTTP cache headers that prevent any shared
 * cache (browser back-button, browser disk cache, reverse proxy,
 * Cloudflare, Varnish, mod_cache, corporate proxy) from serving an
 * authenticated user's HTML response to a different user.
 *
 * Audit 2026-05-22 — confirmed root cause of "user A sees user B's data"
 * reports. Without these headers, Laravel emits no Cache-Control on web
 * routes; many caching layers default to "cache anything 200 OK", which
 * means the FIRST authenticated user's dashboard HTML can be served to
 * the SECOND user who hits the same URL.
 *
 * Apply this middleware to every route group that requires
 * authentication (web auth, admin, API for completeness). NEVER apply to
 * public marketing pages — those benefit from caching.
 *
 * The three headers, layered for max compatibility:
 *   1. Cache-Control: no-store, no-cache, must-revalidate, private,
 *      max-age=0 — modern browsers + RFC 7234 conformant caches
 *   2. Pragma: no-cache — HTTP/1.0 fallback
 *   3. Expires: 0 — ancient proxies
 *
 * Side effects: none. Authenticated responses already shouldn't be
 * cached; we're making the platform explicit about that.
 */
class NoStoreAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only set the headers if the user is authenticated. Public pages
        // (login screen, marketing) still get default headers.
        if ($request->user() !== null || auth()->guard('admin')->check()) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
