<?php

namespace Tests\Feature\Payment;

use App\Models\CoachPaymentGateway;
use App\Services\Payment\PaymentGatewayResolverService;
use App\Services\Payment\ResolvedGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coach-specific payment gateway resolver — priority + isolation + encryption.
 *
 * Priority under test:
 *   coach_self_managed (Enterprise only) > super_admin_for_coach > super_admin_default
 */
class PaymentGatewayResolverTest extends TestCase
{
    use DatabaseTransactions;

    private PaymentGatewayResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(PaymentGatewayResolverService::class);
    }

    /* ----------------------------- helpers ----------------------------- */

    private function makeCoach(): int
    {
        return DB::table('users')->insertGetId([
            'role'       => 'instructor',
            'name'       => 'Coach ' . uniqid(),
            'email'      => 'coach' . uniqid() . '@test.local',
            'password'   => bcrypt('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makePlan(string $tier): int
    {
        return DB::table('membership_plans')->insertGetId([
            'name'          => ucfirst($tier) . ' ' . uniqid(),
            'slug'          => $tier . uniqid(),
            'tier'          => $tier,
            'role'          => 'instructor',
            'price'         => 100,
            'duration_days' => 30,
            'status'        => 'active',
            'created_at'    => now(), 'updated_at' => now(),
        ]);
    }

    private function giveMembership(int $coachId, string $tier): void
    {
        $planId = $this->makePlan($tier);
        DB::table('user_memberships')->insert([
            'user_id'        => $coachId,
            'plan_id'        => $planId,
            'status'         => 'active',
            'payment_status' => 'paid',
            'started_at'     => now()->subDay(),
            'expires_at'     => now()->addYear(),
            'price_paid'     => 0,
            'payment_method' => 'test',
            'created_at'     => now(), 'updated_at' => now(),
        ]);
    }

    private function gateway(int $coachId, string $manager, string $status = 'active', array $creds = null): CoachPaymentGateway
    {
        return CoachPaymentGateway::create([
            'coach_id'     => $coachId,
            'gateway'      => 'razorpay',
            'manager_type' => $manager,
            'status'       => $status,
            'charge'       => 0,
            'credentials'  => $creds ?? ['razorpay_key' => 'rzp_' . $manager, 'razorpay_secret' => 'sec_' . $manager, 'razorpay_webhook_secret' => 'whk_' . $manager],
        ]);
    }

    /* ------------------------------ tests ------------------------------ */

    public function test_no_coach_config_falls_back_to_default(): void
    {
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'enterprise');

        $r = $this->resolver->resolve($coachId, 'razorpay');

        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType);
        $this->assertNull($r->configId);
    }

    public function test_admin_for_coach_used_for_non_enterprise_coach(): void
    {
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'starter');
        $cfg = $this->gateway($coachId, CoachPaymentGateway::MANAGER_ADMIN);

        $r = $this->resolver->resolve($coachId, 'razorpay');

        $this->assertSame(ResolvedGateway::OWNER_ADMIN_COACH, $r->ownerType);
        $this->assertSame($cfg->id, $r->configId);
        $this->assertSame('rzp_admin', $r->credential('razorpay_key'));
        $this->assertSame('whk_admin', $r->webhookSecret);
    }

    public function test_coach_self_managed_used_for_enterprise_coach(): void
    {
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'enterprise');
        $cfg = $this->gateway($coachId, CoachPaymentGateway::MANAGER_COACH);

        $r = $this->resolver->resolve($coachId, 'razorpay');

        $this->assertSame(ResolvedGateway::OWNER_COACH_SELF, $r->ownerType);
        $this->assertSame($cfg->id, $r->configId);
        $this->assertSame($coachId, $r->coachId);
    }

    public function test_coach_self_preferred_over_admin_when_both_exist(): void
    {
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'enterprise');
        $this->gateway($coachId, CoachPaymentGateway::MANAGER_ADMIN);
        $self = $this->gateway($coachId, CoachPaymentGateway::MANAGER_COACH);

        $r = $this->resolver->resolve($coachId, 'razorpay');

        $this->assertSame(ResolvedGateway::OWNER_COACH_SELF, $r->ownerType);
        $this->assertSame($self->id, $r->configId);
    }

    public function test_non_enterprise_cannot_use_self_managed_row(): void
    {
        // Coach is NOT enterprise but somehow has a self row -> must be ignored,
        // falls through to default (no admin row here).
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'starter');
        $this->gateway($coachId, CoachPaymentGateway::MANAGER_COACH);

        $r = $this->resolver->resolve($coachId, 'razorpay');

        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType);
    }

    public function test_inactive_coach_row_falls_back(): void
    {
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'enterprise');
        $this->gateway($coachId, CoachPaymentGateway::MANAGER_COACH, 'inactive');

        $r = $this->resolver->resolve($coachId, 'razorpay');

        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType);
    }

    public function test_missing_required_credentials_falls_back(): void
    {
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'enterprise');
        // razorpay_secret missing -> not usable
        $this->gateway($coachId, CoachPaymentGateway::MANAGER_COACH, 'active', ['razorpay_key' => 'only_key']);

        $r = $this->resolver->resolve($coachId, 'razorpay');

        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType);
    }

    public function test_mixed_cart_null_coach_uses_default(): void
    {
        $r = $this->resolver->resolve(null, 'razorpay');
        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType);
    }

    public function test_resolve_for_order_reuses_stored_metadata(): void
    {
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'enterprise');
        $cfg = $this->gateway($coachId, CoachPaymentGateway::MANAGER_COACH);

        $order = new \Modules\Order\app\Models\Order([
            'payment_method'     => 'razorpay',
            'gateway_owner_type' => ResolvedGateway::OWNER_COACH_SELF,
            'gateway_config_id'  => $cfg->id,
            'gateway_coach_id'   => $coachId,
        ]);

        $r = $this->resolver->resolveForOrder($order);

        $this->assertSame(ResolvedGateway::OWNER_COACH_SELF, $r->ownerType);
        $this->assertSame('whk_coach', $r->webhookSecret);
    }

    public function test_legacy_order_without_metadata_uses_default(): void
    {
        $order = new \Modules\Order\app\Models\Order(['payment_method' => 'razorpay']);
        $r = $this->resolver->resolveForOrder($order);
        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType);
    }

    public function test_resolve_for_order_falls_to_default_when_stamped_config_deleted(): void
    {
        // M2 — if the exact stamped config row was deleted, NEVER tier-rematch to
        // a different (possibly different-account) row; fail safe to the default.
        $coachId = $this->makeCoach();
        $this->giveMembership($coachId, 'enterprise');
        $cfg = $this->gateway($coachId, CoachPaymentGateway::MANAGER_COACH);

        $order = new \Modules\Order\app\Models\Order([
            'payment_method'     => 'razorpay',
            'gateway_owner_type' => ResolvedGateway::OWNER_COACH_SELF,
            'gateway_config_id'  => $cfg->id,
            'gateway_coach_id'   => $coachId,
        ]);

        // Coach deletes that config and creates a NEW one (different row id).
        $cfg->delete();
        $this->gateway($coachId, CoachPaymentGateway::MANAGER_COACH);

        $r = $this->resolver->resolveForOrder($order);
        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType, 'must not rematch a different row');
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $coachId = $this->makeCoach();
        $cfg = $this->gateway($coachId, CoachPaymentGateway::MANAGER_ADMIN, 'active', ['razorpay_key' => 'PLAINTEXT_KEY_123', 'razorpay_secret' => 'PLAINTEXT_SECRET_XYZ']);

        $raw = DB::table('coach_payment_gateways')->where('id', $cfg->id)->value('credentials');

        $this->assertStringNotContainsString('PLAINTEXT_SECRET_XYZ', $raw, 'secret must be encrypted at rest');
        $this->assertStringNotContainsString('PLAINTEXT_KEY_123', $raw, 'key must be encrypted at rest');

        // ...but reads back decrypted via the model
        $this->assertSame('PLAINTEXT_SECRET_XYZ', $cfg->fresh()->credentials['razorpay_secret']);
    }

    public function test_default_tier_returns_same_global_credentials_as_before(): void
    {
        // Backward-compat guarantee: with NO coach config, the resolver's default
        // tier must hand back exactly the global creds the old code read via
        // get_payment_gateway_info(), so existing checkout is unchanged.
        DB::table('payment_gateways')->updateOrInsert(['key' => 'razorpay_key'], ['value' => 'GLOBAL_RZP_KEY']);
        DB::table('payment_gateways')->updateOrInsert(['key' => 'razorpay_secret'], ['value' => 'GLOBAL_RZP_SECRET']);
        \Illuminate\Support\Facades\Cache::forget('payment_setting');

        $r = $this->resolver->resolve(null, 'razorpay');

        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType);
        $this->assertSame('GLOBAL_RZP_KEY', $r->credential('razorpay_key'));
        $this->assertSame('GLOBAL_RZP_SECRET', $r->credential('razorpay_secret'));
    }

    public function test_masked_credentials_hide_secrets(): void
    {
        $coachId = $this->makeCoach();
        $cfg = $this->gateway($coachId, CoachPaymentGateway::MANAGER_ADMIN, 'active', ['razorpay_key' => 'rzp_live_abcd1234', 'razorpay_secret' => 'supersecret9999', 'razorpay_name' => 'My Academy']);

        $masked = $cfg->maskedCredentials();

        $this->assertStringEndsWith('1234', $masked['razorpay_key']);
        $this->assertStringNotContainsString('supersecret', $masked['razorpay_secret']);
        $this->assertSame('My Academy', $masked['razorpay_name'], 'non-secret fields stay visible');
    }
}
