<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Modules\Order\app\Models\Order;
use App\Services\PaymentFulfilmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(private PaymentFulfilmentService $fulfilment) {}

    /**
     * POST /webhooks/mercadopago
     *
     * MercadoPago notifies via IPN/webhook with `topic=payment, id=<payment_id>`
     * (no signature for legacy IPN). The secure pattern: re-query MP's API with
     * the payment id and trust only what the API returns.
     *
     * Required env: MP_ACCESS_TOKEN.
     */
    public function handle(Request $request): Response
    {
        $accessToken = config('services.mercadopago.access_token') ?: env('MP_ACCESS_TOKEN');
        if (empty($accessToken)) {
            Log::error('MercadoPago webhook hit but MP_ACCESS_TOKEN is not set');
            return response('Webhook not configured', 500);
        }

        $topic     = $request->query('topic') ?: $request->input('type');
        $paymentId = $request->query('id') ?: ($request->input('data.id') ?? null);

        if ($topic !== 'payment' || empty($paymentId)) {
            Log::info('MercadoPago webhook ignored', ['topic' => $topic, 'id' => $paymentId]);
            return response('OK', 200);
        }

        try {
            $res = Http::timeout(10)
                ->withToken($accessToken)
                ->get("https://api.mercadopago.com/v1/payments/{$paymentId}")
                ->json();
        } catch (\Throwable $e) {
            Log::error('MercadoPago webhook query failed', ['err' => $e->getMessage()]);
            return response('Query error', 500);
        }

        $status = $res['status'] ?? null;
        if ($status !== 'approved') {
            Log::info('MercadoPago webhook: payment not approved', ['payment_id' => $paymentId, 'status' => $status]);
            return response('Not approved', 200);
        }

        $orderId = $res['external_reference'] ?? null;
        if (!$orderId) {
            Log::warning('MercadoPago webhook had no external_reference (order id)', ['payment_id' => $paymentId]);
            return response('Acknowledged (no order metadata)', 200);
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::warning('MercadoPago webhook references missing order', ['order_id' => $orderId]);
            return response('Acknowledged (order not found)', 200);
        }

        // F3 (audit 2026-06-26) — amount reconciliation. MercadoPago's
        // transaction_amount is MAJOR units. Only reconcile on a currency match
        // (currency_id == order currency) so a converted payment can't false-block.
        $mpVerified = (isset($res['transaction_amount']) && isset($res['currency_id'])
            && strtoupper((string) $res['currency_id']) === strtoupper((string) $order->payable_currency))
            ? (float) $res['transaction_amount'] : null;

        $processed = $this->fulfilment->markPaid($order, $paymentId, [
            'gateway' => 'mercadopago',
            'response' => $res,
        ], $mpVerified);

        Log::info('MercadoPago webhook processed', [
            'order_id' => $order->id, 'trx_id' => $paymentId, 'processed' => $processed,
        ]);

        return response('OK', 200);
    }
}
