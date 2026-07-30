<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Post-login 2FA gate. Works with any guard; pass the guard name as the
 * middleware argument:
 *
 *     'middleware' => ['auth:admin', '2fa:admin']
 *     'middleware' => ['auth', 'verified', '2fa:web']
 *
 * If the logged-in user has 2FA enabled, every request must have
 * session('two_factor.passed_at.{guard}') set within VALID_FOR_MINUTES;
 * otherwise we redirect to the guard's challenge route.
 *
 * Each guard's challenge/login/logout routes must be whitelisted in
 * GUARD_CONFIG below — those endpoints have to remain reachable AFTER login
 * but BEFORE the challenge is solved.
 */
class EnsureTwoFactorChallenged
{
    /** How long a passed challenge stays valid (in minutes). */
    private const VALID_FOR_MINUTES = 120;

    /**
     * Per-guard config: list of path prefixes that bypass the gate, and the
     * route name to redirect to when challenge is required.
     */
    private const GUARD_CONFIG = [
        'admin' => [
            'challenge_route' => 'admin.2fa.challenge',
            'whitelist'       => [
                'admin/2fa/challenge',
                'admin/logout',
            ],
        ],
        'web' => [
            'challenge_route' => 'web.2fa.challenge',
            'whitelist'       => [
                '2fa/challenge',
                'logout',
                'logoutme',
            ],
        ],
    ];

    public function handle(Request $request, Closure $next, string $guard = 'admin'): Response
    {
        $user = auth($guard)->user();
        if (!$user) {
            return $next($request);
        }

        // Not enrolled in 2FA → don't gate.
        if (!method_exists($user, 'hasTwoFactorEnabled') || !$user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        $config = self::GUARD_CONFIG[$guard] ?? null;
        if (!$config) {
            // Unknown guard configured — fail-open rather than risk lockout.
            return $next($request);
        }

        // Whitelisted paths (challenge + logout) stay reachable.
        // F35 (audit 2026-06-26) — match the exact path or a real sub-path
        // ("$allowed/..."), NOT any prefix. The old str_starts_with let
        // "logout-everywhere" satisfy the "logout" entry and bypass the challenge.
        $path = trim($request->path(), '/');
        foreach ($config['whitelist'] as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return $next($request);
            }
        }

        $sessionKey = "two_factor.passed_at.$guard";
        $passedAt = session($sessionKey);
        if ($passedAt && now()->diffInMinutes(\Carbon\Carbon::parse($passedAt)) < self::VALID_FOR_MINUTES) {
            return $next($request);
        }

        return redirect()->route($config['challenge_route']);
    }
}
