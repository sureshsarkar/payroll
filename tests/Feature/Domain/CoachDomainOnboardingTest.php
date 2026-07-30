<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\CoachDomainController;
use App\Models\CoachDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

/**
 * Per-coach white-label — Phase 5 (custom-domain onboarding).
 *
 * Contracts tested:
 *
 *   1. Add domain creates an UNVERIFIED row (custom kind).
 *   2. Add domain validates the hostname format + uniqueness.
 *   3. Verify endpoint requires TXT match — succeeds when the
 *      record contains the coach's stable token.
 *   4. Verify endpoint fails cleanly when the record is missing.
 *   5. Coach can only verify / delete domains they own (IDOR gate).
 *   6. Subdomain rows cannot be deleted (platform-managed).
 *   7. tokenFor() is stable across calls (so the coach can leave
 *      the page and come back to a working DNS instruction).
 */
class CoachDomainOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    /* ───────── add (store) ───────── */

    public function test_store_creates_unverified_custom_domain_row(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        Auth::guard('web')->loginUsingId($coach->id);

        $ctrl = app(CoachDomainController::class);
        $req = Request::create('/x', 'POST', ['hostname' => 'acme.example.test']);
        $req->setLaravelSession(app('session.store'));
        $ctrl->store($req);

        $row = CoachDomain::where('coach_id', $coach->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('acme.example.test', $row->hostname);
        $this->assertSame('custom', $row->kind);
        $this->assertNull($row->verified_at, 'new custom domain must start unverified');
    }

    public function test_store_normalises_hostname(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        Auth::guard('web')->loginUsingId($coach->id);

        $ctrl = app(CoachDomainController::class);
        $req = Request::create('/x', 'POST', ['hostname' => 'https://MixedCase.Example.test:443/path']);
        $req->setLaravelSession(app('session.store'));
        $ctrl->store($req);

        $row = CoachDomain::where('coach_id', $coach->id)->first();
        $this->assertSame('mixedcase.example.test', $row->hostname);
    }

    public function test_store_rejects_invalid_hostname(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        Auth::guard('web')->loginUsingId($coach->id);

        $ctrl = app(CoachDomainController::class);
        $req = Request::create('/x', 'POST', ['hostname' => 'no-tld-here']);
        $req->setLaravelSession(app('session.store'));
        $resp = $ctrl->store($req);

        $this->assertSame(0, CoachDomain::where('coach_id', $coach->id)->count(),
            'no row should be created for an invalid hostname');
    }

    public function test_store_rejects_hostname_already_claimed_by_another_coach(): void
    {
        $coachA = User::factory()->create(['role' => 'instructor']);
        $coachB = User::factory()->create(['role' => 'instructor']);
        CoachDomain::create([
            'coach_id' => $coachA->id, 'hostname' => 'taken.example.test',
            'kind' => 'custom', 'verified_at' => now(),
        ]);

        Auth::guard('web')->loginUsingId($coachB->id);
        $ctrl = app(CoachDomainController::class);
        $req = Request::create('/x', 'POST', ['hostname' => 'taken.example.test']);
        $req->setLaravelSession(app('session.store'));
        $ctrl->store($req);

        $this->assertSame(0, CoachDomain::where('coach_id', $coachB->id)->count(),
            'coach B must not be able to claim coach A\'s hostname');
    }

    /* ───────── verify ───────── */

    public function test_verify_succeeds_when_txt_record_contains_token(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        Auth::guard('web')->loginUsingId($coach->id);

        $row = CoachDomain::create([
            'coach_id' => $coach->id, 'hostname' => 'verify.example.test',
            'kind' => 'custom', 'verified_at' => null,
        ]);

        // Mock the DNS lookup to return a TXT record carrying the
        // coach's token. dns_get_record's response shape is an array
        // of associative arrays — each row has a 'txt' key.
        $token = (app(CoachDomainController::class))->tokenFor($row);

        $stub = Mockery::mock(CoachDomainController::class)->makePartial();
        $stub->shouldAllowMockingProtectedMethods();
        $stub->shouldReceive('lookupTxt')->andReturn([
            ['type' => 'TXT', 'txt' => 'random-other-token=abc123'],
            ['type' => 'TXT', 'txt' => $token],
        ]);

        $resp = $stub->verify($row->id);
        $j = json_decode($resp->getContent(), true);

        $this->assertTrue($j['ok'], 'verify must succeed when token is in TXT records');
        $this->assertNotNull($row->fresh()->verified_at);
    }

    public function test_verify_fails_when_txt_record_missing(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        Auth::guard('web')->loginUsingId($coach->id);

        $row = CoachDomain::create([
            'coach_id' => $coach->id, 'hostname' => 'miss.example.test',
            'kind' => 'custom', 'verified_at' => null,
        ]);

        $stub = Mockery::mock(CoachDomainController::class)->makePartial();
        $stub->shouldAllowMockingProtectedMethods();
        $stub->shouldReceive('lookupTxt')->andReturn([
            ['type' => 'TXT', 'txt' => 'some-other-record=xyz'],
        ]);

        $resp = $stub->verify($row->id);
        $j = json_decode($resp->getContent(), true);

        $this->assertFalse($j['ok']);
        $this->assertNull($row->fresh()->verified_at,
            'verify must NOT mark verified_at on a miss');
    }

    /* ───────── IDOR ───────── */

    public function test_verify_404s_when_coach_doesnt_own_the_domain(): void
    {
        $coachA = User::factory()->create(['role' => 'instructor']);
        $coachB = User::factory()->create(['role' => 'instructor']);
        $row = CoachDomain::create([
            'coach_id' => $coachA->id, 'hostname' => 'a.example.test',
            'kind' => 'custom', 'verified_at' => null,
        ]);

        Auth::guard('web')->loginUsingId($coachB->id);
        $ctrl = app(CoachDomainController::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $ctrl->verify($row->id);
    }

    public function test_destroy_blocks_subdomain_deletion(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $row = CoachDomain::create([
            'coach_id' => $coach->id, 'hostname' => 'auto.platform.test',
            'kind' => 'subdomain', 'verified_at' => now(),
        ]);

        Auth::guard('web')->loginUsingId($coach->id);
        $ctrl = app(CoachDomainController::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $ctrl->destroy($row->id);
    }

    /* ───────── tokenFor() ───────── */

    public function test_token_for_is_stable_across_calls(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $row = CoachDomain::create([
            'coach_id' => $coach->id, 'hostname' => 'stable.example.test',
            'kind' => 'custom', 'verified_at' => null,
        ]);
        $ctrl = app(CoachDomainController::class);

        $a = $ctrl->tokenFor($row);
        $b = $ctrl->tokenFor($row->fresh());

        $this->assertSame($a, $b, 'token must be deterministic so the coach can come back later');
        $this->assertStringStartsWith('mbsguru-verify=', $a);
        $this->assertGreaterThanOrEqual(20, strlen($a));
    }
}
