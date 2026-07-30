<?php

namespace App\Notifications;

use App\Models\UserMembership;

/**
 * Sent to the user themselves the moment their membership goes active —
 * fired from MembershipService::activate(), so it covers wallet-only,
 * Razorpay-paid, and admin-manual-confirm paths the same way.
 */
class MembershipActivatedToUser extends InAppNotification
{
    protected string $event = 'membership_activated';
    protected string $emailTemplate = 'notif_membership_activated';
    protected string $emailCtaLabel = 'Open membership';

    private UserMembership $membership;

    public function __construct(UserMembership $membership)
    {
        $this->membership = $membership;
        $planName = $membership->plan?->name ?? 'membership';

        $this->title = 'Your ' . $planName . ' is active';
        if ($membership->expires_at) {
            $this->body = 'Active until ' . $membership->expires_at->format('M d, Y') . '.';
        } else {
            $this->body = 'Lifetime access — enjoy!';
        }
        $this->icon = 'fa-shield-alt';
        $this->iconColor = '#10b981';
        $this->url = route('membership.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'      => $notifiable->name ?? '',
            'plan_name'      => (string) ($this->membership->plan?->name ?? 'membership'),
            'expires_at'     => $this->membership->expires_at?->format('M d, Y') ?? __('Lifetime'),
            'cash_paid'      => formatMoney($this->membership->price_paid),
            'wallet_used'    => formatMoney($this->membership->wallet_credit_used),
        ];
    }
}
