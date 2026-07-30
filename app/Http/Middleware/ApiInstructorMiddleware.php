<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2026-06-09 (security audit) — role gate for the instructor/coach API.
 *
 * The web coach panel is gated by InstructorMiddleware ('instructorrole'),
 * but the API instructor endpoints (CoachDashboardController) were behind
 * 'auth:sanctum' ONLY — so any authenticated token, INCLUDING a student's,
 * could invoke coach actions (create/update/delete courses, students,
 * announcements, sales). Queries are scoped to auth()->id() so there is no
 * cross-COACH data leak, but a student must not be able to act as a coach.
 *
 * Same coach/coach-staff rule as InstructorMiddleware, but returns a JSON
 * 403/401 (never a redirect — this is an API surface).
 */
class ApiInstructorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status'  => false,
                'message' => __('Unauthenticated.'),
            ], 401);
        }

        // A real coach is a top-level account (coach_id NULL); staff carry a
        // coach_id and any role except student/admin. A student is rejected
        // regardless of a stale/leaked coach_id (data-corruption defence).
        $isCoach      = $user->role === 'instructor' && empty($user->coach_id);
        $isCoachStaff = ! empty($user->coach_id)
            && ! in_array($user->role, ['student', 'admin'], true);

        if (! $isCoach && ! $isCoachStaff) {
            return response()->json([
                'status'  => false,
                'message' => __('Forbidden. This area is for coaches only.'),
            ], 403);
        }

        return $next($request);
    }
}
