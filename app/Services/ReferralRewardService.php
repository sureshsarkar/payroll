<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Referral lifecycle:
 *  1. {@see attributeOnSignup}: when a new user registers, link them to the
 *     referrer (Referrals row, status=pending). All fraud guards run here.
 *  2. {@see onMembershipActivated}: when that user activates their first
 *     membership, award the configured reward to the referrer's referral
 *     wallet (or leave pending if admin-approval mode is on).
 *
 * The two halves are intentionally split so registration can succeed even if
 * the user never pays, AND so an admin can approve a held referral later
 * without the original signup needing to be replayed.
 */
class ReferralRewardService
{
    public function __construct(private ReferralWalletService $wallet) {}

    /**
     * Called from RegisteredUserController right after the new User row is
     * created. Looks up the referrer by code, runs fraud guards, and writes
     * a Referral row in 'pending' state. No reward is awarded here.
     *
     * Returns the created Referral, or null if attribution was skipped.
     */
    public function attributeOnSignup(User $newUser, ?string $code, ?string $ip = null): ?Referral
    {
        $settings = ReferralSetting::current();
        if (!$settings->enabled) return null;

        $code = trim((string) $code);
        if ($code === '') return null;

        $referrer = User::where('referral_code', $code)->first();
        if (!$referrer) return null;

        // Guard: self-referral
        if ($settings->block_self_referral && $referrer->id === $newUser->id) {
            Log::info("Referral blocked: self-referral by user {$newUser->id}");
            return null;
        }

        // Guard: same-IP referral (optional, opt-in)
        if ($settings->block_same_ip_referral && $ip) {
            $referrerIp = $referrer->last_login_ip ?? null;
            if ($referrerIp && $referrerIp === $ip) {
                Log::info("Referral blocked: same-IP — referrer {$referrer->id}, new {$newUser->id}");
                return null;
            }
        }

        // Guard: duplicate (one referral per referred user — DB unique enforces this too)
        if (Referral::where('referred_user_id', $newUser->id)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($referrer, $newUser, $code) {
            // Update the legacy referred_by_user_id columns too so the existing
            // affiliate flow (commissions on orders) keeps working alongside.
            $newUser->referred_by_user_id = $referrer->id;
            $newUser->referred_at         = now();
            $newUser->save();

            return Referral::create([
                'referrer_user_id' => $referrer->id,
                'referred_user_id' => $newUser->id,
                'referred_role'    => (string) ($newUser->role ?? 'student'),
                'referral_code'    => $code,
                'status'           => 'pending',
            ]);
        });
    }

    /**
     * Called from MembershipService::activate. If the activating user was
     * referred, award the configured reward to the referrer's wallet — once.
     */
    public function onMembershipActivated(UserMembership $membership): ?Referral
    {
        $settings = ReferralSetting::current();
        if (!$settings->enabled) return null;

        // Find a pending referral for this user.
        $referral = Referral::where('referred_user_id', $membership->user_id)
            ->where('status', 'pending')
            ->first();

        if (!$referral) return null;

        // Minimum membership-spend guard. Uses cash portion (price_paid) — wallet
        // credits don't count toward "real money" thresholds.
        if ((float) $settings->min_membership_amount > 0) {
            if ((float) $membership->price_paid + 0.001 < (float) $settings->min_membership_amount) {
                Log::info("Referral skipped: membership #{$membership->id} below min spend");
                return null;
            }
        }

        // Determine reward amount based on the referred user's role at signup.
        $reward = $referral->referred_role === 'instructor'
            ? (float) $settings->reward_when_referred_is_coach
            : (float) $settings->reward_when_referred_is_student;

        if ($reward <= 0) return null;

        return DB::transaction(function () use ($referral, $membership, $reward, $settings) {
            // If admin approval is required, just hold the row at 'pending'
            // with the membership_id snapshotted so the admin can approve later.
            if ($settings->require_admin_approval) {
                $referral->membership_id = $membership->id;
                $referral->reward_amount = $reward;
                $referral->save();
                return $referral->fresh();
            }

            // Auto-award path.
            $referrer = User::find($referral->referrer_user_id);
            if (!$referrer) return null;

            $this->wallet->credit(
                $referrer,
                $reward,
                'referral_reward',
                "Referral reward — {$membership->user?->name} activated " . ($membership->plan?->name ?? 'membership'),
                $referral
            );

            $referral->status        = 'rewarded';
            $referral->reward_amount = $reward;
            $referral->rewarded_at   = now();
            $referral->membership_id = $membership->id;
            $referral->save();

            // Notify referrer.
            try {
                if ($referrer && class_exists(\App\Notifications\ReferralRewardEarnedToUser::class)) {
                    $referrer->notify(new \App\Notifications\ReferralRewardEarnedToUser($referral));
                }
            } catch (\Throwable $e) {
                Log::warning('Notify referrer of reward failed: ' . $e->getMessage());
            }

            return $referral->fresh();
        });
    }

    /**
     * Admin manually approves a held referral.
     */
    public function approveByAdmin(Referral $referral): Referral
    {
        if ($referral->status !== 'pending') return $referral;

        return DB::transaction(function () use ($referral) {
            $referrer = User::find($referral->referrer_user_id);
            if ($referrer && (float) $referral->reward_amount > 0) {
                $this->wallet->credit(
                    $referrer,
                    (float) $referral->reward_amount,
                    'referral_reward',
                    'Admin-approved referral reward',
                    $referral
                );
            }
            $referral->status      = 'rewarded';
            $referral->rewarded_at = now();
            $referral->save();
            return $referral->fresh();
        });
    }

    /**
     * Admin rejects/reverses. If already rewarded, claws back the credit.
     */
    public function reject(Referral $referral, string $reason = ''): Referral
    {
        return DB::transaction(function () use ($referral, $reason) {
            // Claw back if already paid
            if ($referral->status === 'rewarded' && (float) $referral->reward_amount > 0) {
                $referrer = User::find($referral->referrer_user_id);
                if ($referrer && $this->wallet->balance($referrer) >= (float) $referral->reward_amount) {
                    $this->wallet->debit(
                        $referrer,
                        (float) $referral->reward_amount,
                        'reversal',
                        'Reversed: ' . \Str::limit($reason, 200),
                        $referral
                    );
                }
                $referral->status = 'reversed';
            } else {
                $referral->status = 'rejected';
            }

            $referral->rejection_reason = \Str::limit($reason, 500);
            $referral->save();
            return $referral->fresh();
        });
    }
}
