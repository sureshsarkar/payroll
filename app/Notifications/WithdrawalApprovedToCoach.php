<?php

namespace App\Notifications;

use Modules\PaymentWithdraw\app\Models\WithdrawRequest;

/**
 * Sent to a coach when their payout request is approved.
 */
class WithdrawalApprovedToCoach extends InAppNotification
{
    protected string $event = 'withdrawal_status_changed';
    protected string $emailTemplate = 'notif_withdrawal_approved';

    private WithdrawRequest $request;

    /** Coach-facing: brand the email as the coach who owns the recipient. */
    protected function brandCoachId(object $notifiable): ?int
    {
        return $this->recipientCoachId($notifiable);
    }

    public function __construct(WithdrawRequest $request)
    {
        $this->request = $request;
        $this->title = 'Payout approved';
        $this->body = 'Your withdrawal of ' . formatMoney($request->withdraw_amount) . ' has been approved.';
        $this->icon = 'fa-money-bill-wave';
        $this->iconColor = '#10b981';
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
