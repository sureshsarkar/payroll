<?php

namespace App\Http\Middleware;

use App\Services\CoachPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level coach-panel permission gate (2026-07-04). Apply to any coach route
 * or API endpoint so access is blocked at the URL/API layer — not just hidden in
 * the UI. A real coach always passes; a staff member must hold at least ONE of
 * the given permission slugs (resolved via CoachPermissionService, which honours
 * the override > role > deny priority). Denials are a 403 (JSON for API/AJAX).
 *
 * Usage:
 *   Route::get('coupons', ...)->middleware('permission:coach-coupons');
 *   Route::post('coupons/store', ...)->middleware('permission:coach-coupons-create');
 *   ->middleware('permission:reports-export,reports-download')   // any-of
 */
class CoachPermission
{
    public function __construct(private CoachPermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string ...$slugs): Response
    {
        $user = auth('web')->user();

        if (! $user) {
            return $this->deny($request);
        }
        if ($this->permissions->isRealCoach($user)) {
            return $next($request);
        }
        if (! empty($slugs) && $this->permissions->canAny($user, $slugs)) {
            return $next($request);
        }

        return $this->deny($request);
    }

    private function deny(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => 'error',
                'message' => __('You are not authorized to perform this action.'),
            ], 403);
        }
        abort(403);
    }
}
