<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Modules\Order\app\Models\Order;
use App\Services\PaymentFulfilmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalWebhookController extends Controller
{
    public function __construct(private PaymentFulfilmentService $fulfilment) {}

    /**
     * POST /webhooks/paypal
     *
     * PayPal sends webhook notifications and signs them. We must call PayPal's
     * /v1/notifications/verify-webhook-signature to confirm authenticity —
     * never trust the body alone.
     *
     * Required env: PAYPAL_WEBHOOK_ID, plus the OAuth credentials already used
     * by srmklive/paypal (client id + secret in the paypal.php config).
     *
     * Events: PAYMENT.CAPTURE.COMPLETED, CHECKOUT.ORDER.APPROVED.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $event   = json_decode($payload, true);

        // Tenant-aware (2026-06-29) — resolve the verifying context (webhook id +
        // OAuth client creds + account mode) from the order referenced by
        // custom_id, so a coach payment is verified against the COACH's PayPal
        // app. Default/legacy orders keep the platform env credentials.
        $ctx = $this->resolvePaypalContext($event);

        // Coach gateway but the coach didn't set a Webhook ID — we cannot verify
        // this notification against the coach's PayPal app (mixing the platform
        // webhook id with the coach's OAuth creds would always fail and trigger
        // endless PayPal retries). Acknowledge so retries stop; the synchronous
        // return callback remains the source of truth for fulfilment.
        if (! empty($ctx['coach_unverifiable'])) {
            Log::info('PayPal webhook: coach gateway has no webhook id; relying on sync callback', [
                'event_id' => $event['id'] ?? null,
            ]);
            return response('Acknowledged (coach webhook id not configured)', 200);
        }

        if (empty($ctx['webhook_id'])) {
            Log::error('PayPal webhook hit but no webhook id is configured');
            return response('Webhook not configured', 500);
        }

        // Verify with PayPal
        try {
            $accessToken = $this->getAccessToken($ctx['client_id'], $ctx['secret'], $ctx['mode']);
            if (!$accessToken) {
                return response('Unable to obtain PayPal access token', 502);
            }

            $verify = Http::timeout(10)->withToken($accessToken)
                ->post($this->baseApi($ctx['mode']) . '/v1/notifications/verify-webhook-signature', [
                    'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
                    'cert_url'          => $request->header('PAYPAL-CERT-URL'),
                    'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                    'webhook_id'        => $ctx['webhook_id'],
                    'webhook_event'     => $event,
                ])->json();

            if (($verify['verification_status'] ?? '') !== 'SUCCESS') {
                Log::warning('PayPal webhook signature verification failed', ['result' => $verify]);
                return response('Invalid signature', 400);
            }
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification error', ['err' => $e->getMessage()]);
            return response('Verification error', 500);
        }

        $eventType = $event['event_type'] ?? null;

        if (in_array($eventType, ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'], true)) {
            $resource = $event['resource'] ?? [];
            $orderId  = $resource['custom_id']
                     ?? $resource['purchase_units'][0]['custom_id']
                     ?? null;
            $trxId    = $resource['id'] ?? null;

            if (!$orderId) {
                Log::warning('PayPal webhook had no order_id (custom_id)', ['event_id' => $event['id'] ?? null]);
                return response('Acknowledged (no order metadata)', 200);
            }

            $order = Order::find($orderId);
            if (!$order) {
                Log::warning('PayPal webhook references missing order', ['order_id' => $orderId]);
                return response('Acknowledged (order not found)', 200);
            }

            // F3 (audit 2026-06-26) — amount reconciliation. PayPal amounts are
            // MAJOR units (decimal string). Only reconcile when the captured
            // currency matches the order currency, so a converted-currency
            // capture can never trigger a FALSE underpayment block.
            $ppVal = $resource['amount']['value'] ?? ($resource['purchase_units'][0]['amount']['value'] ?? null);
            $ppCur = $resource['amount']['currency_code'] ?? ($resource['purchase_units'][0]['amount']['currency_code'] ?? null);
            $ppVerified = ($ppVal !== null && $ppCur !== null
                && strtoupper((string) $ppCur) === strtoupper((string) $order->payable_currency))
                ? (float) $ppVal : null;

            $processed = $this->fulfilment->markPaid($order, $trxId, [
                'gateway' => 'paypal',
                'event_id' => $event['id'] ?? null,
                'amount' => $ppVal,
                'currency' => $ppCur,
            ], $ppVerified);

            Order::whereKey($order->id)->update(['webhook_verified_at' => now()]);

            Log::info('PayPal webhook processed', [
                'order_id' => $order->id, 'trx_id' => $trxId, 'processed' => $processed,
            ]);
        } else {
            Log::info('PayPal webhook unhandled type', ['type' => $eventType]);
        }

        return response('OK', 200);
    }

    private function getAccessToken(?string $clientId = null, ?string $secret = null, ?string $mode = null): ?string
    {
        $clientId = $clientId ?: (env('PAYPAL_LIVE_CLIENT_ID') ?: env('PAYPAL_SANDBOX_CLIENT_ID'));
        $secret   = $secret ?: (env('PAYPAL_LIVE_CLIENT_SECRET') ?: env('PAYPAL_SANDBOX_CLIENT_SECRET'));
        if (!$clientId || !$secret) return null;

        $res = Http::timeout(10)->asForm()
            ->withBasicAuth($clientId, $secret)
            ->post($this->baseApi($mode) . '/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->json();
        return $res['access_token'] ?? null;
    }

    private function baseApi(?string $mode = null): string
    {
        $mode = strtolower($mode ?: env('PAYPAL_MODE', 'sandbox'));
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Resolve the PayPal verification context from the order referenced by the
     * event's custom_id. Coach gateway → the coach's webhook id + OAuth client
     * creds + account mode; otherwise the platform env defaults (unchanged).
     *
     * @return array{webhook_id:?string, client_id:?string, secret:?string, mode:?string}
     */
    private function resolvePaypalContext(?array $event): array
    {
        $ctx = [
            'webhook_id' => config('services.paypal.webhook_id') ?: env('PAYPAL_WEBHOOK_ID'),
            'client_id'  => null,
            'secret'     => null,
            'mode'       => env('PAYPAL_MODE', 'sandbox'),
            'coach_unverifiable' => false,
        ];

        $orderId = data_get($event, 'resource.custom_id')
            ?? data_get($event, 'resource.purchase_units.0.custom_id');

        if ($orderId && ($order = Order::find($orderId))) {
            $resolved = app(\App\Services\Payment\PaymentGatewayResolverService::class)
                ->resolveForOrder($order);
            if (! $resolved->isDefault()) {
                $coachWebhookId = $resolved->webhookSecret;
                if (empty($coachWebhookId)) {
                    // Coach gateway without a Webhook ID — cannot verify against
                    // the coach's app; never mix it with the platform webhook id.
                    $ctx['coach_unverifiable'] = true;
                    return $ctx;
                }
                $ctx['webhook_id'] = $coachWebhookId;
                $ctx['client_id']  = $resolved->credential('paypal_client_id');
                $ctx['secret']     = $resolved->credential('paypal_secret_key');
                $ctx['mode']       = $resolved->credential('paypal_account_mode') ?: $ctx['mode'];
            }
        }

        return $ctx;
    }
}
