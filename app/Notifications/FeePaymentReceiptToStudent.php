<?php

namespace App\Notifications;

use App\Models\FeePayment;

/**
 * Receipt mail sent to a student after a successful fee payment.
 * Phase 4C.
 */
class FeePaymentReceiptToStudent extends InAppNotification
{
    protected string $event = 'fee_payment_received';
    protected string $emailTemplate = 'notif_fee_payment_receipt';
    protected string $emailCtaLabel = 'Open my fees';

    private FeePayment $payment;

    public function __construct(FeePayment $payment)
    {
        $this->payment = $payment;
        $this->coachId = $payment->demand?->coach_id ? (int) $payment->demand->coach_id : null;
        $this->title = 'Payment received';
        $this->body = formatMoney($payment->amount)
            . ' · ' . \Str::limit($payment->demand?->title ?? '', 40)
            . ' · Receipt ' . $payment->receipt_no;
        $this->icon = 'fa-receipt';
        $this->iconColor = '#10b981';
        $this->url = route('student.fees.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'  => $notifiable->name ?? '',
            'amount'     => formatMoney($this->payment->amount),
            'receipt_no' => $this->payment->receipt_no,
            'fee_title'  => $this->payment->demand?->title ?? '',
            'paid_at'    => optional($this->payment->paid_at)->format('M j, Y g:i A') ?? '',
            'gateway'    => strtoupper($this->payment->gateway),
            'fees_url'   => $this->url,
        ];
    }
}
