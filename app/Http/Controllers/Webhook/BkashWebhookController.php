<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Modules\Order\app\Models\Order;
use App\Services\PaymentFulfilmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BkashWebhookController extends Controller
{
    public function __construct(private PaymentFulfilmentService $fulfilment) {}

    /**
     * POST /webhooks/bkash
     *
     * bKash Tokenized Checkout doesn't ship classic webhooks; the secure flow
     * is to call the bKash "execute payment" / "query payment" endpoint with
     * the trxID and verify status server-side. This handler accepts a
     * "callback ping" (e.g. from a cron worker rechecking pending orders) or
     * a direct signed notification if the merchant configured one.
     *
     * For now this acts as a thin verification: trust nothing in the request
     * body — re-query bKash with the trxID and only mark paid if bKash says
     * statusCode='0000' AND transactionStatus='Completed'.
     */
    public function handle(Request $request): Response
    {
        // FT-VAL-11 (2026-05-28) — tighten order_id rule.
        // Pre-fix `order_id => required` accepted any string. With
        // non-numeric input, Eloquent's MySQL driver casts to int
        // (so "1abc" hits Order id=1) which still wouldn't pass the
        // bKash re-query below (trx_id mismatch), but tightening
        // up-front keeps log noise down and rejects fuzzers earlier.
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'trx_id'   => 'required|string|max:64',
        ]);
        if ($validator->fails()) {
            return response($validator->errors()->first(), 400);
        }

        $orderId = $request->input('order_id');
        $trxId   = $request->input('trx_id');

        $order = Order::find($orderId);
        if (!$order) {
            Log::warning('bKash webhook references missing order', ['order_id' => $orderId]);
            return response('Acknowledged (order not found)', 200);
        }

        // Re-query bKash for the canonical payment status before trusting it.
        // Reuses the same credentials wired in Modules/BkashPG.
        $appKey = config('services.bkash.app_key') ?: env('BKASH_APP_KEY');
        $appSecret = config('services.bkash.app_secret') ?: env('BKASH_APP_SECRET');
        $username = config('services.bkash.username') ?: env('BKASH_USERNAME');
        $password = config('services.bkash.password') ?: env('BKASH_PASSWORD');
        $baseUrl  = config('services.bkash.base_url') ?: env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta');

        if (!$appKey || !$appSecret || !$username || !$password) {
            Log::error('bKash webhook hit but credentials are not set');
            return response('Webhook not configured', 500);
        }

        try {
            // 1. Get token
            $tokenRes = Http::timeout(10)->withHeaders([
                'username' => $username,
                'password' => $password,
            ])->post("$baseUrl/tokenized/checkout/token/grant", [
                'app_key' => $appKey,
                'app_secret' => $appSecret,
            ])->json();

            $token = $tokenRes['id_token'] ?? null;
            if (!$token) {
                Log::warning('bKash token grant failed', ['res' => $tokenRes]);
                return response('Token unavailable', 502);
            }

            // 2. Query the payment status
            $statusRes = Http::timeout(10)->withHeaders([
                'authorization' => $token,
                'x-app-key'     => $appKey,
            ])->post("$baseUrl/tokenized/checkout/payment/status", [
                'paymentID' => $trxId,
            ])->json();

            $statusCode = $statusRes['statusCode'] ?? '';
            $txStatus   = $statusRes['transactionStatus'] ?? '';

            if ($statusCode !== '0000' || $txStatus !== 'Completed') {
                Log::info('bKash webhook: payment not completed', ['trx_id' => $trxId, 'res' => $statusRes]);
                return response('Not paid yet', 200);
            }

            // F3 (audit 2026-06-26) — amount reconciliation. bKash returns the
            // amount in MAJOR units (BDT). Only reconcile on a currency match so a
            // converted-currency payment can't false-block.
            $bkVerified = (isset($statusRes['amount']) && isset($statusRes['currency'])
                && strtoupper((string) $statusRes['currency']) === strtoupper((string) $order->payable_currency))
                ? (float) $statusRes['amount'] : null;

            $processed = $this->fulfilment->markPaid($order, $trxId, [
                'gateway' => 'bkash',
                'response' => $statusRes,
            ], $bkVerified);

            Log::info('bKash webhook processed', ['order_id' => $order->id, 'trx_id' => $trxId, 'processed' => $processed]);
        } catch (\Throwable $e) {
            Log::error('bKash webhook error', ['err' => $e->getMessage()]);
            return response('Internal error', 500);
        }

        return response('OK', 200);
    }
}
