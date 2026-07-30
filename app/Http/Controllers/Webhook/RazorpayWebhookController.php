<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\CoachPricingEnquiry;
use App\Models\CoachPricingPayment;
use App\Services\PaymentFulfilmentService;
use App\Services\Payment\PaymentGatewayResolverService;
use App\Services\PricingPaymentService;
use Modules\Order\app\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    public function __construct(private PaymentFulfilmentService $fulfilment) {}

    /**
     * POST /webhooks/razorpay
     *
     * Razorpay sends webhook events with HMAC-SHA256 of the raw body, keyed by
     * RAZORPAY_WEBHOOK_SECRET (set in Razorpay dashboard).
     * Events: payment.authorized, payment.captured, payment.failed.
     *
     * The `payment.captured` event is what we treat as "money in hand".
     * Razorpay retries on non-2xx, so this handler MUST be idempotent.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sig = $request->header('X-Razorpay-Signature');

        // Tenant-aware (2026-06-29). Route to the right webhook secret using the
        // order referenced in the (still-unverified) payload; the HMAC check
        // below is what enforces trust.
        $secret = $this->resolveWebhookSecret($payload);
        if (empty($secret)) {
            Log::error('Razorpay webhook hit but no webhook secret is configured');
            return response('Webhook not configured', 500);
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        if (!is_string($sig) || !hash_equals($expected, $sig)) {
            Log::warning('Razorpay webhook signature mismatch');
            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);
        $type  = $event['event'] ?? null;

        if ($type === 'payment.captured') {
            $payment = $event['payload']['payment']['entity'] ?? null;
            if (!$payment) {
                return response('Acknowledged (no payment entity)', 200);
            }
            $trxId = $payment['id'] ?? null;
            $notes = $payment['notes'] ?? [];

            // Audit 2026-05-19 phase 4C — branch on what the order was for.
            // Fee Management payments echo {fee_demand_id, student_id} in
            // notes; course/membership orders echo {order_id}.
            if (!empty($notes['fee_demand_id']) && !empty($notes['student_id'])) {
                return $this->markFeePaymentPaid($payment, (int) $notes['fee_demand_id'], (int) $notes['student_id']);
            }

            // 2026-07-13 — Pricing & Plans booking payment. notes carry
            // {type: 'pricing_plan', enquiry_id}. Safety net for the FE verify
            // path: if the visitor closed the tab after paying, the webhook still
            // confirms it server-side (idempotent).
            // 2026-07-15 — Trainer "Book Personal Class Session" (type:
            // 'trainer_personal_session') rides the SAME coach_pricing_payments row
            // keyed by enquiry_id, so it uses the identical mark-paid path.
            if (in_array($notes['type'] ?? null, ['pricing_plan', 'trainer_personal_session'], true) && !empty($notes['enquiry_id'])) {
                return $this->markPricingPaymentPaid($payment, (int) $notes['enquiry_id']);
            }

            $orderId = $notes['order_id'] ?? null;
            if (!$orderId) {
                Log::warning('Razorpay webhook had no order_id / fee_demand_id in notes', ['payment_id' => $trxId]);
                return response('Acknowledged (no order metadata)', 200);
            }

            $order = Order::find($orderId);
            if (!$order) {
                Log::warning('Razorpay webhook references missing order', ['order_id' => $orderId]);
                return response('Acknowledged (order not found)', 200);
            }

            $processed = $this->fulfilment->markPaid($order, $trxId, [
                'gateway' => 'razorpay',
                'event_id' => $payment['id'] ?? null,
                'amount' => $payment['amount'] ?? null,
                'currency' => $payment['currency'] ?? null,
            ], isset($payment['amount']) ? ((float) $payment['amount']) / 100 : null); // F3 amount reconciliation (subunits→major)

            Order::whereKey($order->id)->update(['webhook_verified_at' => now()]);

            Log::info('Razorpay webhook processed', [
                'order_id' => $order->id, 'trx_id' => $trxId, 'processed' => $processed,
            ]);
        } elseif ($type === 'payment.failed') {
            // 2026-07-09 (Phase 3.1) — a failed payment previously produced NO
            // communication. Notify the buyer (retry nudge) + the owning coach
            // (lost-sale visibility). Deduped per order so retry attempts don't
            // spam; skipped when the order is already paid.
            $payment = $event['payload']['payment']['entity'] ?? null;

            // 2026-07-13 — Pricing & Plans failed payment: keep the enquiry, flag
            // the payment failed. A later captured retry still transitions to paid.
            $pNotes = $payment['notes'] ?? [];
            if ($payment && in_array($pNotes['type'] ?? null, ['pricing_plan', 'trainer_personal_session'], true) && !empty($pNotes['enquiry_id'])) {
                $this->markPricingPaymentFailed($payment, (int) $pNotes['enquiry_id']);
            }

            $orderId = $payment['notes']['order_id'] ?? null;
            if ($payment && $orderId && ($order = Order::find($orderId))) {
                if ($order->payment_status !== 'paid' && \App\Notifications\PaymentFailedToStudent::shouldNotify((int) $order->id)) {
                    $amount = isset($payment['amount']) ? ((float) $payment['amount']) / 100 : (float) ($order->paid_amount ?? 0);
                    $code   = $payment['currency'] ?? $order->payable_currency;
                    $buyer  = $order->buyer_id ? \App\Models\User::find($order->buyer_id) : null;

                    if ($buyer) {
                        try {
                            $buyer->notify(new \App\Notifications\PaymentFailedToStudent($order, $amount, $code));
                        } catch (\Throwable $e) {
                            Log::warning('Notify student of payment failure failed: ' . $e->getMessage());
                        }
                    }

                    $coachId = $order->primary_coach_id ? (int) $order->primary_coach_id : null;
                    if ($coachId && ($coach = \App\Models\User::find($coachId))) {
                        try {
                            $coach->notify(new \App\Notifications\PaymentFailedToCoach($order, $amount, $code, $buyer));
                        } catch (\Throwable $e) {
                            Log::warning('Notify coach of payment failure failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        } else {
            Log::info('Razorpay webhook unhandled type', ['type' => $type]);
        }

        return response('OK', 200);
    }

    /**
     * Route to the correct webhook secret using the order referenced in the
     * (still-unverified) payload. Coach gateway → coach's secret; otherwise the
     * platform default (also used for fee payments, which are platform fees).
     */
    private function resolveWebhookSecret(string $payload): ?string
    {
        $event   = json_decode($payload, true);
        $notes   = (array) data_get($event, 'payload.payment.entity.notes', []);
        $orderId = $notes['order_id'] ?? null;

        if ($orderId && ($order = Order::find($orderId))) {
            $resolved = app(PaymentGatewayResolverService::class)->resolveForOrder($order);
            if (! empty($resolved->webhookSecret)) {
                return $resolved->webhookSecret;
            }
        }

        // Pricing-plan / trainer-session payment → resolve the owning coach's
        // webhook secret. The coach_id comes from the still-unverified payload;
        // picking the wrong secret simply fails the HMAC check below, so trust is
        // still enforced.
        if (in_array($notes['type'] ?? null, ['pricing_plan', 'trainer_personal_session'], true) && ! empty($notes['coach_id'])) {
            $resolved = app(PaymentGatewayResolverService::class)->resolve((int) $notes['coach_id'], 'razorpay');
            if (! empty($resolved->webhookSecret)) {
                return $resolved->webhookSecret;
            }
        }

        return config('services.razorpay.webhook_secret') ?: env('RAZORPAY_WEBHOOK_SECRET');
    }

    /**
     * Idempotent — flips a pending pricing payment to 'paid' + its enquiry to
     * paid. Reuses PricingPaymentService::markPaidLocked (row-locked, amount-
     * reconciled) so the webhook and the FE verify callback can never double-pay.
     */
    private function markPricingPaymentPaid(array $rzpPayment, int $enquiryId): Response
    {
        $rzpOrderId = $rzpPayment['order_id'] ?? null;

        $payment = CoachPricingPayment::where('enquiry_id', $enquiryId)
            ->where('gateway', 'razorpay')
            ->where('gateway_order_id', $rzpOrderId)
            ->first();

        if (! $payment) {
            Log::warning('Razorpay pricing webhook: no matching payment row', [
                'enquiry_id' => $enquiryId, 'rzp_order' => $rzpOrderId,
            ]);
            return response('Acknowledged (no pricing payment row)', 200);
        }
        if ($payment->status === CoachPricingPayment::STATUS_PAID) {
            return response('OK (already paid)', 200);
        }

        $capturedPaise = isset($rzpPayment['amount']) ? (int) $rzpPayment['amount'] : null;
        app(PricingPaymentService::class)->markPaidLocked(
            $payment,
            (string) ($rzpPayment['id'] ?? ''),
            $capturedPaise,
            $rzpPayment
        );

        Log::info('Razorpay pricing webhook processed', ['pricing_payment_id' => $payment->id]);
        return response('OK', 200);
    }

    /** Mark a pending pricing payment (+ its enquiry) failed. Never touches a paid row. */
    private function markPricingPaymentFailed(array $rzpPayment, int $enquiryId): void
    {
        $payment = CoachPricingPayment::where('enquiry_id', $enquiryId)
            ->where('gateway', 'razorpay')
            ->where('gateway_order_id', $rzpPayment['order_id'] ?? null)
            ->first();

        if ($payment && $payment->status === CoachPricingPayment::STATUS_PENDING) {
            // Persist the gateway's failure reason (error_code / error_description).
            $payment->update([
                'status'          => CoachPricingPayment::STATUS_FAILED,
                'payment_details' => json_encode([
                    'error_code'        => $rzpPayment['error_code'] ?? null,
                    'error_description' => $rzpPayment['error_description'] ?? null,
                    'error_reason'      => $rzpPayment['error_reason'] ?? null,
                ]),
            ]);
            $payment->enquiry?->update(['payment_status' => CoachPricingEnquiry::PAY_FAILED]);
        }
    }

    /**
     * Idempotent — flips an 'initiated' fee_payment row to 'paid'.
     * Called by the webhook when payment.captured carries
     * notes.fee_demand_id + notes.student_id.
     *
     * Safety net for the FE callback path: if the student closed the
     * browser before /student/fees/checkout/verify fired, the webhook
     * still confirms the payment server-side.
     */
    private function markFeePaymentPaid(array $rzpPayment, int $demandId, int $studentId): Response
    {
        $rzpOrderId   = $rzpPayment['order_id'] ?? null;
        $rzpPaymentId = $rzpPayment['id'] ?? null;

        // Find by Razorpay order_id (saved when we created the order).
        // Falls back to payment_id in case verify() already swapped it.
        $payment = \App\Models\FeePayment::where('gateway', 'razorpay')
            ->where('student_id', $studentId)
            ->where('fee_demand_id', $demandId)
            ->where(function ($q) use ($rzpOrderId, $rzpPaymentId) {
                $q->where('gateway_txn_id', $rzpOrderId)
                  ->orWhere('gateway_txn_id', $rzpPaymentId);
            })
            ->first();

        if (!$payment) {
            Log::warning('Razorpay fee webhook: no matching fee_payment row', [
                'demand_id' => $demandId, 'student_id' => $studentId,
                'rzp_order' => $rzpOrderId,
            ]);
            return response('Acknowledged (no fee_payment row)', 200);
        }

        if ($payment->status === 'paid') {
            // Idempotent — webhook retried after first delivery succeeded.
            return response('OK (already paid)', 200);
        }

        $prevStatus = $payment->status;
        $payment->update([
            'status'         => 'paid',
            'paid_at'        => now(),
            'gateway_txn_id' => $rzpPaymentId ?: $payment->gateway_txn_id,
        ]);

        // Audit the fee collection (money trail) — mirrors the course-order
        // path in PaymentFulfilmentService::markPaid. No auth guard in a
        // webhook, so ActivityLogger resolves the actor to 'system'. The
        // already-paid guard above makes this fire exactly once (idempotent).
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::PAYMENT_STATUS_CHANGED,
            'fee',
            $payment,
            ['status' => $prevStatus],
            [
                'status'         => 'paid',
                'gateway'        => 'razorpay',
                'gateway_txn_id' => $rzpPaymentId,
                'amount'         => $payment->amount,
            ],
            'Fee payment ' . $payment->receipt_no . ' (demand #' . $demandId . ') marked paid via webhook'
        );

        // Receipt email — best-effort. (Idempotency note: if the FE
        // verify() path got there first, this branch never runs because
        // the early-return above caught the already-paid row.)
        try {
            $payment->load('demand', 'student');
            if ($payment->student) {
                $payment->student->notify(new \App\Notifications\FeePaymentReceiptToStudent($payment));
            }
        } catch (\Throwable $e) {
            Log::warning('Razorpay webhook receipt notify failed', [
                'fee_payment_id' => $payment->id, 'err' => $e->getMessage(),
            ]);
        }

        Log::info('Razorpay fee webhook processed', [
            'fee_payment_id' => $payment->id,
            'receipt_no'     => $payment->receipt_no,
            'rzp_payment_id' => $rzpPaymentId,
        ]);

        return response('OK', 200);
    }
}
