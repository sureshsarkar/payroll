<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the request has ?ref=CODE, drop a long-lived cookie so we can credit
 * the referrer when the visitor eventually registers or buys.
 *
 * Cookie attribution window: 30 days. First-touch wins (i.e. we don't
 * overwrite an existing cookie unless the new ref is different and the
 * cookie is missing or stale).
 */
class TrackReferralCookie
{
    private const COOKIE_NAME = 'mbs_ref';
    private const TTL_DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $ref = $request->query('ref');
        $response = $next($request);

        if ($ref && is_string($ref) && preg_match('/^[a-z0-9]{4,20}$/i', $ref)) {
            // Only set if not already present — first touch wins.
            if (!$request->cookie(self::COOKIE_NAME)) {
                $cookie = Cookie::create(
                    self::COOKIE_NAME,
                    strtolower($ref),
                    now()->addDays(self::TTL_DAYS)->timestamp,
                    '/',
                    null,    // domain
                    request()->isSecure(),
                    true,    // httpOnly
                    false,   // raw
                    'Lax'
                );
                if ($response instanceof Response) {
                    $response->headers->setCookie($cookie);
                }
            }
        }

        return $response;
    }
}
