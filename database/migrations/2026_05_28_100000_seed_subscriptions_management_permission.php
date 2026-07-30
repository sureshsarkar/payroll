<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FT-IDOR-16 fix (2026-05-28) — register the `subscriptions.management`
 * admin permission so the SubscriptionController gates that this
 * commit re-enables actually resolve to something.
 *
 * Backstory:
 *   - `SubscriptionController` had 8 calls to
 *     `checkAdminHasPermissionAndThrowException('subscriptions.management')`
 *     commented out (dev-time bypass that survived merge — same shape as
 *     FT-IDOR-8 / FT-IDOR-10 / FT-IDOR-11).
 *   - The 2026_05_18_120000_seed_additional_admin_roles migration
 *     references this slug as part of the "Finance" role spec.
 *   - But the slug was never actually inserted into `permissions`. The
 *     PermissionsTrait that drives RolePermissionSeeder has slots for
 *     ~32 permission groups but no $subscriptionPermissions array.
 *
 *   Net effect: turning the gates back on with the slug missing from
 *   the permissions table would lock EVERYONE (Super Admin included,
 *   since Spatie's `can()` returns false for unknown permissions) out
 *   of the subscription-admin page. This migration registers the slug
 *   so the gates resolve to true for Super Admin and for the Finance
 *   role (which already lists subscriptions.management in its grant
 *   set — the role-seeder skips unknown slugs silently, so it'll pick
 *   the new row up the next time the seeder runs).
 *
 * Idempotent; safe to re-run.
 */
return new class extends Migration
{
    private string $perm = 'subscriptions.management';

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => $this->perm, 'guard_name' => 'admin'],
            [
                'group_name' => 'subscription management',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        // Grant to Super Admin (id=1) — matches the convention every
        // other permission-seed migration in this branch uses.
        $superRoleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where(fn ($q) => $q->where('name', 'Super Admin')->orWhere('id', 1))
            ->value('id');

        // Backfill the "Finance" role too — the 2026_05_18_120000
        // role-seed migration already lists this slug in the Finance
        // grant set, but that migration skips unknown slugs silently
        // and at the time it ran, the slug didn't exist yet.
        $financeRoleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where('name', 'Finance')
            ->value('id');

        $permId = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->where('name', $this->perm)
            ->value('id');

        if (!$permId) {
            return; // shouldn't happen — defensive
        }

        foreach (array_filter([$superRoleId, $financeRoleId]) as $roleId) {
            $already = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permId)
                ->exists();
            if (!$already) {
                DB::table('role_has_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $permId,
                ]);
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->where('name', $this->perm)
            ->where('guard_name', 'admin')
            ->delete();

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
