<?php

namespace App\Http\Middleware;

use App\Models\MembershipPlan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a coach-panel feature to coaches on an ACTIVE Enterprise membership.
 *
 * Used by the coach-self-managed payment gateway screens. Non-Enterprise
 * coaches (Starter / trial / expired / none) are blocked at the route level —
 * not just hidden from the menu — so direct-URL access is denied server-side.
 *
 * Staff accounts inherit their head coach's plan via User::activePlan(), so a
 * staff member of an Enterprise coach is allowed; a staff member of a non-
 * Enterprise coach is not.
 */
class RequiresEnterpriseMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        $plan = $user->activePlan();
        if ($plan instanceof MembershipPlan && $plan->isEnterprise()) {
            return $next($request);
        }

        $message = __('Coach-specific payment gateway configuration is available only for Enterprise Membership plans. Please upgrade your plan to enable this feature.');

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'requires_enterprise',
                'message' => $message,
            ], 403);
        }

        $redirect = \Illuminate\Support\Facades\Route::has('instructor.membership.index')
            ? route('instructor.membership.index')
            : (\Illuminate\Support\Facades\Route::has('instructor.dashboard') ? route('instructor.dashboard') : url('/'));

        return redirect($redirect)->with([
            'messege'    => $message,
            'alert-type' => 'warning',
        ]);
    }
}
