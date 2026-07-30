<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserMembership;
use App\Services\MembershipService;
use Illuminate\Http\Request;

/**
 * Admin view of every membership purchase. Used to:
 *  - confirm pending payments (manual mode)
 *  - cancel/refund
 *  - audit which users are active/expired
 */
class UserMembershipController extends Controller
{
    public function __construct(private MembershipService $service) {}

    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('user-membership.view');
        $q = UserMembership::query()->with(['user:id,name,email,role', 'plan:id,name,price']);

        if ($status = $request->get('status')) $q->where('status', $status);
        if ($payment = $request->get('payment_status')) $q->where('payment_status', $payment);
        if ($keyword = trim((string) $request->get('q', ''))) {
            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%$keyword%")->orWhere('email', 'like', "%$keyword%"));
        }

        $memberships = $q->orderByDesc('id')->paginate(25)->withQueryString();
        return view('admin.membership.users.index', compact('memberships'));
    }

    /* ---------------- Phase 4 — assign coach plan + billing report ---------------- */

    public function assignForm()
    {
        checkAdminHasPermissionAndThrowException('user-membership.confirm');
        $coaches = \App\Models\User::where('role', 'instructor')->orderBy('name')->get(['id', 'name', 'email']);
        $plans   = \App\Models\MembershipPlan::whereIn('role', ['instructor', 'all'])
            ->where('status', 'active')->orderBy('sort_order')->orderBy('price')->get();

        return view('admin.membership.users.assign', compact('coaches', 'plans'));
    }

    public function assign(Request $request)
    {
        checkAdminHasPermissionAndThrowException('user-membership.confirm');
        $data = $request->validate([
            'coach_id' => ['required', 'integer', 'exists:users,id'],
            'plan_id'  => ['required', 'integer', 'exists:membership_plans,id'],
        ]);

        $coach = \App\Models\User::where('id', $data['coach_id'])->where('role', 'instructor')->firstOrFail();
        $plan  = \App\Models\MembershipPlan::findOrFail($data['plan_id']);

        // F17 (audit 2026-06-26) — never assign the one-time TRIAL plan here: it
        // would set payment_method='admin' and silently bypass the trial_used_at
        // flag, letting a coach re-claim a free trial. Trials go via CoachTrialService.
        if (app(\App\Services\CoachTrialService::class)->isTrialPlan($plan)) {
            return redirect()->back()->with([
                'messege'    => __('The free-trial plan cannot be assigned manually. Assign a paid plan instead.'),
                'alert-type' => 'error',
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($coach, $plan) {
            // Retire any current active membership, then make the new one active.
            UserMembership::where('user_id', $coach->id)->where('status', 'active')->update(['status' => 'cancelled']);

            $attrs = [
                'user_id'        => $coach->id,
                'plan_id'        => $plan->id,
                'status'         => 'active',
                'payment_status' => 'paid',
                'started_at'     => now(),
                'expires_at'     => $plan->duration_days > 0 ? now()->addDays($plan->duration_days) : null,
                'price_paid'     => 0,
                'payment_method' => 'admin',
            ];
            // HOTFIX 2026-06-26 — the F17 unconditional `billing_period` => 'monthly'
            // 500'd assign() on any DB that hadn't run the annual-billing migration
            // ("Unknown column 'billing_period'"). Set it ONLY where the column
            // exists; elsewhere the DB default / null-coalescing handles it.
            if (\Illuminate\Support\Facades\Schema::hasColumn('user_memberships', 'billing_period')) {
                $attrs['billing_period'] = 'monthly';
            }
            UserMembership::create($attrs);
        });

        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::PLAN_ASSIGNED, 'pricing-plan', $coach,
            null, ['plan' => $plan->slug, 'plan_id' => $plan->id],
            'Assigned plan "' . $plan->name . '" to ' . $coach->name
        );

        return redirect()->route('admin.user-memberships.billing')
            ->with(['messege' => __('Plan assigned to :coach', ['coach' => $coach->name]), 'alert-type' => 'success']);
    }

    public function billing(Request $request)
    {
        checkAdminHasPermissionAndThrowException('user-membership.view');
        $keyword = trim((string) $request->get('q', ''));
        // 2026-06-25 (Phase 7) — eager-load activeMembership.plan so each row's
        // summary() reads the already-loaded relation instead of re-fetching the
        // user + lazy-loading membership/plan per coach (was a ~125-query page).
        $coaches = \App\Models\User::with('activeMembership.plan')
            ->where('role', 'instructor')
            ->when($keyword !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$keyword%")->orWhere('email', 'like', "%$keyword%")))
            ->orderBy('name')->paginate(25)->withQueryString();

        $svc  = app(\App\Services\CoachBillingService::class);
        $rows = $coaches->getCollection()->map(fn ($c) => ['coach' => $c, 'summary' => $svc->summary($c)]);

        return view('admin.membership.users.billing', compact('coaches', 'rows', 'keyword'));
    }

    /**
     * Mark a pending membership as paid + active. Triggers referral reward.
     */
    public function confirm(Request $request, UserMembership $membership)
    {
        checkAdminHasPermissionAndThrowException('user-membership.confirm');
        $request->validate([
            'paid_amount'    => ['required', 'numeric', 'min:0'],
            'transaction_id' => ['nullable', 'string', 'max:191'],
        ]);

        $this->service->activate(
            $membership,
            paidAmount: (float) $request->paid_amount,
            transactionId: $request->transaction_id ?: null
        );

        return back()->with(['messege' => __('Membership activated'), 'alert-type' => 'success']);
    }

    public function cancel(UserMembership $membership)
    {
        checkAdminHasPermissionAndThrowException('user-membership.cancel');
        $membership->update(['status' => 'cancelled']);
        return back()->with(['messege' => __('Cancelled'), 'alert-type' => 'success']);
    }

    /**
     * Full membership refund. Three things happen:
     *  1) The membership is marked refunded + cancelled.
     *  2) Any referral wallet credit the user spent on it is restored.
     *  3) Any referral reward that was awarded TO the user's referrer for this
     *     membership is clawed back (status=reversed, balance debited).
     *
     * The cash side (Razorpay/Stripe) is NOT auto-refunded — that has to be
     * done manually in the gateway dashboard. We just log the intent here.
     */
    public function refund(\Illuminate\Http\Request $request, UserMembership $membership)
    {
        checkAdminHasPermissionAndThrowException('user-membership.refund');
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($membership->payment_status === 'refunded') {
            return back()->with(['messege' => __('Already refunded'), 'alert-type' => 'info']);
        }

        return \DB::transaction(function () use ($membership, $request) {
            // 1) Restore any wallet credit they applied at checkout.
            $walletRestored = (float) $membership->wallet_credit_used;
            if ($walletRestored > 0) {
                $user = \App\Models\User::find($membership->user_id);
                if ($user) {
                    app(\App\Services\ReferralWalletService::class)->credit(
                        $user,
                        $walletRestored,
                        'admin_adjustment',
                        'Refund — restored wallet credit applied to membership #' . $membership->id,
                        $membership
                    );
                }
            }

            // 2) Reverse any referral reward that fired off THIS membership.
            $referral = \App\Models\Referral::where('membership_id', $membership->id)
                ->where('status', 'rewarded')
                ->first();
            if ($referral) {
                app(\App\Services\ReferralRewardService::class)->reject(
                    $referral,
                    'Membership refunded · ' . \Str::limit((string) $request->input('reason', ''), 200)
                );
            }

            // 3) Mark the membership refunded + cancelled.
            $membership->update([
                'status'         => 'cancelled',
                'payment_status' => 'refunded',
            ]);

            $msg = __('Refund processed.');
            if ($walletRestored > 0) $msg .= ' ' . __('Wallet credit of :amt restored.', ['amt' => currency($walletRestored)]);
            if ($referral)           $msg .= ' ' . __('Referral reward reversed.');

            return back()->with(['messege' => $msg, 'alert-type' => 'success']);
        });
    }

    /**
     * Trial → paid conversion report. The killer metric for any trial-based
     * platform: are people actually upgrading after the trial?
     *
     * Computes funnel + cohort stats. Default cohort is the last 90 days but
     * admin can pick a different range via ?from=YYYY-MM-DD&to=YYYY-MM-DD.
     */
    public function conversionReport(Request $request)
    {
        checkAdminHasPermissionAndThrowException('user-membership.view');
        $from = $request->get('from')
            ? \Carbon\Carbon::parse($request->get('from'))->startOfDay()
            : now()->subDays(90)->startOfDay();
        $to = $request->get('to')
            ? \Carbon\Carbon::parse($request->get('to'))->endOfDay()
            : now()->endOfDay();

        // Cohort: users whose first trial started in this window.
        $trialsInWindow = UserMembership::where('payment_method', 'trial')
            ->whereBetween('started_at', [$from, $to])
            ->get();

        $coachIds = $trialsInWindow->pluck('user_id')->unique()->values();

        // For each cohort coach, find any paid (non-trial) membership.
        $paidByCoach = UserMembership::whereIn('user_id', $coachIds)
            ->where('payment_method', '!=', 'trial')
            ->where('payment_status', 'paid')
            ->orderBy('started_at')
            ->get()
            ->groupBy('user_id');

        $converted = 0;
        $stillTrial = 0;
        $churned = 0;
        $totalTimeToPaid = 0; // seconds
        $convertedToPlan = []; // plan_id => count

        foreach ($trialsInWindow as $trial) {
            $paid = $paidByCoach->get($trial->user_id)?->first();
            if ($paid) {
                $converted++;
                if ($paid->started_at && $trial->started_at) {
                    $totalTimeToPaid += $paid->started_at->diffInSeconds($trial->started_at);
                }
                $convertedToPlan[$paid->plan_id] = ($convertedToPlan[$paid->plan_id] ?? 0) + 1;
            } else {
                if ($trial->status === 'active' && $trial->expires_at && $trial->expires_at->isFuture()) {
                    $stillTrial++;
                } else {
                    $churned++;
                }
            }
        }

        $totalTrials = count($trialsInWindow);
        $conversionRate = $totalTrials > 0 ? round(($converted / $totalTrials) * 100, 1) : 0;
        $avgDaysToPaid = $converted > 0 ? round($totalTimeToPaid / $converted / 86400, 1) : 0;

        // Resolve the most-converted-to plan name
        $plansById = \App\Models\MembershipPlan::whereIn('id', array_keys($convertedToPlan))->pluck('name', 'id');
        arsort($convertedToPlan);

        // Recent cohort sample for the table at the bottom
        $sample = $trialsInWindow->take(25)->map(function ($trial) use ($paidByCoach) {
            $paid = $paidByCoach->get($trial->user_id)?->first();
            return [
                'user_id'       => $trial->user_id,
                'user'          => \App\Models\User::find($trial->user_id),
                'trial_started' => $trial->started_at,
                'trial_status'  => $trial->status,
                'paid_at'       => $paid?->started_at,
                'paid_plan'     => $paid?->plan?->name,
                'days_to_paid'  => $paid && $trial->started_at && $paid->started_at
                    ? round($paid->started_at->diffInSeconds($trial->started_at) / 86400, 1)
                    : null,
            ];
        });

        return view('admin.membership.users.conversion', [
            'from'             => $from,
            'to'               => $to,
            'totalTrials'      => $totalTrials,
            'converted'        => $converted,
            'stillTrial'       => $stillTrial,
            'churned'          => $churned,
            'conversionRate'   => $conversionRate,
            'avgDaysToPaid'    => $avgDaysToPaid,
            'convertedToPlan'  => $convertedToPlan,
            'plansById'        => $plansById,
            'sample'           => $sample,
        ]);
    }

    /**
     * Extend a membership by N days. Useful for customer service. Pushes
     * expires_at out (or sets it from today if it was already in the past).
     */
    public function extend(Request $request, UserMembership $membership)
    {
        checkAdminHasPermissionAndThrowException('user-membership.extend');
        $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);
        $days = (int) $request->days;

        // F10 (audit 2026-06-26) — never resurrect a REFUNDED membership through
        // "extend": it would silently flip payment_status back to paid and undo
        // the refund. Force the admin to assign a fresh plan instead.
        if ($membership->payment_status === 'refunded' || $membership->status === 'refunded') {
            return back()->with([
                'messege'    => __('A refunded membership cannot be extended. Assign a new plan instead.'),
                'alert-type' => 'error',
            ]);
        }

        $base = $membership->expires_at && $membership->expires_at->isFuture()
            ? $membership->expires_at
            : now();

        $membership->expires_at = $base->copy()->addDays($days);

        // If the row was expired/cancelled, reactivate it.
        if (in_array($membership->status, ['expired', 'cancelled'])) {
            $membership->status = 'active';
        }
        if ($membership->payment_status !== 'paid') {
            $membership->payment_status = 'paid';
        }
        $membership->save();

        // F10 — audit trail (extend was previously silent, unlike assign/confirm).
        try {
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::PLAN_ASSIGNED, 'pricing-plan', $membership->user,
                ['action' => 'extend', 'days' => $days, 'membership_id' => $membership->id, 'new_expiry' => (string) $membership->expires_at]
            );
        } catch (\Throwable $e) { /* audit is best-effort, never block the action */ }

        return back()->with([
            'messege'    => __(":n days added — new expiry :date", ['n' => $days, 'date' => $membership->expires_at->format('M d, Y')]),
            'alert-type' => 'success',
        ]);
    }
}
