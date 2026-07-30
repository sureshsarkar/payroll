<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\UserMembership;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(private MembershipService $service) {}

    /**
     * Plan picker — shows only plans matching the user's role + 'all'.
     */
    public function index()
    {
        $user = auth()->user();
        $plans = MembershipPlan::activeForRoleCached($user->role ?? 'student');

        // 2026-06-25 — one-time trial: hide the Free Trial plan from coaches who
        // already used it, and flag it so the view can explain why. Tenant-safe
        // (checked for this coach only). Non-coaches are unaffected.
        $trial = app(\App\Services\CoachTrialService::class);
        $trialUsed = $trial->isHeadCoach($user) && $trial->hasUsedTrial($user);
        if ($trialUsed) {
            $plans = $plans->reject(fn ($p) => $trial->isTrialPlan($p))->values();
        }

        $current = $this->service->currentFor($user);
        $history = UserMembership::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->with('plan')
            ->get();

        // One-click renewal: surface their LAST paid (non-trial) plan when the
        // user is in the expiring (last 7 days) or expired window. Trials are
        // ignored on purpose — pushing trialers to "renew their trial" makes
        // no sense; they should pick a paid plan.
        $renewalPlan = null;
        $renewalState = null; // 'expiring' | 'expired' | null
        $shouldShowRenewal = false;
        if ($current && $current->payment_method !== 'trial' && $current->expires_at && $current->expires_at->lessThanOrEqualTo(now()->addDays(7))) {
            $renewalState = 'expiring';
            $shouldShowRenewal = true;
        } elseif (!$current) {
            // No active membership — find the most recent expired paid plan.
            $expired = UserMembership::where('user_id', $user->id)
                ->where('status', 'expired')
                ->where('payment_method', '!=', 'trial')
                ->orderByDesc('id')
                ->first();
            if ($expired) {
                $renewalState = 'expired';
                $shouldShowRenewal = true;
                $current = $expired; // re-purpose for the view's plan-name lookup
            }
        }
        if ($shouldShowRenewal && $current?->plan_id) {
            $renewalPlan = MembershipPlan::active()->find($current->plan_id);
            // If the previous plan is no longer active (admin removed/disabled), drop the CTA.
            if (!$renewalPlan) {
                $shouldShowRenewal = false;
            }
        }

        return view('frontend.membership.index', compact(
            'plans', 'current', 'history', 'renewalPlan', 'renewalState', 'shouldShowRenewal', 'trialUsed'
        ));
    }

    /**
     * Checkout page — shows price quote with wallet-credit option.
     */
    public function checkout(int $planId)
    {
        $user = auth()->user();
        $plan = MembershipPlan::active()->forRole($user->role ?? 'student')->findOrFail($planId);

        // 2026-06-25 — Enterprise is sales-led (custom quote / direct settlement),
        // never self-checkout. Block the checkout page (incl. direct URL with the
        // enterprise plan id) and route to Contact us.
        if ($plan->isEnterprise()) {
            return redirect()->route('contact.index')->with([
                'messege'    => __('Enterprise plans are tailored to you — please contact our team for a custom quote.'),
                'alert-type' => 'info',
            ]);
        }

        // One-time trial: block the checkout page itself for a re-claim attempt
        // (e.g. direct URL with the trial plan id).
        $trial = app(\App\Services\CoachTrialService::class);
        if ($trial->isTrialPlan($plan) && $trial->hasUsedTrial($user)) {
            return redirect()->route('membership.index')->with([
                'messege'    => __(\App\Exceptions\TrialAlreadyUsedException::USER_MESSAGE),
                'alert-type' => 'warning',
            ]);
        }

        $quoteWith    = $this->service->quote($plan, $user, true);
        $quoteWithout = $this->service->quote($plan, $user, false);

        return view('frontend.membership.checkout', compact('plan', 'quoteWith', 'quoteWithout'));
    }

    /**
     * Process checkout. For now, paid portions are stubbed as 'manual' —
     * admin marks them paid in the admin user-memberships UI. Wallet-only
     * checkouts activate immediately.
     */
    public function pay(Request $request, int $planId)
    {
        $user = auth()->user();
        $plan = MembershipPlan::active()->forRole($user->role ?? 'student')->findOrFail($planId);

        // Enterprise is sales-led — block self-purchase (incl. crafted POST).
        if ($plan->isEnterprise()) {
            return redirect()->route('contact.index')->with([
                'messege'    => __('Enterprise plans are tailored to you — please contact our team for a custom quote.'),
                'alert-type' => 'info',
            ]);
        }

        $request->validate([
            'apply_wallet'   => 'nullable|boolean',
            'payment_method' => 'nullable|string|in:manual,razorpay,stripe',
        ]);

        $applyWallet  = (bool) $request->input('apply_wallet', true);
        $paymentMethod = $request->input('payment_method', 'manual');

        // Backend one-time-trial enforcement — startCheckout throws if this is a
        // duplicate trial claim (covers modified plan id / replayed payload / API).
        try {
            $membership = $this->service->startCheckout($user, $plan, $applyWallet, $paymentMethod);
        } catch (\App\Exceptions\TrialAlreadyUsedException $e) {
            return redirect()->route('membership.index')->with([
                'messege'    => __($e->getMessage()),
                'alert-type' => 'warning',
            ]);
        }

        // Wallet covered the entire price → already activated.
        if ($membership->status === 'active') {
            return redirect()->route('membership.index')->with([
                'messege'    => __('Membership activated using your referral wallet.'),
                'alert-type' => 'success',
            ]);
        }

        // Hand off to Razorpay for the cash portion.
        if ($paymentMethod === 'razorpay') {
            return redirect()->route('membership.razorpay.show', $membership->id);
        }

        // Manual / bank transfer → pending until admin confirms.
        return redirect()->route('membership.index')->with([
            'messege'    => __('Membership request created. Awaiting payment confirmation.'),
            'alert-type' => 'info',
        ]);
    }
}
