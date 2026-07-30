<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin view for affiliate commissions: list + approve/mark-paid/reverse.
 *
 * Routes:
 *   GET    /admin/referral-commissions               index
 *   POST   /admin/referral-commissions/{id}/approve
 *   POST   /admin/referral-commissions/{id}/pay
 *   POST   /admin/referral-commissions/{id}/reverse
 */
class ReferralCommissionController extends Controller
{
    public function updatePercent(Request $request): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('referral.update-percent');
        $request->validate(['percent' => ['required', 'numeric', 'between:0,100']]);
        \DB::table('settings')->updateOrInsert(
            ['key' => 'referral_commission_percent'],
            ['value' => (string) $request->percent, 'updated_at' => now(), 'created_at' => now()]
        );
        // Bust the cached settings object so the new % takes effect on next page load.
        \Cache::forget('setting');
        return back()->with(['messege' => __('Referral percent updated to :p%', ['p' => $request->percent]), 'alert-type' => 'success']);
    }

    public function index(Request $request): View
    {
        checkAdminHasPermissionAndThrowException('referral.view');
        $query = ReferralCommission::with(['referrer:id,name,email', 'referred:id,name,email', 'order:id,invoice_id,paid_amount']);

        $query->when($request->status, fn($q) => $q->where('status', $request->status));
        $query->when($request->keyword, function ($q) use ($request) {
            $kw = "%{$request->keyword}%";
            $q->whereHas('referrer', fn($r) => $r->where('name', 'like', $kw)->orWhere('email', 'like', $kw))
              ->orWhereHas('referred', fn($r) => $r->where('name', 'like', $kw)->orWhere('email', 'like', $kw));
        });

        $commissions = $query->orderByDesc('id')->paginate(30)->withQueryString();

        $totals = [
            'eligible' => (float) ReferralCommission::where('status', ReferralCommission::STATUS_ELIGIBLE)->sum('amount'),
            'approved' => (float) ReferralCommission::where('status', ReferralCommission::STATUS_APPROVED)->sum('amount'),
            'credited' => (float) ReferralCommission::where('status', ReferralCommission::STATUS_CREDITED)->sum('amount'),
            'rejected' => (float) ReferralCommission::where('status', ReferralCommission::STATUS_REJECTED)->sum('amount'),
            'reversed' => (float) ReferralCommission::where('status', ReferralCommission::STATUS_REVERSED)->sum('amount'),
        ];

        $referralPercent = (float) (\DB::table('settings')->where('key', 'referral_commission_percent')->value('value') ?? 10);

        return view('admin.referral-commissions.index', compact('commissions', 'totals', 'referralPercent'));
    }

    public function approve(string $id): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('referral.approve');
        $c = ReferralCommission::findOrFail($id);
        // 2026-06-03 (Referral A+) — route through the service (eligible→approved).
        app(\App\Services\ReferralCommissionService::class)->approve($c);
        return back()->with(['messege' => __('Commission approved'), 'alert-type' => 'success']);
    }

    public function pay(string $id): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('referral.pay');
        $c = ReferralCommission::findOrFail($id);
        // 2026-06-03 (Referral A+) — credit the referrer's withdrawable wallet
        // via the service (approved|eligible → credited; idempotent).
        app(\App\Services\ReferralCommissionService::class)->credit($c);
        return back()->with(['messege' => __('Commission credited to wallet'), 'alert-type' => 'success']);
    }

    public function reverse(string $id): RedirectResponse
    {
        checkAdminHasPermissionAndThrowException('referral.reverse');
        $c = ReferralCommission::findOrFail($id);
        // 2026-06-03 (Referral A+) — credited rows are clawed back (reversed);
        // not-yet-credited rows are rejected.
        app(\App\Services\ReferralCommissionService::class)
            ->reject($c, (string) request('reason', 'Reversed by admin'));
        return back()->with(['messege' => __('Commission reversed'), 'alert-type' => 'success']);
    }
}
