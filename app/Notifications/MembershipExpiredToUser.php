<?php

namespace App\Notifications;

use App\Models\UserMembership;

/**
 * Sent when a membership/subscription lapses (the daily expirePastDue sweep
 * flips it to 'expired'). Platform-scoped — like MembershipActivatedToUser,
 * this is the platform talking to its member about their plan, so no coach
 * branding (coachId stays null).
 */
class MembershipExpiredToUser extends InAppNotification
{
    private UserMembership $membership;

    public function __construct(UserMembership $membership)
    {
        $this->membership = $membership;
        $planName = $membership->plan?->name ?? 'membership';

        $this->title     = 'Your ' . $planName . ' has expired';
        $this->body      = 'Your access has ended. Renew now to continue without interruption.';
        $this->icon      = 'fa-hourglass-end';
        $this->iconColor = '#ef4444';

        try {
            $this->url = route('membership.index');
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }
}
