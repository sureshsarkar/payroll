<?php

namespace App\Services;

use App\Models\CoachStudentLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 2026-06-24 (Phase 4) — read-only billing summary for a coach: their active
 * Super-Admin plan, the commission/capacity/settlement it dictates, and the
 * lifetime revenue/commission split from paid orders of their courses. Used by
 * BOTH the coach's read-only "My Plan & Billing" view and the admin coach
 * billing report. Tenant-safe: every figure is scoped to the one coach.
 */
class CoachBillingService
{
    /**
     * @return array{
     *   plan:?\App\Models\MembershipPlan, subscription:float, setup_fee:?float,
     *   setup_custom:bool, commission_rate:float, capacity:?int, capacity_used:int,
     *   capacity_unlimited:bool, gross:float, gross_catalogue:float, platform_commission:float,
     *   coach_revenue:float, by_currency:array, primary_currency:string,
     *   is_multi_currency:bool, wallet_balance:float, payout_required:bool,
     *   direct_settlement:bool, expires_at:?\Illuminate\Support\Carbon
     * }
     */
    public function summary(User|int $coach): array
    {
        // 2026-06-25 (Phase 7) — accept an already-loaded User so the admin
        // billing report can eager-load activeMembership.plan once for the page
        // instead of re-fetching + lazy-loading per coach (was ~5 queries × N).
        $coach      = $coach instanceof User ? $coach : User::find($coach);
        $coachId    = (int) ($coach?->id ?? 0);
        $plan       = $coach?->activePlan();
        $membership = $coach?->activeMembership;

        // AUD-003 (Release 1) — was SUM(order_items.price) [raw, PRE-coupon
        // catalogue price] for both gross and the commission base, which
        // over-stated gross/commission/coach-revenue on any coupon order and did
        // NOT reconcile with the coach wallet. Now delegated to the authoritative
        // FinancialReportingService, which bases gross + commission on per-item
        // netPaid (post-coupon) — the exact basis credited to the wallet. The raw
        // catalogue value is preserved separately as 'gross_catalogue'.
        $fin      = new FinancialReportingService();
        $money    = $fin->coachLifetime($coachId);
        $gross    = $money['gross'];   // NET settled revenue (post-coupon)
        $platform = $money['platform_commission'];

        // AUD-028 — per-currency split so a coach who sold in more than one currency is
        // never shown a single combined figure. UNKNOWN groups any null/invalid currency.
        $byCurrency  = $fin->coachLifetimeByCurrency($coachId);
        $primaryCur  = $fin->primaryCurrency();
        $multiCur    = count($byCurrency) > 1;

        return [
            'plan'               => $plan,
            'subscription'       => (float) ($plan->price ?? 0),
            'setup_fee'          => $plan ? (float) $plan->setup_fee : null,
            'setup_custom'       => (bool) ($plan->setup_fee_custom ?? false),
            'commission_rate'    => $coach ? $coach->effectiveCommissionRate() : 0.0,
            'capacity'           => $plan?->student_capacity,
            'capacity_used'      => (int) CoachStudentLink::where('coach_id', $coachId)->where('status', 'active')->count(),
            'capacity_unlimited' => $plan ? $plan->isUnlimitedStudents() : true,
            'gross'              => $gross,
            'gross_catalogue'    => $money['gross_catalogue'],
            'platform_commission'=> round($platform, 2),
            'coach_revenue'      => $money['coach_revenue'],
            'by_currency'        => $byCurrency,      // AUD-028 per-currency breakdown
            'primary_currency'   => $primaryCur,
            'is_multi_currency'  => $multiCur,
            'wallet_balance'     => (float) ($coach->wallet_balance ?? 0),
            'payout_required'    => $coach ? $coach->coachPayoutRequired() : true,
            'direct_settlement'  => $coach ? $coach->coachUsesDirectSettlement() : false,
            'expires_at'         => $membership?->expires_at,
        ];
    }
}
