<?php

namespace App\Services;

use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\StaffPermissionOverride;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for Coach-Panel permission resolution (2026-07-04).
 *
 * Priority (Step 6): staff override  >  role permission  >  deny.
 *
 * Backward compatibility: the LIVE enforcement gate stays `users_permissions`
 * (the exact set the legacy checkPermission()/checkPermissionView() helpers
 * read), so no existing staff's access changes until a coach re-saves them.
 * `syncStaff()` recomputes that gate from  role − revokes + grants  and writes
 * it back, so the resolver is the write-side authority while enforcement is
 * unchanged. A real coach (role=instructor, coach_id NULL) always has full
 * access; students/admins never hold coach-staff permissions (fail closed).
 */
class CoachPermissionService
{
    /** A top-level coach account (unrestricted on the coach panel). */
    public function isRealCoach($user): bool
    {
        return $user && ($user->role ?? null) === 'instructor' && empty($user->coach_id);
    }

    /** The owning coach id for any coach-panel actor (self for a coach, coach_id for staff). */
    public function coachIdFor($user): int
    {
        if (! $user) {
            return 0;
        }
        return ($user->role ?? null) === 'instructor' ? (int) $user->id : (int) ($user->coach_id ?? 0);
    }

    private function staff(int $userId): ?CoachStaff
    {
        return CoachStaff::with(['roles.permissions:id,slug', 'permissions:id,slug'])->find($userId);
    }

    /**
     * Permission ids granted by the staff member's ASSIGNED role(s). An INACTIVE
     * role (status != 1) grants nothing, and a deleted role simply isn't present.
     */
    public function rolePermissionIds(CoachStaff $staff): array
    {
        return $staff->roles
            ->where('status', 1)
            ->flatMap(fn ($r) => $r->permissions->pluck('id'))
            ->unique()
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    /** @return array{grant:int[], revoke:int[]} this staff member's explicit overrides. */
    public function overrideMap(int $userId): array
    {
        $rows = StaffPermissionOverride::where('user_id', $userId)->get(['coach_staff_permission_id', 'effect']);
        return [
            'grant'  => $rows->where('effect', StaffPermissionOverride::GRANT)->pluck('coach_staff_permission_id')->map(fn ($v) => (int) $v)->all(),
            'revoke' => $rows->where('effect', StaffPermissionOverride::REVOKE)->pluck('coach_staff_permission_id')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** Resolved effective permission ids: role − revokes + grants (override wins). */
    public function effectivePermissionIds(CoachStaff $staff): array
    {
        $role = $this->rolePermissionIds($staff);
        $ov   = $this->overrideMap((int) $staff->id);

        $set = array_diff($role, $ov['revoke']);                 // role minus revoked
        $set = array_values(array_unique(array_merge($set, $ov['grant']))); // plus granted
        return array_map('intval', $set);
    }

    /** Resolved effective permission SLUGS (used by the management UI + reporting). */
    public function effectivePermissionSlugs(CoachStaff $staff): array
    {
        $ids = $this->effectivePermissionIds($staff);
        if (empty($ids)) {
            return [];
        }
        return CoachStaffPermission::whereIn('id', $ids)->pluck('slug')->filter()->values()->all();
    }

    /**
     * The LIVE access decision. Uses the materialised users_permissions gate so
     * it matches the legacy helpers exactly (backward compatible). A real coach
     * is always allowed; students/admins are always denied.
     */
    public function can($user, string $slug): bool
    {
        if ($this->isRealCoach($user)) {
            return true;
        }
        if (in_array($user->role ?? null, ['student', 'admin', 'super-admin'], true)) {
            return false;
        }
        $staff = $this->staff((int) ($user->id ?? 0));
        if (! $staff) {
            return false;
        }
        return $staff->permissions->contains('slug', $slug);
    }

    /** True if the actor holds ANY of the given slugs (real coach → always true). */
    public function canAny($user, array $slugs): bool
    {
        if ($this->isRealCoach($user)) {
            return true;
        }
        foreach ($slugs as $slug) {
            if ($this->can($user, (string) $slug)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Persist a staff member's role + overrides and re-materialise the live gate.
     * $grantIds / $revokeIds are permission ids the coach explicitly toggled away
     * from the role default. Atomic; overrides fully replace the prior set for
     * this staff member. All scoped to $coachId (the owning coach) for audit.
     */
    public function syncStaff(CoachStaff $staff, ?int $roleId, array $grantIds, array $revokeIds, int $coachId): void
    {
        DB::transaction(function () use ($staff, $roleId, $grantIds, $revokeIds, $coachId) {
            $staff->roles()->sync($roleId ? [$roleId] : []);

            StaffPermissionOverride::where('user_id', $staff->id)->delete();
            $now  = now();
            $rows = [];
            foreach (array_unique(array_map('intval', $grantIds)) as $pid) {
                $rows[] = ['user_id' => $staff->id, 'coach_staff_permission_id' => $pid, 'effect' => StaffPermissionOverride::GRANT, 'added_by' => $coachId, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_unique(array_map('intval', $revokeIds)) as $pid) {
                // A permission can't be both granted and revoked (unique index);
                // an explicit grant takes precedence, so skip a conflicting revoke.
                if (in_array($pid, array_map('intval', $grantIds), true)) {
                    continue;
                }
                $rows[] = ['user_id' => $staff->id, 'coach_staff_permission_id' => $pid, 'effect' => StaffPermissionOverride::REVOKE, 'added_by' => $coachId, 'created_at' => $now, 'updated_at' => $now];
            }
            if ($rows) {
                StaffPermissionOverride::insert($rows);
            }

            // Materialise  role − revokes + grants  into the live users_permissions gate.
            $fresh     = $staff->fresh(['roles.permissions']);
            $effective = $this->effectivePermissionIds($fresh);
            $staff->permissions()->sync($effective);
        });
    }
}
