<?php

namespace Tests\Feature\Payment;

use App\Http\Controllers\Webhook\RazorpayWebhookController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Models\CoachPaymentGateway;
use App\Services\Payment\ResolvedGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Phase 3 — webhook secret routing.
 *
 * A webhook for an order paid through a coach gateway MUST verify with that
 * coach's webhook secret; a default/legacy order verifies with the platform
 * secret. The unverified payload is read ONLY to route; the signature check
 * (covered by the gateways' own verify paths) still enforces trust.
 */
class WebhookSecretRoutingTest extends TestCase
{
    use DatabaseTransactions;

    private function enterpriseCoachWith(string $gateway, array $creds): array
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
        $cfg = CoachPaymentGateway::create([
            'coach_id' => $coachId, 'gateway' => $gateway, 'manager_type' => CoachPaymentGateway::MANAGER_COACH,
            'status' => 'active', 'credentials' => $creds,
        ]);
        return [$coachId, $cfg];
    }

    private function order(string $gateway, string $ownerType, ?int $coachId, ?int $configId): Order
    {
        $buyer = DB::table('users')->insertGetId([
            'role' => 'student', 'name' => 'B', 'email' => 'b' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return Order::create([
            'invoice_id' => 'INV' . uniqid(), 'buyer_id' => $buyer, 'status' => 'pending',
            'payment_method' => $gateway, 'payment_status' => 'pending', 'payable_amount' => 100,
            'gateway_owner_type' => $ownerType, 'gateway_coach_id' => $coachId, 'gateway_config_id' => $configId,
        ]);
    }

    private function callResolve(object $controller, string $payload): ?string
    {
        $m = new \ReflectionMethod($controller, 'resolveWebhookSecret');
        $m->setAccessible(true);
        return $m->invoke($controller, $payload);
    }

    public function test_stripe_coach_order_routes_to_coach_secret(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_GLOBAL']);
        [$coachId, $cfg] = $this->enterpriseCoachWith('stripe', [
            'stripe_key' => 'pk', 'stripe_secret' => 'sk', 'stripe_webhook_secret' => 'whsec_COACH',
        ]);
        $order = $this->order('stripe', ResolvedGateway::OWNER_COACH_SELF, $coachId, $cfg->id);

        $payload = json_encode(['data' => ['object' => ['metadata' => ['order_id' => (string) $order->id]]]]);
        $secret  = $this->callResolve(app(StripeWebhookController::class), $payload);

        $this->assertSame('whsec_COACH', $secret);
    }

    public function test_stripe_default_order_routes_to_global_secret(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_GLOBAL']);
        $order = $this->order('stripe', ResolvedGateway::OWNER_DEFAULT, null, null);

        $payload = json_encode(['data' => ['object' => ['metadata' => ['order_id' => (string) $order->id]]]]);
        $secret  = $this->callResolve(app(StripeWebhookController::class), $payload);

        $this->assertSame('whsec_GLOBAL', $secret);
    }

    public function test_stripe_unknown_order_falls_back_to_global(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_GLOBAL']);
        $payload = json_encode(['data' => ['object' => ['metadata' => ['order_id' => '999999999']]]]);
        $secret  = $this->callResolve(app(StripeWebhookController::class), $payload);
        $this->assertSame('whsec_GLOBAL', $secret);
    }

    public function test_razorpay_coach_order_routes_to_coach_secret(): void
    {
        config(['services.razorpay.webhook_secret' => 'rzp_GLOBAL']);
        [$coachId, $cfg] = $this->enterpriseCoachWith('razorpay', [
            'razorpay_key' => 'k', 'razorpay_secret' => 's', 'razorpay_webhook_secret' => 'rzp_COACH',
        ]);
        $order = $this->order('razorpay', ResolvedGateway::OWNER_COACH_SELF, $coachId, $cfg->id);

        $payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_x', 'notes' => ['order_id' => (string) $order->id],
        ]]]]);
        $secret = $this->callResolve(app(RazorpayWebhookController::class), $payload);

        $this->assertSame('rzp_COACH', $secret);
    }

    public function test_razorpay_fee_payment_uses_global_secret(): void
    {
        // Fee payments (notes.fee_demand_id, no order_id) are platform fees → global.
        config(['services.razorpay.webhook_secret' => 'rzp_GLOBAL']);
        $payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_x', 'notes' => ['fee_demand_id' => '5', 'student_id' => '9'],
        ]]]]);
        $secret = $this->callResolve(app(RazorpayWebhookController::class), $payload);
        $this->assertSame('rzp_GLOBAL', $secret);
    }

    public function test_paypal_coach_order_routes_to_coach_context(): void
    {
        config(['services.paypal.webhook_id' => 'WH_GLOBAL']);
        [$coachId, $cfg] = $this->enterpriseCoachWith('paypal', [
            'paypal_client_id' => 'CID_COACH', 'paypal_secret_key' => 'CSEC_COACH',
            'paypal_account_mode' => 'live', 'paypal_webhook_id' => 'WH_COACH',
        ]);
        $order = $this->order('paypal', ResolvedGateway::OWNER_COACH_SELF, $coachId, $cfg->id);

        $event = ['resource' => ['custom_id' => (string) $order->id]];
        $ctrl  = app(\App\Http\Controllers\Webhook\PaypalWebhookController::class);
        $m = new \ReflectionMethod($ctrl, 'resolvePaypalContext');
        $m->setAccessible(true);
        $ctx = $m->invoke($ctrl, $event);

        $this->assertSame('WH_COACH', $ctx['webhook_id']);
        $this->assertSame('CID_COACH', $ctx['client_id']);
        $this->assertSame('live', $ctx['mode']);
    }

    public function test_paypal_default_order_uses_global_context(): void
    {
        config(['services.paypal.webhook_id' => 'WH_GLOBAL']);
        $order = $this->order('paypal', ResolvedGateway::OWNER_DEFAULT, null, null);

        $event = ['resource' => ['custom_id' => (string) $order->id]];
        $ctrl  = app(\App\Http\Controllers\Webhook\PaypalWebhookController::class);
        $m = new \ReflectionMethod($ctrl, 'resolvePaypalContext');
        $m->setAccessible(true);
        $ctx = $m->invoke($ctrl, $event);

        $this->assertSame('WH_GLOBAL', $ctx['webhook_id']);
        $this->assertNull($ctx['client_id'], 'default uses env client creds, not coach');
    }

    public function test_paypal_coach_without_webhook_id_is_unverifiable_not_mixed(): void
    {
        // M1 — coach configured PayPal (client creds) but left Webhook ID blank.
        // Must NOT mix the platform webhook id with coach creds (that always fails
        // and triggers endless retries); flag as unverifiable so the handler 200s.
        config(['services.paypal.webhook_id' => 'WH_GLOBAL']);
        [$coachId, $cfg] = $this->enterpriseCoachWith('paypal', [
            'paypal_client_id' => 'CID', 'paypal_secret_key' => 'CSEC', 'paypal_account_mode' => 'live',
            // no paypal_webhook_id
        ]);
        $order = $this->order('paypal', ResolvedGateway::OWNER_COACH_SELF, $coachId, $cfg->id);

        $event = ['resource' => ['custom_id' => (string) $order->id]];
        $ctrl  = app(\App\Http\Controllers\Webhook\PaypalWebhookController::class);
        $m = new \ReflectionMethod($ctrl, 'resolvePaypalContext');
        $m->setAccessible(true);
        $ctx = $m->invoke($ctrl, $event);

        $this->assertTrue($ctx['coach_unverifiable']);
        $this->assertNull($ctx['client_id'], 'must not leak coach creds when unverifiable');
    }
}
