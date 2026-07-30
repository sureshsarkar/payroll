<?php

namespace Tests\Feature\Payment;

use App\Http\Middleware\RequiresEnterpriseMembership;
use App\Models\CoachPaymentGateway;
use App\Models\User;
use App\Services\Payment\CoachGatewayConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3 — Enterprise gate + coach gateway save security.
 *
 * Covers: the requires.enterprise middleware (Enterprise passes, others blocked
 * at the route, not just hidden), and CoachGatewayConfigService (encryption,
 * keep-blank-secret, charge clamp, unknown-gateway rejection).
 */
class CoachGatewayAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(string $tier = null): User
    {
        $id = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'C', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($tier) {
            $planId = DB::table('membership_plans')->insertGetId([
                'name' => ucfirst($tier) . uniqid(), 'slug' => $tier . uniqid(), 'tier' => $tier,
                'role' => 'instructor', 'price' => 100, 'duration_days' => 30, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('user_memberships')->insert([
                'user_id' => $id, 'plan_id' => $planId, 'status' => 'active', 'payment_status' => 'paid',
                'started_at' => now()->subDay(), 'expires_at' => now()->addYear(), 'price_paid' => 0,
                'payment_method' => 'test', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return User::find($id);
    }

    private function runMiddleware(?User $user, bool $json = false)
    {
        $req = Request::create('/instructor/payment-gateways', 'GET');
        if ($json) $req->headers->set('Accept', 'application/json');
        $req->setUserResolver(fn () => $user);
        $called = false;
        $res = (new RequiresEnterpriseMembership())->handle($req, function () use (&$called) {
            $called = true;
            return response('OK', 200);
        });
        return [$res, $called];
    }

    public function test_enterprise_coach_passes_gate(): void
    {
        [$res, $called] = $this->runMiddleware($this->coach('enterprise'));
        $this->assertTrue($called, 'Enterprise coach should pass the gate');
        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_starter_coach_blocked(): void
    {
        [$res, $called] = $this->runMiddleware($this->coach('starter'), json: true);
        $this->assertFalse($called, 'Non-Enterprise must be blocked');
        $this->assertSame(403, $res->getStatusCode());
        $this->assertStringContainsString('requires_enterprise', $res->getContent());
    }

    public function test_coach_without_plan_blocked(): void
    {
        [$res, $called] = $this->runMiddleware($this->coach(null), json: true);
        $this->assertFalse($called);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function test_unauthenticated_blocked(): void
    {
        [$res, $called] = $this->runMiddleware(null, json: true);
        $this->assertFalse($called);
        $this->assertSame(401, $res->getStatusCode());
    }

    /* ---- service: save security ---- */

    public function test_save_creates_encrypted_coach_row(): void
    {
        $coach = $this->coach('enterprise');
        $svc = app(CoachGatewayConfigService::class);

        $row = $svc->save($coach->id, CoachPaymentGateway::MANAGER_COACH, 'razorpay', [
            'status' => 'active', 'charge' => '2.5',
            'razorpay_key' => 'rzp_live_KEY', 'razorpay_secret' => 'SECRET_XYZ', 'razorpay_webhook_secret' => 'WH_1',
        ]);

        $this->assertSame(CoachPaymentGateway::MANAGER_COACH, $row->manager_type);
        $this->assertSame($coach->id, $row->coach_id);
        $this->assertSame('active', $row->status);
        $this->assertSame(2.5, $row->charge);
        $this->assertSame('SECRET_XYZ', $row->credentials['razorpay_secret']);

        $raw = DB::table('coach_payment_gateways')->where('id', $row->id)->value('credentials');
        $this->assertStringNotContainsString('SECRET_XYZ', $raw, 'must be encrypted at rest');
    }

    public function test_blank_secret_keeps_existing_value(): void
    {
        $coach = $this->coach('enterprise');
        $svc = app(CoachGatewayConfigService::class);
        $svc->save($coach->id, CoachPaymentGateway::MANAGER_COACH, 'razorpay', [
            'status' => 'active', 'razorpay_key' => 'K1', 'razorpay_secret' => 'ORIGINAL_SECRET',
        ]);
        // Re-save with blank secret (UI shows it masked) but changed name.
        $row = $svc->save($coach->id, CoachPaymentGateway::MANAGER_COACH, 'razorpay', [
            'status' => 'active', 'razorpay_key' => 'K1', 'razorpay_secret' => '', 'razorpay_name' => 'My Academy',
        ]);

        $this->assertSame('ORIGINAL_SECRET', $row->credentials['razorpay_secret'], 'blank secret keeps stored value');
        $this->assertSame('My Academy', $row->credentials['razorpay_name']);
    }

    public function test_charge_is_clamped(): void
    {
        $coach = $this->coach('enterprise');
        $row = app(CoachGatewayConfigService::class)->save($coach->id, CoachPaymentGateway::MANAGER_COACH, 'razorpay', [
            'status' => 'inactive', 'charge' => '999', 'razorpay_key' => 'k', 'razorpay_secret' => 's',
        ]);
        $this->assertSame(100.0, $row->charge);
    }

    public function test_unknown_gateway_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(CoachGatewayConfigService::class)->save(1, CoachPaymentGateway::MANAGER_COACH, 'not_a_gateway', ['status' => 'active']);
    }
}
