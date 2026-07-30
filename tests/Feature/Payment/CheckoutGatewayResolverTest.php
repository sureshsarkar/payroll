<?php

namespace Tests\Feature\Payment;

use App\Models\CoachPaymentGateway;
use App\Services\Payment\CheckoutGatewayResolver;
use App\Services\Payment\ResolvedGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Checkout-facing resolver: single-coach rule + order stamping.
 * Mixed multi-coach carts MUST fall back to the global default (money safety).
 */
class CheckoutGatewayResolverTest extends TestCase
{
    use DatabaseTransactions;

    private CheckoutGatewayResolver $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(CheckoutGatewayResolver::class);
    }

    private function enterpriseCoachWithRazorpay(): int
    {
        $coachId = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'C', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $planId = DB::table('membership_plans')->insertGetId([
            'name' => 'E' . uniqid(), 'slug' => 'e' . uniqid(), 'tier' => 'enterprise',
            'role' => 'instructor', 'price' => 100, 'duration_days' => 30, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_memberships')->insert([
            'user_id' => $coachId, 'plan_id' => $planId, 'status' => 'active', 'payment_status' => 'paid',
            'started_at' => now()->subDay(), 'expires_at' => now()->addYear(), 'price_paid' => 0,
            'payment_method' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        CoachPaymentGateway::create([
            'coach_id' => $coachId, 'gateway' => 'razorpay', 'manager_type' => CoachPaymentGateway::MANAGER_COACH,
            'status' => 'active', 'credentials' => ['razorpay_key' => 'k', 'razorpay_secret' => 's', 'razorpay_webhook_secret' => 'w'],
        ]);
        return $coachId;
    }

    public function test_single_coach_id_detection(): void
    {
        $this->assertSame(7, $this->svc->singleCoachId([7, 7, 7]));
        $this->assertNull($this->svc->singleCoachId([7, 9]));      // mixed
        $this->assertNull($this->svc->singleCoachId([]));          // empty
        $this->assertSame(7, $this->svc->singleCoachId([7, 0, null])); // junk ignored
    }

    public function test_single_coach_cart_uses_coach_gateway(): void
    {
        $coachId = $this->enterpriseCoachWithRazorpay();
        $r = $this->svc->resolveForOwners([$coachId, $coachId], 'razorpay');
        $this->assertSame(ResolvedGateway::OWNER_COACH_SELF, $r->ownerType);
    }

    public function test_mixed_cart_uses_default_gateway(): void
    {
        $coachId = $this->enterpriseCoachWithRazorpay();
        $other   = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'O', 'email' => 'o' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $r = $this->svc->resolveForOwners([$coachId, $other], 'razorpay');
        $this->assertSame(ResolvedGateway::OWNER_DEFAULT, $r->ownerType, 'mixed cart must not route to one coach');
    }

    public function test_stamp_persists_provenance(): void
    {
        $coachId = $this->enterpriseCoachWithRazorpay();
        $r = $this->svc->resolveForOwner($coachId, 'razorpay');

        $buyer = DB::table('users')->insertGetId([
            'role' => 'student', 'name' => 'B', 'email' => 'b' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = Order::create([
            'invoice_id' => 'INV' . uniqid(), 'buyer_id' => $buyer, 'seller_id' => $coachId,
            'status' => 'pending', 'payment_method' => 'razorpay', 'payment_status' => 'pending',
            'payable_amount' => 100,
        ]);

        $this->svc->stamp($order, $r);

        $fresh = Order::find($order->id);
        $this->assertSame(ResolvedGateway::OWNER_COACH_SELF, $fresh->gateway_owner_type);
        $this->assertSame($r->configId, (int) $fresh->gateway_config_id);
        $this->assertSame($coachId, (int) $fresh->gateway_coach_id);
    }
}
