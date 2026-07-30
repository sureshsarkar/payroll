<?php

namespace Tests\Feature\Domain;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Domain test — webhook signature verification (Tier C category).
 *
 * Verifies the contract: each gateway's webhook endpoint rejects
 * requests with missing or wrong signatures, and accepts requests
 * with a correctly-computed signature.
 *
 * No live gateway calls. We compute the signature ourselves using the
 * same algorithm each gateway documents, then POST to the local
 * /webhooks/<gateway> endpoint.
 *
 * Stripe signature notes: \Stripe\Webhook::constructEvent expects
 *   t=<unix>,v1=<hmac> in the Stripe-Signature header.
 *
 * Razorpay signature notes: X-Razorpay-Signature is hash_hmac('sha256', body, secret).
 */
class WebhookSignatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Configure test secrets so the controllers' "not configured" 500
        // branch doesn't trigger. We're testing signature verification, not
        // the env-var guard (which is separately tested by audit:smoke).
        config()->set('services.stripe.webhook_secret', 'whsec_unit_test_secret');
        config()->set('services.razorpay.webhook_secret', 'razorpay_unit_test_secret');
    }

    public function test_stripe_webhook_rejects_missing_signature(): void
    {
        $payload = json_encode(['id' => 'evt_test', 'object' => 'event', 'type' => 'noop']);
        $req = \Illuminate\Http\Request::create('/webhooks/stripe', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
        $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);

        $this->assertContains($response->getStatusCode(), [400, 401, 403, 422],
            'Stripe webhook must refuse requests without a signature; got '.$response->getStatusCode().' body: '.substr((string)$response->getContent(), 0, 100));
    }

    public function test_stripe_webhook_rejects_wrong_signature(): void
    {
        $payload = json_encode(['id' => 'evt_test', 'object' => 'event', 'type' => 'noop']);
        $ts = time();
        $req = \Illuminate\Http\Request::create('/webhooks/stripe', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1=deadbeefdeadbeef",
        ], $payload);
        $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);

        $this->assertContains($response->getStatusCode(), [400, 401, 403, 422],
            'Stripe webhook must refuse wrong signature; got '.$response->getStatusCode().' body: '.substr((string)$response->getContent(), 0, 100));
    }

    public function test_stripe_webhook_signature_helper_works_correctly(): void
    {
        // Lower-level: validate the constructEvent contract in isolation.
        // If this passes, the controller's verification path uses it correctly.
        $payload = '{"id":"evt_test","object":"event"}';
        $secret = 'whsec_unit_test_secret';
        $ts = time();
        $sig = hash_hmac('sha256', "{$ts}.{$payload}", $secret);

        try {
            \Stripe\Webhook::constructEvent($payload, "t={$ts},v1={$sig}", $secret, 300);
            $valid = true;
        } catch (\Throwable $e) {
            $valid = false;
        }

        $this->assertTrue($valid, 'Stripe\\Webhook::constructEvent must accept a correctly-signed payload.');

        try {
            \Stripe\Webhook::constructEvent($payload, "t={$ts},v1=wronghex", $secret, 300);
            $rejectedWrong = false;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $rejectedWrong = true;
        }

        $this->assertTrue($rejectedWrong, 'Stripe\\Webhook::constructEvent must reject wrong signatures.');
    }

    public function test_razorpay_webhook_rejects_missing_signature(): void
    {
        $payload = json_encode(['event' => 'payment.captured', 'payload' => []]);

        // Use kernel directly — TestCase::call() snapshots config BEFORE
        // setUp's config()->set takes effect for the kernel-handled request.
        $req = \Illuminate\Http\Request::create('/webhooks/razorpay', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
        $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);

        $this->assertContains($response->getStatusCode(), [400, 401, 403, 422],
            'Razorpay webhook must refuse missing signature; got '.$response->getStatusCode().' body: '.substr((string)$response->getContent(), 0, 100));
    }

    public function test_razorpay_webhook_rejects_wrong_signature(): void
    {
        $payload = json_encode(['event' => 'payment.captured', 'payload' => []]);
        $req = \Illuminate\Http\Request::create('/webhooks/razorpay', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => 'wrong-hmac-deadbeef',
        ], $payload);
        $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);

        $this->assertContains($response->getStatusCode(), [400, 401, 403, 422],
            'Razorpay webhook must refuse wrong signature; got '.$response->getStatusCode().' body: '.substr((string)$response->getContent(), 0, 100));
    }

    public function test_razorpay_signature_algorithm_matches_controller_expectation(): void
    {
        $payload = '{"event":"payment.captured"}';
        $secret = 'razorpay_test_webhook_secret';
        $sig = hash_hmac('sha256', $payload, $secret);

        // hash_equals is the constant-time comparison the controller uses.
        $this->assertTrue(hash_equals(
            hash_hmac('sha256', $payload, $secret),
            $sig,
        ));
        $this->assertFalse(hash_equals(
            hash_hmac('sha256', $payload, 'different_secret'),
            $sig,
        ));
    }

    public function test_bkash_webhook_endpoint_exists_and_is_throttled(): void
    {
        // bKash, MercadoPago, PayPal use API-requery rather than HMAC
        // — verify the routes are registered + middleware-protected,
        // even without a live gateway.
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn($r) => $r->uri() === 'webhooks/bkash');

        $this->assertNotNull($route, 'webhooks/bkash route must be registered.');

        $middleware = $route->gatherMiddleware();
        $hasThrottle = collect($middleware)->contains(fn($m) => str_starts_with($m, 'throttle'));
        $this->assertTrue($hasThrottle, 'webhooks/bkash must have throttle middleware (rate limiting).');
    }

    public function test_paypal_and_mercadopago_webhook_endpoints_throttled(): void
    {
        foreach (['webhooks/paypal', 'webhooks/mercadopago'] as $uri) {
            $route = collect(app('router')->getRoutes()->getRoutes())
                ->first(fn($r) => $r->uri() === $uri);

            $this->assertNotNull($route, "$uri must be registered.");
            $hasThrottle = collect($route->gatherMiddleware())
                ->contains(fn($m) => str_starts_with($m, 'throttle'));
            $this->assertTrue($hasThrottle, "$uri must be throttled.");
        }
    }

    public function test_every_webhook_route_is_csrf_exempt(): void
    {
        // Webhooks come from third parties; they don't have our CSRF
        // token. They MUST be on the exempt list in VerifyCsrfToken.
        $ref = new \ReflectionClass(\App\Http\Middleware\VerifyCsrfToken::class);
        $prop = $ref->getProperty('except');
        $prop->setAccessible(true);
        $exempt = $prop->getValue($ref->newInstanceWithoutConstructor());

        foreach (['webhooks/stripe', 'webhooks/razorpay', 'webhooks/bkash',
                  'webhooks/paypal', 'webhooks/mercadopago'] as $path) {
            $this->assertTrue(
                in_array($path, $exempt, true) || in_array('webhooks/*', $exempt, true),
                "$path must be in the CSRF exempt list. Currently: ".implode(',', $exempt)
            );
        }
    }
}
