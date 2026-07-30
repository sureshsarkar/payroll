<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralWalletTransaction;
use App\Models\UserMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile-app companion to Frontend\ReferralController.
 *
 * Returns the same data the web page renders (code + link + stats +
 * recent referrals + wallet balance + wallet transactions + membership
 * usage history) as a single JSON payload, keyed by snake_case to match
 * the rest of the API.
 *
 * Read-only — the wallet/referral mutations happen via the existing
 * web pay-in flows (referrals get rewarded automatically on the
 * referred user's first paid membership), so the mobile app doesn't
 * need to mutate anything here.
 *
 * Route: GET /api/referral  (auth:sanctum)
 */
class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Trigger the User::referral_code accessor so first-time
        // visitors actually get a code generated server-side.
        $code = $user->referral_code;
        // 2026-07-07 — mirror the web page: link straight to registration.
        $link = rtrim(config('app.url'), '/') . '/register?ref=' . $code;

        // Per-status counts — mirror the web page exactly.
        $myReferrals = Referral::where('referrer_user_id', $user->id);

        $stats = [
            'total'           => (clone $myReferrals)->count(),
            'pending'         => (clone $myReferrals)->where('status', 'pending')->count(),
            'rewarded'        => (clone $myReferrals)->where('status', 'rewarded')->count(),
            'rejected'        => (clone $myReferrals)->whereIn('status', ['rejected', 'reversed'])->count(),
            'lifetime_earned' => (float) (clone $myReferrals)->where('status', 'rewarded')->sum('reward_amount'),
        ];

        $recentReferrals = (clone $myReferrals)
            ->with('referred:id,name,email,role,created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($r) {
                return [
                    'id'             => $r->id,
                    'status'         => $r->status,
                    'reward_amount'  => (float) $r->reward_amount,
                    'rewarded_at'    => $r->rewarded_at?->toIso8601String(),
                    'created_at'     => $r->created_at?->toIso8601String(),
                    'referred'       => $r->referred ? [
                        'id'         => $r->referred->id,
                        'name'       => $r->referred->name,
                        // Mask the email beyond the first character
                        // and the domain — the page is user-visible
                        // and exposing referred-friend emails verbatim
                        // is a minor privacy leak.
                        'email'      => $this->maskEmail((string) $r->referred->email),
                        'role'       => $r->referred->role,
                        'created_at' => $r->referred->created_at?->toIso8601String(),
                    ] : null,
                ];
            });

        // Wallet history: balance + most-recent 20 transactions.
        $walletTx = ReferralWalletTransaction::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($t) {
                return [
                    'id'            => $t->id,
                    'amount'        => (float) $t->amount,
                    'balance_after' => (float) $t->balance_after,
                    'type'          => $t->type,
                    'description'   => $t->description,
                    'created_at'    => $t->created_at?->toIso8601String(),
                ];
            });

        $wallet = [
            'balance'      => (float) ($user->referral_wallet_balance ?? 0),
            'transactions' => $walletTx,
        ];

        // Membership credit usage history — where the wallet was spent.
        $membershipUsage = UserMembership::where('user_id', $user->id)
            ->where('wallet_credit_used', '>', 0)
            ->with('plan:id,name')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($m) {
                return [
                    'id'                 => $m->id,
                    'plan_name'          => $m->plan?->name,
                    'wallet_credit_used' => (float) $m->wallet_credit_used,
                    'price_paid'         => (float) $m->price_paid,
                    'created_at'         => $m->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'code'             => $code,
                'link'             => $link,
                'stats'            => $stats,
                'recent_referrals' => $recentReferrals,
                'wallet'           => $wallet,
                'membership_usage' => $membershipUsage,
            ],
        ]);
    }

    /**
     * Mask an email so only the first char of the local-part and the
     * domain are visible. `studentpanel@gmail.com` → `s***@gmail.com`.
     * Cheap privacy guard for the per-referral list.
     */
    private function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) return $email;
        [$local, $domain] = explode('@', $email, 2);
        $head = mb_substr($local, 0, 1);
        return $head . '***@' . $domain;
    }
}
