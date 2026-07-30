<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\PaymentFulfilmentService;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Order;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private PaymentFulfilmentService $fulfilment) {}

    /**
     * POST /webhooks/stripe
     *
     * Verifies the Stripe-Signature header against STRIPE_WEBHOOK_SECRET,
     * then dispatches `checkout.session.completed` / `payment_intent.succeeded`
     * to the shared idempotent fulfilment service.
     *
     * Stripe retries on non-2xx responses, so this handler MUST be idempotent.
     */
    public function handle(Request $request): Response
    {
        $sig     = $request->header('Stripe-Signature');
        $payload = $request->getContent();

        // Tenant-aware (2026-06-29). Peek the UNVERIFIED payload ONLY to route to
        // the right webhook secret (the coach's, if this order was paid through a
        // coach gateway, else the platform's). Trust still comes entirely from
        // constructEvent() verifying the signature against that secret below.
        $secret = $this->resolveWebhookSecret($payload);
        if (empty($secret)) {
            Log::error('Stripe webhook hit but no webhook secret is configured');
            return response('Webhook not configured', 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook payload invalid', ['err' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature mismatch', ['err' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'payment_intent.succeeded':
                $object = $event->data->object;
                $trxId  = $object->id ?? $object->payment_intent ?? null;

                $orderId = $object->metadata->order_id ?? null;
                if (!$orderId) {
                    Log::warning('Stripe webhook had no order_id metadata', ['event' => $event->id]);
                    return response('Acknowledged (no order metadata)', 200);
                }

                $order = Order::find($orderId);
                if (!$order) {
                    Log::warning('Stripe webhook references missing order', ['order_id' => $orderId]);
                    return response('Acknowledged (order not found)', 200);
                }

                $processed = $this->fulfilment->markPaid($order, $trxId, [
                    'gateway' => 'stripe',
                    'event_id' => $event->id,
                    'object_id' => $object->id ?? null,
                    'amount' => $object->amount_total ?? null,
                ], isset($object->amount_total) ? ((float) $object->amount_total) / 100 : null); // F3 amount reconciliation (subunits→major)

                Order::whereKey($order->id)->update(['webhook_verified_at' => now()]);

                Log::info('Stripe webhook processed', [
                    'event_id'  => $event->id,
                    'order_id'  => $order->id,
                    'processed' => $processed,
                ]);
                break;

            default:
                Log::info('Stripe webhook unhandled type', ['type' => $event->type]);
        }

        return response('OK', 200);
    }

    /**
     * Route to the correct webhook secret using the order referenced in the
     * (still-unverified) payload. Coach gateway → coach's secret; otherwise the
     * platform default. The signature check that follows is what enforces trust,
     * so reading the unverified order id here is purely for routing.
     */
    private function resolveWebhookSecret(string $payload): ?string
    {
        $decoded = json_decode($payload, true);
        $orderId = is_array($decoded)
            ? data_get($decoded, 'data.object.metadata.order_id')
            : null;

        if ($orderId && ($order = Order::find($orderId))) {
            $resolved = app(\App\Services\Payment\PaymentGatewayResolverService::class)
                ->resolveForOrder($order);
            if (! empty($resolved->webhookSecret)) {
                return $resolved->webhookSecret;
            }
        }

        return config('services.stripe.webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET');
    }
}
