<?php

namespace App\Notifications;

use Modules\PaymentWithdraw\app\Models\WithdrawRequest;

/**
 * Sent to a coach when their payout request is rejected.
 */
class WithdrawalRejectedToCoach extends InAppNotification
{
    protected string $event = 'withdrawal_status_changed';
    protected string $emailTemplate = 'notif_withdrawal_rejected';

    private WithdrawRequest $request;

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(WithdrawRequest $request)
    {
        $this->request = $request;
        $this->title = 'Payout rejected';
        $this->body = 'Your withdrawal of ' . formatMoney($request->withdraw_amount) . ' was not approved. Open payouts for details.';
        $this->icon = 'fa-circle-xmark';
        $this->iconColor = '#ef4444';
        $this->url = route('instructor.payout.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name' => $notifiable->name ?? '',
            'amount'    => formatMoney($this->request->withdraw_amount),
        ];
    }
}
