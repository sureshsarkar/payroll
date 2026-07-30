<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MembershipPlanController extends Controller
{
    /**
     * Allowed sort keys → DB columns. M6 fix (2026-05-12).
     */
    private const SORTABLE = [
        'name'          => 'name',
        'role'          => 'role',
        'price'         => 'price',
        'duration_days' => 'duration_days',
        'status'        => 'status',
        'sort_order'    => 'sort_order',
    ];

    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('membership-plan.view');

        // M6 fix (2026-05-12). When no explicit sort key is requested we
        // preserve the historical default ordering (sort_order ASC, price ASC)
        // so existing admin muscle memory isn't disrupted. An explicit
        // ?sort=name (etc.) overrides that.
        $sortKey = (string) $request->query('sort', '');
        if ($sortKey === '' || !isset(self::SORTABLE[$sortKey])) {
            $plans = MembershipPlan::orderBy('sort_order')->orderBy('price')->get();
            $sortKey = 'sort_order';
            $sortDir = 'asc';
        } else {
            $sortCol = self::SORTABLE[$sortKey];
            $sortDir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $plans = MembershipPlan::orderBy($sortCol, $sortDir)->get();
        }

        return view('admin.membership.plans.index', [
            'plans' => $plans,
            'sort'  => $sortKey,
            'dir'   => $sortDir,
        ]);
    }

    public function create()
    {
        checkAdminHasPermissionAndThrowException('membership-plan.create');
        return view('admin.membership.plans.create');
    }

    public function store(Request $request)
    {
        checkAdminHasPermissionAndThrowException('membership-plan.store');
        $data = $this->validateRequest($request);
        $data['slug'] = $this->makeSlug($data['name']);
        $data['features'] = $this->splitFeatures($request->input('features_text'));
        $plan = MembershipPlan::create($data);

        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::PLAN_CHANGED, 'pricing-plan', $plan,
            null, $this->auditSnapshot($plan), 'Pricing plan created: ' . $plan->name
        );

        return redirect()->route('admin.membership-plans.index')->with([
            'messege' => __('Plan created'), 'alert-type' => 'success',
        ]);
    }

    public function edit(MembershipPlan $plan)
    {
        checkAdminHasPermissionAndThrowException('membership-plan.edit');
        return view('admin.membership.plans.edit', compact('plan'));
    }

    public function update(Request $request, MembershipPlan $plan)
    {
        checkAdminHasPermissionAndThrowException('membership-plan.update');
        $before = $this->auditSnapshot($plan);
        $data = $this->validateRequest($request, $plan->id);
        if ($data['name'] !== $plan->name) $data['slug'] = $this->makeSlug($data['name']);
        $data['features'] = $this->splitFeatures($request->input('features_text'));
        $plan->update($data);

        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::PLAN_CHANGED, 'pricing-plan', $plan,
            $before, $this->auditSnapshot($plan->fresh()), 'Pricing plan updated: ' . $plan->name
        );

        return redirect()->route('admin.membership-plans.index')->with([
            'messege' => __('Plan updated'), 'alert-type' => 'success',
        ]);
    }

    public function destroy(MembershipPlan $plan)
    {
        checkAdminHasPermissionAndThrowException('membership-plan.delete');
        // Soft-block — refuse to delete plans that have memberships attached.
        if ($plan->memberships()->exists()) {
            return back()->with([
                'messege' => __('Plan has active memberships — set it to Inactive instead.'),
                'alert-type' => 'error',
            ]);
        }
        $snapshot = $this->auditSnapshot($plan);
        $name = $plan->name;
        $plan->delete();

        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::PLAN_CHANGED, 'pricing-plan', null,
            $snapshot, null, 'Pricing plan deleted: ' . $name
        );

        return back()->with(['messege' => __('Plan deleted'), 'alert-type' => 'success']);
    }

    /** The config fields we audit on every plan change. */
    private function auditSnapshot(MembershipPlan $plan): array
    {
        return $plan->only([
            'name', 'tier', 'role', 'price', 'setup_fee', 'setup_fee_custom',
            'platform_commission_rate', 'commission_min_rate', 'commission_max_rate',
            'student_capacity', 'payout_required', 'direct_settlement', 'status',
        ]);
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        // FT-VAL-18 (2026-05-28) — add price ceiling + bound
        // duration_days to >= 1. Pre-fix:
        //  • price had `min:0` only — could be 999_999_999
        //    (every gateway rejects but the plan row persists with
        //     absurd price, confusing UI + reporting).
        //  • duration_days = 0 was allowed — plan expires the
        //    instant it's activated. The trial-plan path treats
        //    duration_days = 0 specially elsewhere, but for paid
        //    plans this just means the customer pays then loses
        //    access immediately.
        //  • sort_order had no bounds — display-side only, but cap
        //    keeps DB hygiene.
        // Same shape as FT-IDOR-16 (Subscription) and FT-VAL-9 (API
        // createCourse).
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'role'          => ['required', 'in:student,instructor,all'],
            'price'         => ['required', 'numeric', 'min:0', 'max:9999999'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:36500'],
            'status'        => ['required', 'in:active,inactive'],
            'sort_order'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            // 2026-06-24 — pricing-plan fields (Super-Admin only)
            'tier'                     => ['required', 'in:starter,medium,enterprise,custom'],
            'setup_fee'                => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'setup_fee_custom'         => ['nullable', 'boolean'],
            'platform_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_min_rate'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_max_rate'      => ['nullable', 'numeric', 'min:0', 'max:100', 'gte:commission_min_rate'],
            'student_capacity'         => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'payout_required'          => ['nullable', 'boolean'],
            'direct_settlement'        => ['nullable', 'boolean'],
        ]);

        // Checkboxes: absent = false. Normalise so unchecked persists correctly.
        $data['setup_fee_custom']  = $request->boolean('setup_fee_custom');
        $data['payout_required']   = $request->boolean('payout_required');
        $data['direct_settlement'] = $request->boolean('direct_settlement');
        $data['setup_fee']         = $data['setup_fee'] ?? 0;

        return $data;
    }

    private function makeSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $i = 1;
        while (MembershipPlan::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    private function splitFeatures(?string $text): array
    {
        if (!$text) return [];
        return collect(preg_split('/\r?\n/', $text))->map(fn ($l) => trim($l))->filter()->values()->all();
    }
}
