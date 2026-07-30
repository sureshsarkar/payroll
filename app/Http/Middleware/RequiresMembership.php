<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate routes behind an active paid membership. Apply with:
 *
 *   Route::middleware('requires.membership')->group(...)
 *   Route::get('/coach/live-class/store', ...)->middleware('requires.membership');
 *
 * Behaviour:
 *   • not authenticated → bounce to login
 *   • no active membership → flash a friendly message + redirect to /membership
 *   • active membership → pass through
 *
 * For JSON / API requests, returns 403 with {error, code} so the frontend can
 * show its own modal.
 *
 * Admin sessions are exempt (admins always have access). Site-owners can
 * bypass the gate by setting MEMBERSHIP_ENFORCE=false in .env — useful while
 * the system is still being rolled out.
 */
class RequiresMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        // Master kill switch for ops / staged rollout.
        if (env('MEMBERSHIP_ENFORCE', true) === false || env('MEMBERSHIP_ENFORCE') === '0') {
            return $next($request);
        }

        // Admins are exempt.
        if (auth('admin')->check()) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $this->reject($request, 'unauthenticated');
        }

        // Auto-grace: if no active plans exist FOR THIS USER'S ROLE, the gate is
        // meaningless (nothing for them to buy). F9 (audit 2026-06-26) — this was
        // a global any-plan-exists check, so inactivating the instructor plans
        // silently disabled the paywall for EVERYONE. Now role-scoped + cached.
        $role = $user->role ?? 'student';
        $plansExist = \Cache::remember('membership_has_active_plans_' . $role, 300, function () use ($role) {
            return \App\Models\MembershipPlan::active()
                ->where(fn ($q) => $q->where('role', $role)->orWhere('role', 'all'))
                ->exists();
        });
        if (!$plansExist) {
            return $next($request);
        }

        // Resolve to head-coach for staff. Coach-staff are role=instructor with
        // coach_id set — they share their head coach's membership. For the
        // head coach themselves (or anyone else), this is the user's own id.
        $effectiveUserId = ($user->role === 'instructor' && !empty($user->coach_id))
            ? (int) $user->coach_id
            : (int) $user->id;

        $hasMembership = \App\Models\UserMembership::where('user_id', $effectiveUserId)
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->exists();

        if ($hasMembership) {
            return $next($request);
        }

        // Legacy compatibility: pre-existing SubscriptionHistory rows on the
        // effective user continue to grant access until end_date passes.
        if (class_exists(\Modules\Subscription\app\Models\SubscriptionHistory::class)) {
            $sub = \Modules\Subscription\app\Models\SubscriptionHistory::where('user_id', $effectiveUserId)
                ->orderByDesc('id')->first();
            if ($sub && !empty($sub->end_date) && $sub->end_date >= now()) {
                return $next($request);
            }
        }

        // 2026-05-26 — Auto-grant the free trial to coaches who never
        // received one (e.g. they registered before the trial system
        // existed, or their User::created listener failed). Without
        // this they hit the paywall even though policy says every
        // coach starts on the trial. The grant is idempotent: if a
        // row already exists for this user + trial plan, nothing
        // changes. Only fires for role=instructor; staff are gated
        // through their head coach's id already (above).
        if ($user->role === 'instructor' && $user->id === $effectiveUserId) {
            try {
                $granted = app(\App\Services\MembershipService::class)->grantTrial($user);
                if ($granted) {
                    \Log::info('requires-membership-auto-trial', [
                        'user_id' => $user->id,
                        'plan_id' => $granted->plan_id,
                        'ends_at' => (string) $granted->expires_at,
                    ]);
                    return $next($request);
                }
            } catch (\Throwable $e) {
                \Log::warning('requires-membership-auto-trial-failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 2026-06-25 — configurable trial grace / after-policy. A coach whose
        // trial (or plan) just lapsed is allowed through the gate while inside
        // the admin-configured grace window, and indefinitely if the after-policy
        // is 'none'. Past grace with 'soft'/'hard' → fall through to reject.
        try {
            if (app(\App\Services\CoachTrialService::class)->allowsPremium($user)) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            // Service failure must never harden the gate beyond the legacy check.
        }

        return $this->reject($request, 'no_active_membership');
    }

    private function reject(Request $request, string $code): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $code,
                'message' => $code === 'unauthenticated'
                    ? __('Please log in to continue.')
                    : __('This feature requires an active membership.'),
            ], $code === 'unauthenticated' ? 401 : 403);
        }

        if ($code === 'unauthenticated') {
            return redirect()->guest(route('login'))->with([
                'messege' => __('Please log in to continue.'), 'alert-type' => 'info',
            ]);
        }

        return redirect()->route('membership.index')->with([
            'messege'    => __('This feature requires an active membership. Pick a plan to continue.'),
            'alert-type' => 'info',
        ]);
    }
}
