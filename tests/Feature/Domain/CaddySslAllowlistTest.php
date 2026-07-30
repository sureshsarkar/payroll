<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Internal\SslAllowlistController;
use App\Models\CoachDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Caddy on-demand TLS allow-list endpoint.
 *
 * GET /internal/ssl-allowed?domain=<host>
 *
 * Caddy calls this before issuing a Let's Encrypt cert. Our
 * responsibility: return 200 ONLY for hostnames that exist in
 * coach_domains with verified_at NOT NULL. Anything else → 403
 * so Caddy doesn't burn the platform's Let's Encrypt rate-limit
 * budget on cert requests for hostnames we don't own.
 *
 * Six contracts:
 *   1. Verified hostname → 200
 *   2. Unverified hostname → 403
 *   3. Unknown hostname → 403
 *   4. Missing domain param → 400
 *   5. Malformed domain (no TLD) → 400
 *   6. Mixed-case host normalises + matches
 *
 * Style note: like every other Domain test in this suite, we call
 * the controller method directly rather than the HTTP layer. This
 * avoids the APP_URL prefix issue (this install ships from a
 * /mbsguru1/public/ sub-path) and keeps the test focused on the
 * controller's contract.
 */
class CaddySslAllowlistTest extends TestCase
{
    use DatabaseTransactions;

    public function test_verified_domain_returns_200(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachDomain::create([
            'coach_id'    => $coach->id,
            'hostname'    => 'acme.example.test',
            'kind'        => 'custom',
            'is_primary'  => true,
            'verified_at' => now(),
        ]);
        CoachDomain::forgetCacheForHost('acme.example.test');

        $resp = $this->probe('acme.example.test');
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_unverified_domain_returns_403(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachDomain::create([
            'coach_id'    => $coach->id,
            'hostname'    => 'unverified.example.test',
            'kind'        => 'custom',
            'verified_at' => null,
        ]);
        CoachDomain::forgetCacheForHost('unverified.example.test');

        $resp = $this->probe('unverified.example.test');
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_unknown_domain_returns_403(): void
    {
        $resp = $this->probe('totally-random.example.test');
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_missing_domain_returns_400(): void
    {
        $resp = $this->probe(null);
        $this->assertSame(400, $resp->getStatusCode());
    }

    public function test_malformed_domain_returns_400(): void
    {
        // No TLD → can't be a real hostname → 400 rather than 403
        // so the operator can distinguish malformed-input from
        // not-in-allowlist when debugging.
        $resp = $this->probe('localhost-no-tld');
        $this->assertSame(400, $resp->getStatusCode());
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachDomain::create([
            'coach_id'    => $coach->id,
            'hostname'    => 'casetest.example.test',
            'kind'        => 'custom',
            'verified_at' => now(),
        ]);
        CoachDomain::forgetCacheForHost('casetest.example.test');

        // Caddy sometimes sends with mixed case.
        $resp = $this->probe('CaseTest.Example.test');
        $this->assertSame(200, $resp->getStatusCode());
    }

    /**
     * Build a request the same way Caddy would and dispatch
     * through the controller method.
     */
    protected function probe(?string $host): \Illuminate\Http\Response
    {
        $qs = $host === null ? [] : ['domain' => $host];
        $req = Request::create('/internal/ssl-allowed', 'GET', $qs);
        return app(SslAllowlistController::class)->check($req);
    }
}
