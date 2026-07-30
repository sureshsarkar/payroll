<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Role
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role = ''): Response
    {
        // SECURITY (audit 2026-06-12) — the previous body had INVERTED logic
        // (`role === 'instructor' || role !== 'student'` is true for almost
        // everyone) and *redirected* instead of denying, so `role:student`
        // would have sent users to the INSTRUCTOR dashboard. This generic alias
        // isn't wired to any active route today, but it must be a correct
        // deny-on-mismatch gate so it can never become a privilege-escalation
        // foot-gun. Rejects unauthenticated users and any role mismatch with 403.
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($role !== '' && (string) $user->role !== $role) {
            abort(403);
        }

        return $next($request);
    }
}
