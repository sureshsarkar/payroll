<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstructorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Audit 2026-05-18 phase 5 — tightened.
     *
     * Original logic granted access if ANY of:
     *   - role === 'instructor', OR
     *   - coach_id is set
     *
     * That allowed students whose coach_id was accidentally set
     * (data corruption) to access the entire coach panel. We saw three
     * production rows in this state — 'Student Panel', 'Santosh',
     * 'santosh rai' — all role=student, all coach_id=1079.
     *
     * New rule: the user MUST be a coach or a coach-staff member.
     *   - role === 'instructor'                          → coach themselves
     *   - role NOT IN ('student','admin') AND coach_id   → staff with a
     *                                                       custom CoachStaffRole
     *
     * A student (role='student') is bounced regardless of coach_id —
     * a data-corruption defence so a leaked / stale coach_id never
     * escalates them.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role = ''): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('logoutme');
        }

        // 2026-06-01 — a real coach is a top-level account (coach_id NULL);
        // staff always carry coach_id. Pinning $isCoach to empty(coach_id)
        // means even a staff row whose role string is 'instructor' is treated
        // as STAFF (scoped), not a full coach.
        $isCoach      = $user->role === 'instructor' && empty($user->coach_id);
        $isCoachStaff = !empty($user->coach_id)
            && !in_array($user->role, ['student', 'admin'], true);

        if (!$isCoach && !$isCoachStaff) {
            // Audit 2026-05-18 phase 5 — for students, send them to their own
            // dashboard, not logout. Better UX than a flash of "you've been
            // logged out" when they merely visited the wrong URL.
            // LMS removal phase 2 (2026-08-27) — target moved from the deleted
            // `student.dashboard` (LMS) to `employee.overview` (HR).
            if ($user->role === 'student') {
                return redirect()->route('employee.overview')->with([
                    'messege'    => __('That section is for HR. We brought you to your employee dashboard.'),
                    'alert-type' => 'info',
                ]);
            }
            return redirect()->route('logoutme');
        }

        return $next($request);
    }
}
