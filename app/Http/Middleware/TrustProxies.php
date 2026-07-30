<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * ⚠️ SECURITY (audit 2026-06-12) — THE WHITE-LABEL TENANT BOUNDARY DEPENDS
     * ON A TRUSTWORTHY $request->getHost(). CoachDomain::coachIdForHost() maps
     * the host header to a coach; if X-Forwarded-Host can be spoofed, an
     * attacker resolves themselves into ANY coach's branded tenant context
     * (full impersonation).
     *
     * Leave this NULL unless you front the app with a load balancer/reverse
     * proxy you control, and then set it to that proxy's EXACT IP/CIDR — e.g.
     * `protected $proxies = '10.0.0.0/8';`. NEVER set it to '*' (that trusts
     * every client and makes the host header attacker-controlled). NULL is the
     * safe default: forwarded host/proto headers are ignored.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
