<?php

namespace App\Notifications;

use Modules\PaymentWithdraw\app\Models\WithdrawRequest;

/**
 * Sent to admins when a coach submits a new payout request.
 */
class WithdrawalRequestSubmittedToAdmin extends InAppNotification
{
    protected string $emailTemplate = 'notif_new_withdrawal_request';

    private WithdrawRequest $request;

    public function __construct(WithdrawRequest $request)
    {
        $this->request = $request;
        $coachName = $request->user?->name ?? 'A coach';
        $this->title = 'New payout request from ' . $coachName;
        $this->body = 'Amount: ' . formatMoney($request->withdraw_amount);
        $this->icon = 'fa-money-bill-transfer';
        $this->iconColor = '#f59e0b';
        $this->url = route('admin.show-withdraw', ['id' => $request->id]);
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'  => $notifiable->name ?? 'Admin',
            'coach_name' => (string) ($this->request->user?->name ?? 'A coach'),
            'amount'     => formatMoney($this->request->withdraw_amount),
        ];
    }
}
