<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Verifies the audit's payment-webhook guarantees.
 *
 * The big find: every webhook controller imported `App\Models\Order` —
 * a class that does not exist (the real Order is at
 * Modules\Order\app\Models\Order). Every signature-verified payment
 * notification was crashing in `Order::find()` with "Class not found",
 * so customers paid but orders never got marked paid. Tests guard the
 * import-shape going forward.
 *
 * Also asserts:
 *  - Every webhook route is throttled (route-level rate limit)
 *  - Each Stripe / Razorpay / PayPal handler still calls the right
 *    signature-verification primitive (regression guard against someone
 *    "simplifying" the verify step away)
 *  - bKash / MercadoPago handlers continue to do the API re-query
 *    (their signature equivalent — neither gateway sends an HMAC)
 */
class WebhookTest extends TestCase
{
    use DatabaseTransactions;

    public function test_order_class_used_by_webhooks_actually_exists(): void
    {
        // The Order model lives in the Order module, not under App\Models.
        $this->assertFalse(class_exists(\App\Models\Order::class),
            'App\\Models\\Order does NOT exist — webhook handlers must import Modules\\Order\\app\\Models\\Order');
        $this->assertTrue(class_exists(\Modules\Order\app\Models\Order::class),
            'Modules\\Order\\app\\Models\\Order is the canonical Order model');
    }

    /** @dataProvider webhookControllers */
    public function test_webhook_controller_imports_the_real_order_model(string $relPath, bool $stripeStyle): void
    {
        $src = file_get_contents(app_path($relPath));

        // Either an explicit `use Modules\Order\app\Models\Order;` import
        // (Razorpay/bKash/PayPal/MercadoPago) or a fully-qualified
        // reference (Stripe). Reject ANY mention of App\Models\Order.
        $this->assertStringNotContainsString(
            'App\\Models\\Order',
            $src,
            "$relPath must NOT reference App\\Models\\Order — that class does not exist"
        );

        if ($stripeStyle) {
            // Stripe controller used to fully-qualify the call.
            $this->assertTrue(
                str_contains($src, 'use Modules\\Order\\app\\Models\\Order;')
                    || str_contains($src, '\\Modules\\Order\\app\\Models\\Order::find'),
                "$relPath must reference Modules\\Order\\app\\Models\\Order"
            );
        } else {
            $this->assertStringContainsString(
                'use Modules\\Order\\app\\Models\\Order;',
                $src,
                "$relPath must `use Modules\\Order\\app\\Models\\Order;`"
            );
        }
    }

    public static function webhookControllers(): array
    {
        return [
            'Stripe'      => ['Http/Controllers/Webhook/StripeWebhookController.php',      true],
            'Razorpay'    => ['Http/Controllers/Webhook/RazorpayWebhookController.php',    false],
            'bKash'       => ['Http/Controllers/Webhook/BkashWebhookController.php',       false],
            'PayPal'      => ['Http/Controllers/Webhook/PaypalWebhookController.php',      false],
            'MercadoPago' => ['Http/Controllers/Webhook/MercadoPagoWebhookController.php', false],
        ];
    }

    /**
     * The non-webhook payment + checkout controllers also touch Order rows
     * (PaymentController::payment_success() locks + updates the row). They
     * MUST resolve Order via Modules\Order\app\Models\Order — never the
     * non-existent App\Models\Order. Regression seen 2026-05-26: line 973
     * of PaymentController used \App\Models\Order::lockForUpdate() →
     * payment-success route 500'd. This guard catches future re-occurrences.
     */
    public function test_payment_controllers_use_real_order_model(): void
    {
        $files = [
            base_path('Modules/BasicPayment/app/Http/Controllers/PaymentController.php'),
            base_path('Modules/Order/app/Http/Controllers/CheckoutController.php'),
        ];
        foreach ($files as $abs) {
            if (! is_file($abs)) {
                continue;   // module not installed — skip
            }
            $src = file_get_contents($abs);
            $this->assertDoesNotMatchRegularExpression(
                '/\\\\?App\\\\Models\\\\Order(?:::|;|\s|$)/',
                $src,
                basename($abs) . ' must NOT reference App\\Models\\Order — that class does not exist in this codebase. Use Modules\\Order\\app\\Models\\Order instead.'
            );
        }
    }

    public function test_stripe_handler_still_uses_constructEvent_for_signature(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Webhook/StripeWebhookController.php'));
        $this->assertStringContainsString('Stripe\\Webhook::constructEvent', $src,
            'Stripe handler must use the official constructEvent() — DO NOT replace with a hand-rolled HMAC');
        $this->assertStringContainsString('STRIPE_WEBHOOK_SECRET', $src,
            'Stripe handler must read STRIPE_WEBHOOK_SECRET from env / services config');
    }

    public function test_razorpay_uses_timing_safe_signature_compare(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Webhook/RazorpayWebhookController.php'));
        $this->assertStringContainsString("hash_hmac('sha256'", $src);
        $this->assertStringContainsString('hash_equals(', $src,
            'Razorpay must use hash_equals() for timing-safe compare — never == on signatures');
    }

    public function test_paypal_uses_official_verification_endpoint(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Webhook/PaypalWebhookController.php'));
        $this->assertStringContainsString('verify-webhook-signature', $src,
            'PayPal handler must call /v1/notifications/verify-webhook-signature');
        $this->assertStringContainsString("'verification_status'", $src);
    }

    public function test_bkash_does_api_requery_for_payment_status(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Webhook/BkashWebhookController.php'));
        // bKash has no inbound signature; the controller MUST re-query
        // /payment/status and gate on statusCode='0000' + transactionStatus='Completed'.
        $this->assertStringContainsString('payment/status', $src);
        $this->assertStringContainsString("'0000'", $src);
        $this->assertStringContainsString("'Completed'", $src);
    }

    public function test_mercadopago_does_api_requery_for_payment_status(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Webhook/MercadoPagoWebhookController.php'));
        $this->assertStringContainsString('api.mercadopago.com/v1/payments/', $src);
        $this->assertStringContainsString("'approved'", $src,
            'MercadoPago handler must gate on status=approved (not pending/in_process/etc.)');
    }

    public function test_every_webhook_route_is_throttled(): void
    {
        $names = [
            'webhooks.stripe',
            'webhooks.razorpay',
            'webhooks.bkash',
            'webhooks.paypal',
            'webhooks.mercadopago',
        ];
        $routes = RouteFacade::getRoutes()->getRoutesByName();
        foreach ($names as $name) {
            $this->assertArrayHasKey($name, $routes, "route '$name' not registered");
            /** @var Route $route */
            $route = $routes[$name];
            $hasThrottle = (bool) array_filter($route->gatherMiddleware(),
                fn ($m) => str_starts_with((string) $m, 'throttle'));
            $this->assertTrue($hasThrottle,
                "$name must be throttled — even signature-verified webhooks need a burst-spam ceiling");
        }
    }

    public function test_no_signature_webhooks_carry_tighter_throttle(): void
    {
        // bKash + MercadoPago lack an inbound signature; an attacker
        // spamming them burns our gateway API quota. Their throttle
        // should be tighter than the signature-verified gateways.
        $routes = RouteFacade::getRoutes()->getRoutesByName();

        $bkashMw = $routes['webhooks.bkash']->gatherMiddleware();
        $this->assertContains('throttle:30,1', $bkashMw,
            'bKash route must use throttle:30,1 (no signature, API-requery only)');

        $mpMw = $routes['webhooks.mercadopago']->gatherMiddleware();
        $this->assertContains('throttle:30,1', $mpMw,
            'MercadoPago route must use throttle:30,1 (no signature, API-requery only)');
    }
}
