<?php

namespace App\Http\Middleware\API;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AppDemoModeMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response {
        // Use config() not env(): once `php artisan config:cache` runs on the
        // server, env() outside config files returns null, which would drop the
        // ENTIRE API into demo mode (blocking login + all writes) even when
        // APP_MODE=LIVE. config('app.app_mode') reads the cached value safely.
        if (strtoupper((string) config('app.app_mode')) !== 'LIVE') {
            // Auth routes must stay usable in demo mode. NOTE the login route is
            // named `api.patient-login` (not `api.login`) — the old list missed
            // it, so sign-in was blocked in demo mode. `api.2fa.verify` lets a
            // 2FA-enabled login complete its challenge.
            $allowedRoutes = [
                'api.register', 'api.login', 'api.patient-login',
                'api.forget-password', 'api.reset-password', 'api.2fa.verify',
                'api.logout', 'api.logoutAllApp',
            ];
            if (request()->routeIs(...$allowedRoutes) || request()->method() == 'GET') {
                return $next($request);
            } else {
                return response()->json(['status' => 'error', 'message' => 'In Demo Mode You Can Not Perform This Action'], 403);
            }
        }
        return $next($request);
    }
}
