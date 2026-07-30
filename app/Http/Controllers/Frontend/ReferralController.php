<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralWalletTransaction;
use App\Models\UserMembership;
use Illuminate\Http\Request;

/**
 * The user-facing referral page — code, link, stats, wallet history.
 * Distinct from AffiliateController (which shows the cash-commission stream).
 */
class ReferralController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Trigger the User::referral_code accessor so first-time visitors get a code.
        $code = $user->referral_code;
        // 2026-07-07 — point the share link straight at registration (the page
        // prefills referral_code from ?ref and TrackReferralCookie still drops
        // the mbs_ref cookie, so the code is preserved either way).
        $link = rtrim(config('app.url'), '/') . '/register?ref=' . $code;

        // Per-status counts
        $myReferrals = Referral::where('referrer_user_id', $user->id);
        $stats = [
            'total'     => (clone $myReferrals)->count(),
            'pending'   => (clone $myReferrals)->where('status', 'pending')->count(),
            'rewarded'  => (clone $myReferrals)->where('status', 'rewarded')->count(),
            'rejected'  => (clone $myReferrals)->whereIn('status', ['rejected', 'reversed'])->count(),
            'lifetime_earned' => (float) (clone $myReferrals)->where('status', 'rewarded')->sum('reward_amount'),
        ];

        $recentReferrals = (clone $myReferrals)
            ->with('referred:id,name,email,role,created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Wallet history (credits + debits)
        $wallet = [
            'balance' => (float) $user->referral_wallet_balance,
            'transactions' => ReferralWalletTransaction::where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
        ];

        // Membership credit usage history (where the wallet was applied)
        $membershipUsage = UserMembership::where('user_id', $user->id)
            ->where('wallet_credit_used', '>', 0)
            ->with('plan:id,name')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('frontend.referral.index', compact(
            'code', 'link', 'stats', 'recentReferrals', 'wallet', 'membershipUsage'
        ));
    }
}
