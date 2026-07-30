<?php

namespace App\Notifications;

use App\Models\FeeDemand;
use App\Models\FeePayment;

/**
 * Sent to a student when their coach refunds a fee payment
 * (FeeManagementController::refundPayment). White-label: branded as the coach
 * who owns the fee demand, never the platform.
 */
class FeeRefundedToStudent extends InAppNotification
{
    protected string $event = 'fee_payment_received';

    private FeePayment $payment;

    public function __construct(FeePayment $payment)
    {
        $this->payment = $payment;

        // Resolve the owning coach (demand may be loaded without coach_id).
        $coachId = $payment->demand?->coach_id
            ?: FeeDemand::where('id', $payment->fee_demand_id)->value('coach_id');
        $this->coachId = $coachId ? (int) $coachId : null;

        $this->title     = 'Fee refunded';
        $this->body      = formatMoney($payment->amount)
                          . ($payment->receipt_no ? ' (receipt ' . $payment->receipt_no . ')' : '')
                          . ' has been refunded to you.';
        $this->icon      = 'fa-rotate-left';
        $this->iconColor = '#f59e0b';

        try {
            $this->url = route('student.fees.index');
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }
}
