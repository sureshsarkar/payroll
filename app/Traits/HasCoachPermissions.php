<?php

namespace App\Traits;

use App\Services\CoachPermissionService;

/**
 * Convenience for controllers: enforce a coach-panel permission in one line
 * (2026-07-04) so the resolver logic is never duplicated. A real coach always
 * passes; a staff member must hold at least one of the given slugs.
 *
 *   use HasCoachPermissions;
 *   public function store(...) { $this->ensurePermission('coach-coupons-create'); ... }
 */
trait HasCoachPermissions
{
    protected function ensurePermission(string ...$slugs): void
    {
        $svc  = app(CoachPermissionService::class);
        $user = auth('web')->user();

        if ($svc->isRealCoach($user) || (! empty($slugs) && $svc->canAny($user, $slugs))) {
            return;
        }
        abort(403);
    }

    protected function coachCan(string $slug): bool
    {
        return app(CoachPermissionService::class)->can(auth('web')->user(), $slug);
    }
}
