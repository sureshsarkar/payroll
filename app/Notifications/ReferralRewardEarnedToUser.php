<?php

namespace App\Notifications;

use App\Models\Referral;

/**
 * Sent to the referrer when their referral activates a membership and the
 * reward credit lands in their non-withdrawable referral wallet.
 */
class ReferralRewardEarnedToUser extends InAppNotification
{
    protected string $emailTemplate = 'notif_referral_reward';
    protected string $emailCtaLabel = 'Open referral panel';

    private Referral $referral;

    public function __construct(Referral $referral)
    {
        $this->referral = $referral;
        $name = $referral->referred?->name ?? 'Your referral';
        $this->title = "You earned a referral reward!";
        $this->body = $name . ' just activated their membership. ' . formatMoney($referral->reward_amount) . ' credited to your referral wallet.';
        $this->icon = 'fa-gift';
        $this->iconColor = '#10b981';
        $this->url = route('referral.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'      => $notifiable->name ?? '',
            'referred_name'  => (string) ($this->referral->referred?->name ?? ''),
            'reward_amount'  => formatMoney($this->referral->reward_amount),
        ];
    }
}
