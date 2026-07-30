<?php

namespace App\Http\Middleware\API;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Used by `payment.api`-guarded download routes (invoice + certificate PDFs).
 *
 * Why it exists: those endpoints are GETs that may be opened directly from
 * the mobile app's system browser / file viewer, which cannot attach a
 * custom Authorization header — so the mobile client passes the token as
 * `?bearer_token=...` and we re-inject it into the header here.
 *
 * Security caveat: bearer tokens in URL params land in:
 *   - Apache/Nginx access logs
 *   - Browser history
 *   - Referer headers if the page navigates outward
 *
 * This is a known-bad pattern (RFC 6750 §2.3 explicitly discourages it).
 * The proper replacement is `URL::temporarySignedRoute()` per-download
 * URLs, but that requires a corresponding mobile-app change. Until that
 * lands, we log every URL-param hit so we can audit usage and have a
 * clear migration story.
 */
class HeaderBearerTokenSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerToken = $request->bearerToken();
        $paramToken  = $request->input('bearer_token');
        $token       = $headerToken ?: $paramToken;

        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'UnAuthenticated'], 401);
        }

        if (!$headerToken && $paramToken) {
            // Token came from query/body — log it for the deprecation tracker.
            // Tokens themselves are never logged.
            Log::info('payment.api: bearer token used via URL/body param', [
                'route' => $request->route()?->getName(),
                'ip'    => $request->ip(),
            ]);
        }

        $request->headers->set('Authorization', 'Bearer ' . $token);

        $response = $next($request);

        // Defense: prevent intermediaries from caching an authenticated
        // download (which could leak it to subsequent requests).
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}