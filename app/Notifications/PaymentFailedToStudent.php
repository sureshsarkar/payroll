<?php

namespace App\Notifications;

use Illuminate\Support\Facades\Cache;
use Modules\Order\app\Models\Order;

/**
 * 2026-07-09 (Email/Notification audit — Phase 3.1).
 *
 * Sent to the BUYER when a payment attempt on their order fails (Razorpay
 * `payment.failed` webhook). Previously a failed payment produced NO
 * communication at all — a student who abandoned checkout was never told and
 * never nudged to retry. Coach-branded (brand = the order's owning coach) via
 * the shared InAppNotification pipeline (bell + email + real-time).
 */
class PaymentFailedToStudent extends InAppNotification
{
    protected string $event = 'payment_failed_student';
    protected string $emailTemplate = 'notif_payment_failed_student';
    protected string $emailCtaLabel = 'Try again';

    private string $orderId;
    private string $amount;
    private string $retryUrl;

    public function __construct(Order $order, ?float $amount = null, ?string $currencyCode = null)
    {
        // Brand the student's email as the coach who owns the order.
        $this->coachId = $order->primary_coach_id ? (int) $order->primary_coach_id : null;

        $this->orderId = (string) ($order->invoice_id ?: $order->id);
        $this->amount  = formatMoney((float) ($amount ?? $order->paid_amount ?? 0), $currencyCode ?: ($order->payable_currency ?: null));

        try {
            $this->retryUrl = route('student.order.show', ['id' => $order->id]);
        } catch (\Throwable $e) {
            $this->retryUrl = url('/');
        }

        $this->title     = 'Payment didn\'t go through';
        $this->body      = 'Your payment for order #' . $this->orderId . ' wasn\'t completed. You can try again from your orders.';
        $this->icon      = 'fa-circle-exclamation';
        $this->iconColor = '#ef4444';
        $this->url       = $this->retryUrl;
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name' => $notifiable->name ?? '',
            'order_id'  => $this->orderId,
            'amount'    => $this->amount,
            'retry_url' => $this->retryUrl,
        ];
    }

    /**
     * Dedupe helper: returns true only the first time within the window for an
     * order, so rapid retry attempts don't spam the student. Used by the caller.
     */
    public static function shouldNotify(int $orderId, int $ttlSeconds = 3600): bool
    {
        return Cache::add('pay_failed_notified_student:' . $orderId, 1, $ttlSeconds);
    }
}
