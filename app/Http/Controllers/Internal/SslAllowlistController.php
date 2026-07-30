<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\CoachDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Caddy "ask" endpoint for on-demand TLS allow-listing.
 *
 * Caddy's `tls { on_demand }` will provision a Let's Encrypt cert
 * for ANY hostname the first time it sees traffic. That's a DoS
 * hazard — an attacker can request certs for arbitrary domains
 * and burn through Let's Encrypt's rate limits (50 certs/week/account).
 *
 * Caddy supports an `ask` directive that calls back to an HTTP
 * endpoint and only proceeds when the response is 200. This
 * controller is that endpoint:
 *
 *     on_demand_tls {
 *         ask https://platform.com/internal/ssl-allowed
 *     }
 *
 * Caddy GETs with ?domain=<host>; we return 200 if the host is in
 * coach_domains with verified_at NOT NULL, 403 otherwise. The
 * Caddy ask docs are at https://caddyserver.com/docs/caddyfile/options#on-demand-tls
 *
 * Security:
 *   1. We don't auth this endpoint — Caddy is unauthenticated and
 *      we control the URL. The risk surface is "anyone who can
 *      hit our server can probe which custom domains are
 *      verified" — that's a privacy leak (which coaches host
 *      here), NOT a privilege escalation. Operator can additionally
 *      restrict by IP in their reverse proxy if they want.
 *   2. Rate-limited via the route middleware (60/min/IP) so a
 *      mass-probe can't fingerprint the whole coach catalog in
 *      one second.
 *   3. The lookup is case-insensitive + port-stripped via
 *      CoachDomain::normalise() — same normalisation the P2
 *      middleware uses, so a domain that resolves there resolves
 *      here.
 *
 * Returns:
 *   200  domain is in coach_domains AND verified (issue the cert)
 *   403  domain is NOT in coach_domains OR unverified (skip)
 *   400  request didn't include ?domain= or it was malformed
 */
class SslAllowlistController extends Controller
{
    public function check(Request $request): Response
    {
        $raw = (string) $request->query('domain', '');
        if ($raw === '') {
            return response('missing domain', 400);
        }

        $host = CoachDomain::normalise($raw);
        if ($host === '' || ! str_contains($host, '.')) {
            return response('invalid domain', 400);
        }

        // Reuse the P2 middleware's lookup helper — it's cached 60s
        // and returns the coach id ONLY for verified rows. A null
        // here means "no verified coach row for this hostname",
        // which is exactly the case where Caddy should refuse to
        // issue a cert.
        $coachId = CoachDomain::coachIdForHost($host);

        if ($coachId === null) {
            return response('not allowed', 403);
        }

        // 2026-06 — a 200 here means Caddy is about to issue / use a cert for
        // this host, so record SSL as issued (idempotent: one write per host,
        // only when the state actually changes). Tolerant — never block the
        // cert handshake on a bookkeeping failure.
        try {
            CoachDomain::where('hostname', $host)
                ->where('ssl_status', '!=', CoachDomain::SSL_ISSUED)
                ->update(['ssl_status' => CoachDomain::SSL_ISSUED, 'ssl_checked_at' => now()]);
        } catch (\Throwable $e) {
            // ignore — SSL bookkeeping must not break issuance
        }

        // 200 with the empty body — Caddy only cares about the status code.
        return response('', 200);
    }
}
