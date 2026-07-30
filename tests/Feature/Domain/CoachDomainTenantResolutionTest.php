<?php

namespace Tests\Feature\Domain;

use App\Http\Middleware\ResolveCoachByDomain;
use App\Models\CoachBrandSetting;
use App\Models\CoachDomain;
use App\Models\User;
use App\Services\BrandResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Per-coach white-label — Phase 2 (host-based tenant resolution).
 *
 * Five contracts under test:
 *
 *   1. CoachDomain::coachIdForHost matches by host, ignores unverified.
 *   2. CoachDomain::coachIdForHost normalises case + port + protocol.
 *   3. The middleware stamps resolved_coach_id on a matching request.
 *   4. The middleware does NOTHING when the host doesn't match.
 *   5. BrandResolver::current() picks up the stamp and returns the
 *      right coach's brand even for anonymous (logged-out) visitors.
 */
class CoachDomainTenantResolutionTest extends TestCase
{
    use DatabaseTransactions;

    /* ───────── model contract ───────── */

    public function test_coach_id_for_host_matches_verified_row(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachDomain::create([
            'coach_id'    => $coach->id,
            'hostname'    => 'acme.example.test',
            'kind'        => 'custom',
            'is_primary'  => true,
            'verified_at' => now(),
        ]);

        $this->assertSame($coach->id, CoachDomain::coachIdForHost('acme.example.test'));
    }

    public function test_coach_id_for_host_ignores_unverified_rows(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachDomain::create([
            'coach_id'    => $coach->id,
            'hostname'    => 'unverified.example.test',
            'kind'        => 'custom',
            'is_primary'  => true,
            'verified_at' => null,
        ]);

        $this->assertNull(
            CoachDomain::coachIdForHost('unverified.example.test'),
            'unverified custom domains must NOT resolve — prevents spoofing'
        );
    }

    public function test_coach_id_for_host_normalises_input(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachDomain::create([
            'coach_id'    => $coach->id,
            'hostname'    => 'mixedcase.example.test',
            'kind'        => 'custom',
            'is_primary'  => true,
            'verified_at' => now(),
        ]);

        foreach ([
            'MIXEDCASE.example.test',
            'mixedcase.example.test:443',
            'https://mixedcase.example.test',
            '  mixedcase.example.test  ',
        ] as $variant) {
            CoachDomain::forgetCacheForHost($variant);
            $this->assertSame(
                $coach->id, CoachDomain::coachIdForHost($variant),
                "host '$variant' should normalise + match"
            );
        }
    }

    /* ───────── middleware behaviour ───────── */

    public function test_middleware_stamps_resolved_coach_id_on_match(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        CoachDomain::create([
            'coach_id' => $coach->id, 'hostname' => 'mw.example.test',
            'kind' => 'custom', 'is_primary' => true, 'verified_at' => now(),
        ]);

        $req = Request::create('http://mw.example.test/x', 'GET');
        $stamped = null;
        $mw = new ResolveCoachByDomain();
        $mw->handle($req, function (Request $r) use (&$stamped) {
            $stamped = $r->attributes->get('resolved_coach_id');
            return response('ok');
        });

        $this->assertSame($coach->id, $stamped);
    }

    public function test_middleware_leaves_request_alone_when_host_does_not_match(): void
    {
        $req = Request::create('http://no-such-host.example.test/x', 'GET');
        $mw = new ResolveCoachByDomain();
        $mw->handle($req, fn ($r) => response('ok'));

        $this->assertNull(
            $req->attributes->get('resolved_coach_id'),
            'unrecognised host must NOT stamp anything — platform fallback'
        );
    }

    /* ───────── BrandResolver hook ───────── */

    public function test_resolver_current_uses_stamped_coach_for_anonymous_request(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $row = CoachBrandSetting::firstOrCreateForCoach($coach->id);
        $row->update(['brand_name' => 'Tenant Brand']);

        // NO user logged in — only the host-stamp should drive resolution.
        auth()->logout();

        // Stamp the current request like the middleware would.
        request()->attributes->set('resolved_coach_id', $coach->id);

        $brand = app(BrandResolver::class)->current();
        $this->assertSame('Tenant Brand', $brand->name,
            'BrandResolver::current() must consume the host stamp before falling back to logged-in user');

        // Clean up so other tests aren't polluted.
        request()->attributes->remove('resolved_coach_id');
    }
}
