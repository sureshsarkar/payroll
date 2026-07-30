<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\ReferralWalletTransaction;
use App\Services\ReferralRewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    public function __construct(private ReferralRewardService $rewardService) {}

    /**
     * Reports + history index. Filters: status, role, keyword.
     */
    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('referral.view');
        $q = Referral::query()->with(['referrer:id,name,email,role', 'referred:id,name,email,role']);

        if ($status = $request->get('status'))   $q->where('status', $status);
        if ($role = $request->get('role'))       $q->where('referred_role', $role);
        if ($keyword = trim((string) $request->get('q', ''))) {
            $q->where(function ($x) use ($keyword) {
                $x->whereHas('referrer', fn ($r) => $r->where('name', 'like', "%$keyword%")->orWhere('email', 'like', "%$keyword%"))
                  ->orWhereHas('referred', fn ($r) => $r->where('name', 'like', "%$keyword%")->orWhere('email', 'like', "%$keyword%"));
            });
        }

        $referrals = $q->orderByDesc('id')->paginate(25)->withQueryString();

        // Summary metrics for the cards at top of the page.
        $totals = [
            'total_referrals'   => Referral::count(),
            'pending'           => Referral::where('status', 'pending')->count(),
            'rewarded'          => Referral::where('status', 'rewarded')->count(),
            'rejected'          => Referral::whereIn('status', ['rejected', 'reversed'])->count(),
            'reward_paid'       => (float) Referral::where('status', 'rewarded')->sum('reward_amount'),
            'wallet_outstanding'=> (float) DB::table('users')->sum('referral_wallet_balance'),
        ];

        return view('admin.referrals.index', compact('referrals', 'totals'));
    }

    public function settings()
    {
        checkAdminHasPermissionAndThrowException('referral.settings.view');
        $settings = ReferralSetting::current();
        return view('admin.referrals.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        checkAdminHasPermissionAndThrowException('referral.settings.update');
        // FT-VAL-3 fix (2026-05-28) — add upper bounds.
        // Pre-fix rules had `min:0` but no `max`. An admin (or a
        // typo in the form) could set reward_when_referred_is_coach
        // = 999_999_999 — every future approve() then credits that
        // amount to the referrer's wallet, draining the platform.
        // Cap to $10,000 per referral as a sanity ceiling; coaches
        // who genuinely need larger payouts should split into
        // multiple programs. Min-membership-amount needs an upper
        // bound too so it doesn't get set higher than any plan
        // exists (would silently disable all rewards).
        $data = $request->validate([
            'enabled'                          => ['nullable', 'boolean'],
            'reward_when_referred_is_student'  => ['required', 'numeric', 'min:0', 'max:10000'],
            'reward_when_referred_is_coach'    => ['required', 'numeric', 'min:0', 'max:10000'],
            'min_membership_amount'            => ['required', 'numeric', 'min:0', 'max:1000000'],
            'block_self_referral'              => ['nullable', 'boolean'],
            'block_same_ip_referral'           => ['nullable', 'boolean'],
            'require_admin_approval'           => ['nullable', 'boolean'],
        ], [
            'reward_when_referred_is_student.max' => __('Student referral reward cannot exceed 10,000'),
            'reward_when_referred_is_coach.max'   => __('Coach referral reward cannot exceed 10,000'),
            'min_membership_amount.max'           => __('Minimum membership amount is unreasonably high'),
        ]);

        $row = ReferralSetting::current();
        $row->fill([
            'enabled'                         => (bool) ($data['enabled'] ?? false),
            'reward_when_referred_is_student' => $data['reward_when_referred_is_student'],
            'reward_when_referred_is_coach'   => $data['reward_when_referred_is_coach'],
            'min_membership_amount'           => $data['min_membership_amount'],
            'block_self_referral'             => (bool) ($data['block_self_referral'] ?? false),
            'block_same_ip_referral'          => (bool) ($data['block_same_ip_referral'] ?? false),
            'require_admin_approval'          => (bool) ($data['require_admin_approval'] ?? false),
        ])->save();

        return back()->with(['messege' => __('Settings saved'), 'alert-type' => 'success']);
    }

    public function approve(Referral $referral)
    {
        checkAdminHasPermissionAndThrowException('referral.approve');
        $this->rewardService->approveByAdmin($referral);
        return back()->with(['messege' => __('Referral approved + reward credited'), 'alert-type' => 'success']);
    }

    public function reject(Request $request, Referral $referral)
    {
        checkAdminHasPermissionAndThrowException('referral.reject');
        $this->rewardService->reject($referral, (string) $request->input('reason', ''));
        return back()->with(['messege' => __('Referral rejected'), 'alert-type' => 'success']);
    }
}
