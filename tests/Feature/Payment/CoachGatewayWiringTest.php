<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;

/**
 * Guard: EVERY student-checkout entry point + every webhook for the wired core
 * gateways must route through the coach-gateway resolver. This catches the exact
 * class of bug where one checkout path (e.g. the desktop-web PaymentController)
 * is left reading global credentials, so coach gateways silently never apply.
 */
class CoachGatewayWiringTest extends TestCase
{
    private function src(string $rel): string
    {
        return file_get_contents(base_path($rel));
    }

    /** @return array<string,string[]> file => required markers */
    public static function checkoutSeams(): array
    {
        return [
            // Desktop web checkout (the one the bug was in).
            'Modules/BasicPayment/app/Http/Controllers/PaymentController.php' => [
                'CheckoutGatewayResolver', 'gateway_owner_type', 'resolveGatewayForSessionOrder',
            ],
            // Mobile webview checkout.
            'Modules/BasicPayment/app/Http/Controllers/API/PaymentController.php' => [
                'CheckoutGatewayResolver', 'gateway_owner_type', 'resolveGatewayForSessionOrder',
            ],
            // Mobile single-course + cart (Razorpay).
            'app/Http/Controllers/API/CoursePurchaseController.php' => [
                'PaymentGatewayResolverService', 'gateway_owner_type',
            ],
            'app/Http/Controllers/API/CartCheckoutController.php' => [
                'CheckoutGatewayResolver', 'gateway_owner_type',
            ],
            // PayPal web init.
            'Modules/BasicPayment/app/Http/Controllers/API/PaypalPaymentController.php' => [
                'resolveGatewayForSessionOrder',
            ],
            // Tenant-aware webhooks.
            'app/Http/Controllers/Webhook/StripeWebhookController.php'   => ['resolveWebhookSecret'],
            'app/Http/Controllers/Webhook/RazorpayWebhookController.php' => ['resolveWebhookSecret'],
            'app/Http/Controllers/Webhook/PaypalWebhookController.php'   => ['resolvePaypalContext'],
        ];
    }

    public function test_every_checkout_and_webhook_seam_is_wired_to_the_resolver(): void
    {
        foreach (self::checkoutSeams() as $file => $markers) {
            $src = $this->src($file);
            foreach ($markers as $marker) {
                $this->assertStringContainsString(
                    $marker,
                    $src,
                    "$file is missing coach-gateway wiring marker '$marker' — a checkout/webhook path is NOT routed through the resolver, so coach gateways will silently not apply there."
                );
            }
        }
    }

    public function test_no_core_gateway_init_reads_global_credentials_directly_in_web_checkout(): void
    {
        // In the desktop-web controller, the Razorpay/Stripe/PayPal init methods
        // must NOT read get_basic_payment_info()/get_payment_gateway_info()
        // directly anymore (only the non-core mollie/instamojo may).
        $src = $this->src('Modules/BasicPayment/app/Http/Controllers/PaymentController.php');

        // pay_via_stripe / pay_via_razorpay / pay_via_paypal bodies must reference
        // the resolver, not the global trait readers.
        foreach (['pay_via_stripe', 'pay_via_razorpay', 'pay_via_paypal'] as $method) {
            $pos = strpos($src, "function $method(");
            $this->assertNotFalse($pos, "$method not found");
            $body = substr($src, $pos, 600);
            $this->assertStringContainsString('resolveGatewayForSessionOrder', $body,
                "$method must resolve the coach gateway, not read global credentials");
        }
    }
}
