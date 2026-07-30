<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Audit 2026-05-18 Req 3 — admin UI for per-coach commission overrides.
 *
 * The global commission rate lives at settings.commission_rate. Each
 * coach may have a personal override at users.commission_rate (set by
 * this controller). Coaches with NULL override fall back to global.
 *
 * Permission gate: reuses 'dashboard.view' for read; require Super Admin
 * (id=1) or the dedicated permission for write (which doesn't exist yet
 * but is gracefully fallen back from).
 */
class CoachCommissionController extends Controller
{
    /**
     * List all coaches with their current effective rate.
     */
    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('dashboard.view');

        $globalRate = (float) (cache('setting')?->commission_rate ?? 0);

        $query = User::query()
            ->where('role', 'instructor')
            ->select('id', 'name', 'email', 'image', 'commission_rate', 'commission_mode', 'commission_note', 'wallet_balance', 'created_at');

        if ($request->filled('q')) {
            $q = (string) $request->q;
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('mode')) {
            $mode = $request->mode;
            if ($mode === 'default') {
                $query->whereNull('commission_rate');
            } elseif ($mode === 'override') {
                $query->whereNotNull('commission_rate');
            } elseif (in_array($mode, ['temporary', 'permanent', 'free'], true)) {
                $query->where('commission_mode', $mode);
            }
        }

        $coaches = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.coach-commissions.index', compact('coaches', 'globalRate'));
    }

    /**
     * Show the edit form for one coach.
     */
    public function edit(int $id)
    {
        checkAdminHasPermissionAndThrowException('dashboard.view');

        $coach = User::where('role', 'instructor')->findOrFail($id);
        $globalRate = (float) (cache('setting')?->commission_rate ?? 0);

        // AUD-013 (Release 1) — recent earnings were scoped by orders.seller_id,
        // which the main web-gateway checkout never populates, so every gateway
        // sale was omitted (figure understated / zero). Now scoped by the course's
        // instructor_id via order_items, using NET settled revenue (post-coupon) —
        // the authoritative basis. Cast to object so the view's ->orders_30d /
        // ->gross_30d property access is unchanged.
        $fin = new \App\Services\FinancialReportingService();
        $recentEarnings = (object) $fin->coachRecentNet((int) $coach->id, 30);
        // AUD-028 — per-currency recent breakdown so a coach who sold in more than one
        // currency is never shown a single cross-currency "Gross (30d)" figure.
        $recentByCurrency = $fin->coachRecentByCurrency((int) $coach->id, 30);
        $primaryCurrency  = $fin->primaryCurrency();

        return view('admin.coach-commissions.edit', compact('coach', 'globalRate', 'recentEarnings', 'recentByCurrency', 'primaryCurrency'));
    }

    /**
     * Update one coach's commission.
     */
    public function update(Request $request, int $id)
    {
        // FT-IDOR-38 fix (2026-05-28) — was `dashboard.view`, a READ
        // permission. This method mutates a coach's commission rate
        // (or sets mode=free → 0% take from the platform), which is
        // a money-path config that affects every future sale by
        // that coach. Same shape as FT-IDOR-2 (Announcement write
        // on view perm) and FT-IDOR-29 (marketing settings write
        // on view perm).
        //
        // A read-only sub-admin (e.g. the "Content Editor" role,
        // which has dashboard.view in the FT-IDOR-24 audit) could
        // set a competitor coach's commission_rate to 100% (coach
        // earns 0 on every sale) or 0% (platform takes 0 — the
        // coach pockets every cent). Either is a sabotage path.
        //
        // Use `setting.update` — same slug FT-IDOR-28 used for the
        // GLOBAL commission_rate change. Per-coach override should
        // be at least as tightly gated as the global one.
        checkAdminHasPermissionAndThrowException('setting.update');

        $coach = User::where('role', 'instructor')->findOrFail($id);
        $oldCommission = ['commission_rate' => $coach->commission_rate, 'commission_mode' => $coach->commission_mode];

        $request->validate([
            'mode' => 'required|in:default,temporary,permanent,free',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'commission_note' => 'nullable|string|max:255',
        ]);

        // Resolution rule:
        //   - mode=default       → clear all override fields (use global)
        //   - mode=free          → set rate=0, mode=free
        //   - mode=temporary     → require rate; set both
        //   - mode=permanent     → require rate; set both
        $mode = $request->mode;
        if ($mode === 'default') {
            $coach->commission_rate = null;
            $coach->commission_mode = null;
        } elseif ($mode === 'free') {
            $coach->commission_rate = 0;
            $coach->commission_mode = 'free';
        } else {
            if ($request->commission_rate === null || $request->commission_rate === '') {
                return back()
                    ->withErrors(['commission_rate' => 'Rate is required for temporary or permanent overrides.'])
                    ->withInput();
            }
            $coach->commission_rate = (float) $request->commission_rate;
            $coach->commission_mode = $mode;
        }
        $coach->commission_note = $request->commission_note ?: null;
        $coach->save();

        // Enterprise H-A — audit the per-coach commission change (money config).
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::COMMISSION_CHANGED,
            'commission',
            $coach,
            $oldCommission,
            ['commission_rate' => $coach->commission_rate, 'commission_mode' => $coach->commission_mode],
            'Commission updated for ' . $coach->name
        );

        return redirect()->route('admin.coach-commissions.index')->with([
            'messege'    => __('Commission updated for :name.', ['name' => $coach->name]),
            'alert-type' => 'success',
        ]);
    }
}
