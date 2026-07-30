<?php

namespace App\Services;

use App\Models\ReferralWalletTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single API for moving money in/out of users.referral_wallet_balance.
 *
 * Every change MUST go through this service so the ledger row in
 * referral_wallet_transactions stays in sync with the running balance, and so
 * we have one authoritative place to enforce business rules:
 *  - balance never goes negative
 *  - debits are restricted to type='membership_checkout' or 'admin_adjustment'
 *  - withdrawals to bank/cash are NOT supported here (intentional — this
 *    wallet is non-withdrawable, see migration comment)
 */
class ReferralWalletService
{
    /**
     * Add to the user's referral wallet. Used by ReferralRewardService when
     * a referral becomes eligible.
     */
    public function credit(
        User $user,
        float $amount,
        string $type,
        string $description = '',
        ?Model $relatedTo = null
    ): ReferralWalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $relatedTo) {
            // Re-fetch with row lock so concurrent credits don't race the running balance.
            $fresh = User::lockForUpdate()->find($user->id);
            $newBalance = (float) $fresh->referral_wallet_balance + $amount;

            $fresh->referral_wallet_balance = $newBalance;
            $fresh->save();

            return ReferralWalletTransaction::create([
                'user_id'       => $fresh->id,
                'amount'        => round($amount, 2),
                'balance_after' => round($newBalance, 2),
                'type'          => $type,
                'related_type'  => $relatedTo ? $relatedTo::class : null,
                'related_id'    => $relatedTo?->getKey(),
                'description'   => \Str::limit($description, 500, ''),
            ]);
        });
    }

    /**
     * Deduct from the user's referral wallet. Throws if balance is insufficient.
     * Only types 'membership_checkout' and 'admin_adjustment' are accepted —
     * any other context would be a misuse of this wallet.
     */
    public function debit(
        User $user,
        float $amount,
        string $type,
        string $description = '',
        ?Model $relatedTo = null
    ): ReferralWalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive');
        }
        if (!in_array($type, ['membership_checkout', 'admin_adjustment', 'reversal'], true)) {
            throw new \InvalidArgumentException(
                "Referral wallet debit is only allowed for membership_checkout, admin_adjustment, or reversal — got '$type'"
            );
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $relatedTo) {
            $fresh = User::lockForUpdate()->find($user->id);
            $current = (float) $fresh->referral_wallet_balance;

            if ($current + 0.001 < $amount) {
                throw new \DomainException('Insufficient referral wallet balance');
            }

            $newBalance = round($current - $amount, 2);
            $fresh->referral_wallet_balance = $newBalance;
            $fresh->save();

            return ReferralWalletTransaction::create([
                'user_id'       => $fresh->id,
                'amount'        => -round($amount, 2),
                'balance_after' => $newBalance,
                'type'          => $type,
                'related_type'  => $relatedTo ? $relatedTo::class : null,
                'related_id'    => $relatedTo?->getKey(),
                'description'   => \Str::limit($description, 500, ''),
            ]);
        });
    }

    /**
     * Convenience: current balance.
     */
    public function balance(User $user): float
    {
        return (float) ($user->referral_wallet_balance ?? 0);
    }
}
