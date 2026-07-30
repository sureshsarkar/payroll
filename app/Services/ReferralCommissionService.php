<?php

namespace App\Services;

use App\Models\ReferralCommission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\app\Models\Order;

/**
 * 2026-06-03 (Referral A+) — the SINGLE source of truth for order-based referral
 * commissions (System B), replacing the logic that previously lived inline in
 * Admin\OrderController::updateOrder.
 *
 * Lifecycle (audit-defined):
 *   (no row)                      — referred student has not paid yet
 *   eligible                      — a referred student's order is PAID
 *   approved                      — admin approved the commission
 *   credited                      — amount added to the referrer's wallet_balance
 *   rejected                      — invalid / self / duplicate / order failed-cancelled
 *   reversed                      — was credited, then the order was refunded/cancelled
 *
 * The reward is NEVER created on signup/enquiry — onOrderPaid() is the only entry
 * that mints a row, and it is called exclusively from the paid-order code paths.
 */
class ReferralCommissionService
{
    /** Configured commission rate (percent of the paid amount). */
    public function percent(): float
    {
        return (float) (cache()->get('setting')?->referral_commission_percent ?? 10);
    }

    /**
     * Called from EVERY path that marks a course order Paid (online gateway via
     * PaymentFulfilmentService::markPaid, coach-manual, admin, API). Idempotent:
     * the (order_id, referrer_user_id) unique key + the status check below mean
     * repeated calls never double-credit.
     */
    public function onOrderPaid(Order $order): ?ReferralCommission
    {
        // Defensive: only ever act on a genuinely-paid order.
        if (($order->payment_status ?? null) !== 'paid') {
            return null;
        }

        $buyer = User::find($order->buyer_id);
        if (!$buyer || !$buyer->referred_by_user_id) {
            return null; // buyer wasn't referred → nothing to do
        }

        // Self-referral guard (defence-in-depth; signup also prevents it).
        if ((int) $buyer->referred_by_user_id === (int) $buyer->id) {
            return null;
        }

        $referrer = User::find($buyer->referred_by_user_id);
        if (!$referrer) {
            return null; // referrer deleted → skip
        }

        $percent = $this->percent();
        if ($percent <= 0) {
            return null;
        }

        $amount = round((float) $order->paid_amount * ($percent / 100), 2);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $buyer, $referrer, $percent, $amount) {
            $commission = ReferralCommission::firstOrCreate(
                ['order_id' => $order->id, 'referrer_user_id' => $referrer->id],
                [
                    'referred_user_id' => $buyer->id,
                    'amount'           => $amount,
                    'currency'         => $order->payable_currency ?: 'INR',
                    'percent'          => $percent,
                    'status'           => ReferralCommission::STATUS_ELIGIBLE,
                    'eligible_at'      => now(),
                ]
            );

            // If a prior commission for this order had been reversed/rejected
            // (e.g. refunded then re-paid), bring it back to eligible — fresh
            // payment, fresh entitlement. Already-eligible/approved/credited
            // rows are left untouched (idempotent).
            if (in_array($commission->status, [ReferralCommission::STATUS_REVERSED, ReferralCommission::STATUS_REJECTED], true)) {
                $commission->update([
                    'status'          => ReferralCommission::STATUS_ELIGIBLE,
                    'eligible_at'     => now(),
                    'reversed_at'     => null,
                    'reversal_reason' => null,
                    'amount'          => $amount,
                    'percent'         => $percent,
                ]);
            }

            return $commission->fresh();
        });
    }

    /**
     * Called from EVERY path that flips a paid order away from paid
     * (refunded / cancelled / unpaid). Reverses the commission:
     *   - credited → claw the amount back out of the wallet, mark 'reversed'
     *   - eligible/approved (not yet paid out) → mark 'rejected'
     * Idempotent: rows already reversed/rejected are skipped.
     */
    public function onOrderReversed(Order $order, string $reason = ''): int
    {
        $commissions = ReferralCommission::where('order_id', $order->id)
            ->whereIn('status', [
                ReferralCommission::STATUS_ELIGIBLE,
                ReferralCommission::STATUS_APPROVED,
                ReferralCommission::STATUS_CREDITED,
            ])
            ->get();

        $affected = 0;
        foreach ($commissions as $c) {
            DB::transaction(function () use ($c, $reason, &$affected) {
                if ($c->status === ReferralCommission::STATUS_CREDITED) {
                    // Claw the money back out of the withdrawable wallet.
                    $referrer = User::lockForUpdate()->find($c->referrer_user_id);
                    if ($referrer) {
                        $referrer->decrement('wallet_balance', (float) $c->amount);
                    }
                    $c->status      = ReferralCommission::STATUS_REVERSED;
                    $c->reversed_at = now();
                } else {
                    // Never paid out → just invalidate.
                    $c->status = ReferralCommission::STATUS_REJECTED;
                }
                $c->reversal_reason = \Str::limit($reason ?: 'Order payment reversed', 500, '');
                $c->paid_at = null;
                $c->save();
                $affected++;
            });
        }

        if ($affected > 0) {
            Log::info("Referral commission reversed for order #{$order->id} ({$affected} row(s)): {$reason}");
        }

        return $affected;
    }

    /** Admin: eligible → approved (no money moved yet). */
    public function approve(ReferralCommission $c): ReferralCommission
    {
        if ($c->status === ReferralCommission::STATUS_ELIGIBLE) {
            $c->status      = ReferralCommission::STATUS_APPROVED;
            $c->approved_at = now();
            $c->save();
        }
        return $c->fresh();
    }

    /** Admin: approved|eligible → credited; credits the referrer's wallet once. */
    public function credit(ReferralCommission $c): ReferralCommission
    {
        if (!in_array($c->status, [ReferralCommission::STATUS_ELIGIBLE, ReferralCommission::STATUS_APPROVED], true)) {
            return $c->fresh(); // already credited/rejected/reversed → no-op
        }

        return DB::transaction(function () use ($c) {
            $referrer = User::lockForUpdate()->find($c->referrer_user_id);
            if ($referrer && (float) $c->amount > 0) {
                $referrer->increment('wallet_balance', (float) $c->amount);
            }
            $c->status      = ReferralCommission::STATUS_CREDITED;
            $c->credited_at = now();
            $c->approved_at = $c->approved_at ?: now();
            $c->paid_at     = now(); // legacy column kept in sync
            $c->save();
            return $c->fresh();
        });
    }

    /** Admin: reject/reverse. Credited rows are clawed back; others just rejected. */
    public function reject(ReferralCommission $c, string $reason = ''): ReferralCommission
    {
        return DB::transaction(function () use ($c, $reason) {
            if ($c->status === ReferralCommission::STATUS_CREDITED) {
                $referrer = User::lockForUpdate()->find($c->referrer_user_id);
                if ($referrer) {
                    $referrer->decrement('wallet_balance', (float) $c->amount);
                }
                $c->status      = ReferralCommission::STATUS_REVERSED;
                $c->reversed_at = now();
            } else {
                $c->status = ReferralCommission::STATUS_REJECTED;
            }
            $c->reversal_reason = \Str::limit($reason, 500, '');
            $c->paid_at = null;
            $c->save();
            return $c->fresh();
        });
    }
}
