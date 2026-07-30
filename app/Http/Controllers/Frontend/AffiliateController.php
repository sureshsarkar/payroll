<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Affiliate / referral dashboard.
 *
 * GET /affiliate — shows the user's referral link, signup count, commissions
 * earned (pending + approved + paid), and a recent commissions table.
 */
class AffiliateController extends Controller
{
    public function index(Request $request): View
    {
        $u = $request->user();

        $referralUrl = $u->referral_url; // accessor — auto-generates code on first read

        $referredCount = $u->referrals()->count();
        $referredActive = $u->referrals()->where('status', 'active')->count();

        // 2026-06-03 (Referral A+) — corporate lifecycle buckets.
        $sumByStatus = fn (string $status) => (float) ReferralCommission::where('referrer_user_id', $u->id)
            ->where('status', $status)->sum('amount');

        $totals = [
            'eligible' => $sumByStatus(ReferralCommission::STATUS_ELIGIBLE),
            'approved' => $sumByStatus(ReferralCommission::STATUS_APPROVED),
            'credited' => $sumByStatus(ReferralCommission::STATUS_CREDITED),
            'reversed' => $sumByStatus(ReferralCommission::STATUS_REVERSED),
            'rejected' => $sumByStatus(ReferralCommission::STATUS_REJECTED),
        ];
        // Lifetime earned = everything not invalidated (eligible + approved + credited).
        $totals['lifetime'] = $totals['eligible'] + $totals['approved'] + $totals['credited'];
        // Back-compat aliases for any view still using the old keys.
        $totals['pending'] = $totals['eligible'];
        $totals['paid']    = $totals['credited'];

        $recentCommissions = ReferralCommission::where('referrer_user_id', $u->id)
            ->with(['referred:id,name,email', 'order:id,invoice_id,paid_amount'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $referralPercent = (float) (cache()->get('setting')?->referral_commission_percent ?? 10);

        return view('frontend.affiliate.index', compact(
            'referralUrl', 'referredCount', 'referredActive',
            'totals', 'recentCommissions', 'referralPercent'
        ));
    }
}
