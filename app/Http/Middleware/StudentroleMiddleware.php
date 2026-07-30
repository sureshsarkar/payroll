<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentroleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role=''): Response
    {
        // 2026-06-01 (audit) — null-guard like InstructorMiddleware does;
        // don't dereference a null user if this ever runs before 'auth'.
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->role !== 'student') {
            return redirect()->route('logoutme');
        }
        return $next($request);
    }
}
