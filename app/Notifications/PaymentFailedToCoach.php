<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Order\app\Models\Order;

/**
 * 2026-07-09 (Email/Notification audit — Phase 3.1).
 *
 * Sent to the COACH who owns an order when a payment attempt fails
 * (Razorpay `payment.failed` webhook) — visibility into lost/abandoned sales,
 * which previously produced no notification at all. Branded as the coach
 * (recipient) via the shared InAppNotification pipeline.
 */
class PaymentFailedToCoach extends InAppNotification
{
    protected string $event = 'payment_failed_coach';
    protected string $emailTemplate = 'notif_payment_failed_coach';
    protected string $emailCtaLabel = 'View orders';

    private string $orderId;
    private string $amount;
    private string $studentName;
    private string $orderUrl;
    private string $coachName;

    public function __construct(Order $order, ?float $amount = null, ?string $currencyCode = null, ?User $student = null)
    {
        // Recipient IS the coach → brand as the coach's own identity.
        $this->coachId = $order->primary_coach_id ? (int) $order->primary_coach_id : null;

        $this->orderId     = (string) ($order->invoice_id ?: $order->id);
        $this->amount      = formatMoney((float) ($amount ?? $order->paid_amount ?? 0), $currencyCode ?: ($order->payable_currency ?: null));
        $this->studentName = (string) ($student?->name ?: __('A customer'));
        $this->coachName   = $this->coachId ? (string) (User::where('id', $this->coachId)->value('name') ?: '') : '';

        try {
            $this->orderUrl = route('instructor.my-sells.index');
        } catch (\Throwable $e) {
            $this->orderUrl = url('/');
        }

        $this->title     = 'A checkout didn\'t complete';
        $this->body      = 'A payment for order #' . $this->orderId . ' (' . $this->amount . ') from ' . Str::limit($this->studentName, 40) . ' wasn\'t completed.';
        $this->icon      = 'fa-triangle-exclamation';
        $this->iconColor = '#f59e0b';
        $this->url       = $this->orderUrl;
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'coach_name'   => $this->coachName ?: ($notifiable->name ?? ''),
            'student_name' => $this->studentName,
            'order_id'     => $this->orderId,
            'amount'       => $this->amount,
            'order_url'    => $this->orderUrl,
        ];
    }
}
