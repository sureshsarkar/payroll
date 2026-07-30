<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {

                $user = Auth::guard($guard)->user();

                // F8 (audit 2026-06-26) — explicit role branches. The previous
                // condition (role==='instructor' || role!=='student') was a
                // tautology that funnelled ANY non-student/blank/corrupted role to
                // the coach dashboard. Default unknown roles to the least-privileged
                // (student) destination instead.
                if ($user->role === 'admin') {
                    return redirect(RouteServiceProvider::ADMIN);
                }

                if ($user->role === 'instructor') {
                    return redirect(RouteServiceProvider::INSTRUCTOR_DASHBORD);
                }

                return redirect(RouteServiceProvider::STUDENT_DASHBORD);
            }
        }

        return $next($request);
    }
}
