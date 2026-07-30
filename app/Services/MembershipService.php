<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\UserMembership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single point of control for membership lifecycle. Anything that "activates"
 * a membership (admin, payment-callback, wallet-only checkout, manual fix-up)
 * must go through {@see activate()} so the referral reward fires consistently.
 *
 * Other modules are encouraged to subscribe to the 'membership.activated'
 * event/hook by checking the returned UserMembership and dispatching their own
 * notifications/rewards. The referral system uses {@see ReferralRewardService}
 * which is wired directly into activate() to award the referrer.
 */
class MembershipService
{
    /**
     * Compute price for a plan given the user's available wallet credit.
     * Returns: ['final', 'wallet_used', 'plan_price']
     */
    public function quote(MembershipPlan $plan, User $user, bool $applyWalletCredit = true): array
    {
        $price = (float) $plan->price;
        $available = $applyWalletCredit ? (float) ($user->referral_wallet_balance ?? 0) : 0;

        $walletUsed = min($price, $available);
        $final = max(0, $price - $walletUsed);

        return [
            'plan_price'  => round($price, 2),
            'wallet_used' => round($walletUsed, 2),
            'final'       => round($final, 2),
        ];
    }

    /**
     * Create a UserMembership row in 'pending' state. Used at checkout — gets
     * activated by activate() once payment clears.
     *
     * If the wallet covers the full price (final == 0), this immediately calls
     * activate() and returns an active membership.
     */
    public function startCheckout(User $user, MembershipPlan $plan, bool $applyWalletCredit = true, string $paymentMethod = 'manual'): UserMembership
    {
        // 2026-06-25 — One-time free-trial guard. ALL trial claims funnel through
        // CoachTrialService::grantTrial (which blocks re-use, persists the flag,
        // and audits). This is the single backend chokepoint that no entry point
        // — controller, API, wallet path, replayed/modified payload — can bypass.
        $trial = app(\App\Services\CoachTrialService::class);
        if ($trial->isTrialPlan($plan)) {
            $trial->assertCanClaimTrial($user);          // throws TrialAlreadyUsedException if used
            $granted = $trial->grantTrial($user);        // create + mark trial_used_at + audit
            if (! $granted) {
                throw new \App\Exceptions\TrialAlreadyUsedException();
            }
            return $granted;
        }

        $quote = $this->quote($plan, $user, $applyWalletCredit);

        return DB::transaction(function () use ($user, $plan, $quote, $paymentMethod) {
            // Lock + deduct wallet credit immediately so it's not double-spent.
            if ($quote['wallet_used'] > 0) {
                /** @var \App\Services\ReferralWalletService $wallet */
                $wallet = app(\App\Services\ReferralWalletService::class);
                $wallet->debit(
                    $user,
                    $quote['wallet_used'],
                    'membership_checkout',
                    'Applied to membership: ' . $plan->name
                );
            }

            $membership = UserMembership::create([
                'user_id'            => $user->id,
                'plan_id'            => $plan->id,
                'price_paid'         => 0, // updated by activate() once payment clears
                'wallet_credit_used' => $quote['wallet_used'],
                'payment_method'     => $paymentMethod,
                'payment_status'     => $quote['final'] == 0 ? 'paid' : 'pending',
                'status'             => 'pending',
            ]);

            // Free-via-wallet path → activate immediately.
            if ($quote['final'] == 0) {
                $this->activate($membership, paidAmount: 0, transactionId: 'WALLET-' . $membership->id);
            }

            return $membership->fresh();
        });
    }

    /**
     * Mark a pending membership as active. THIS is the trigger point for
     * referral reward award — see ReferralRewardService::onMembershipActivated.
     */
    public function activate(UserMembership $membership, float $paidAmount = 0, ?string $transactionId = null): UserMembership
    {
        return DB::transaction(function () use ($membership, $paidAmount, $transactionId) {
            // Idempotency — already active, just return.
            if ($membership->status === 'active' && $membership->payment_status === 'paid') {
                return $membership;
            }

            $now = now();
            $membership->started_at     = $now;
            $membership->expires_at     = $membership->plan?->duration_days > 0
                ? $now->copy()->addDays((int) $membership->plan->duration_days)
                : null;
            $membership->price_paid     = $paidAmount;
            $membership->payment_status = 'paid';
            $membership->status         = 'active';
            if ($transactionId) {
                $membership->transaction_id = $transactionId;
            }
            $membership->save();

            // Hook: award referral reward to whoever referred this user, if anyone.
            try {
                /** @var \App\Services\ReferralRewardService $reward */
                $reward = app(\App\Services\ReferralRewardService::class);
                $reward->onMembershipActivated($membership);
            } catch (\Throwable $e) {
                \Log::warning('Referral reward on membership activation failed: ' . $e->getMessage());
            }

            // Notify the member themselves — confirmation of activation.
            try {
                $user = $membership->user ?? \App\Models\User::find($membership->user_id);
                if ($user) {
                    $user->notify(new \App\Notifications\MembershipActivatedToUser($membership));
                }
            } catch (\Throwable $e) {
                \Log::warning('Notify user of membership activation failed: ' . $e->getMessage());
            }

            return $membership;
        });
    }

    /**
     * Grant a coach free-trial membership. Distinct from {@see activate} — this
     * path:
     *   • does NOT fire the referral reward hook (trials shouldn't farm rewards)
     *   • does NOT send the "membership activated" notification (caller decides)
     *   • is idempotent: returns the existing active membership if the user
     *     already has one
     *   • silently no-ops if the trial plan is inactive (admin disabled trials)
     *
     * Returns the granted membership, or null if no trial plan is available.
     */
    public function grantTrial(User $user, ?int $overrideDays = null): ?UserMembership
    {
        // 2026-06-25 — delegated to the configurable CoachTrialService (admin-set
        // length/grace/enable in settings). The old here-hardcoded "plan
        // duration_days else 15" path is gone; $overrideDays is accepted for
        // backward-compat but the configured length is authoritative.
        return app(\App\Services\CoachTrialService::class)->grantTrial($user);
    }

    /**
     * Returns the user's currently-active membership (paid + within window) or null.
     */
    public function currentFor(User $user): ?UserMembership
    {
        return UserMembership::where('user_id', $user->id)
            ->active()
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Sweep expired memberships → 'expired'. Called by a daily scheduled command.
     */
    public function expirePastDue(): int
    {
        // Loop (vs bulk update) so each member gets an expiry notification.
        $due = UserMembership::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($due as $membership) {
            $membership->status = 'expired';
            $membership->save();

            try {
                $user = $membership->user ?? User::find($membership->user_id);
                if ($user) {
                    $user->notify(new \App\Notifications\MembershipExpiredToUser($membership));
                }
            } catch (\Throwable $e) {
                \Log::warning('Membership-expired notify failed: ' . $e->getMessage());
            }
        }

        return $due->count();
    }
}
