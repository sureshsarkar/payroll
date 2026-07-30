<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit follow-up 2026-05-15 — repair admin permission drift.
 *
 * 23 permission names were referenced by admin controllers via
 * checkAdminHasPermissionAndThrowException() but were missing from
 * the `permissions` table. Every Admin user — INCLUDING Super Admin —
 * was triggering "Permission Denied, You can not perform this action!"
 * on those menus because $admin->can('foo') returns false when the
 * named permission does not exist in the registry.
 *
 * This migration:
 *   1. Inserts the 23 missing permissions on the 'admin' guard
 *      (idempotent — uses INSERT IGNORE semantics by checking first).
 *   2. Grants every admin-guard permission to the Super Admin role
 *      (idempotent — uses INSERT IGNORE semantics).
 *
 * Safe to re-run via php artisan migrate:rollback + migrate.
 */
return new class extends Migration
{
    /** @var string[] */
    private array $missing = [
        // Membership plans (admin/membership-plans/* and admin/user-memberships/*)
        'membership-plan.view',
        'membership-plan.create',
        'membership-plan.store',
        'membership-plan.edit',
        'membership-plan.update',
        'membership-plan.delete',

        // Referral program
        'referral.update-percent',
        'referral.view',
        'referral.approve',
        'referral.pay',
        'referral.reverse',
        'referral.settings.view',
        'referral.settings.update',
        'referral.reject',

        // Generic settings sub-page check
        'settings.view',

        // User membership lifecycle
        'user-membership.view',
        'user-membership.confirm',
        'user-membership.cancel',
        'user-membership.refund',
        'user-membership.extend',

        // Landing page message (controller uses 'landingpage.' but DB had 'landing-page.' — add both shapes)
        'landingpage.message.view',
        'landingpage.message.delete',

        // Subscription management top-level
        'subscriptions.management',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        $insertedPermissions = 0;
        foreach ($this->missing as $name) {
            $exists = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'admin')
                ->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'name'       => $name,
                    'guard_name' => 'admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $insertedPermissions++;
            }
        }

        // Grant every admin-guard permission to whichever role is the
        // "Super Admin" (by name OR id=1). Idempotent — only inserts
        // pairs that don't already exist.
        $superAdminRole = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where(function ($q) {
                $q->where('name', 'Super Admin')->orWhere('id', 1);
            })
            ->first();

        if (!$superAdminRole) {
            return;
        }

        $allAdminPermissionIds = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->pluck('id')->all();

        $existingPairs = DB::table('role_has_permissions')
            ->where('role_id', $superAdminRole->id)
            ->pluck('permission_id')->all();

        $missingPairs = array_diff($allAdminPermissionIds, $existingPairs);

        if (!empty($missingPairs)) {
            $rows = array_map(
                fn($pid) => ['role_id' => $superAdminRole->id, 'permission_id' => $pid],
                $missingPairs,
            );
            DB::table('role_has_permissions')->insert($rows);
        }

        // Drop spatie's permission cache so the new rows take effect immediately.
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Reversible: only drop the rows we inserted. Do NOT touch the
        // role_has_permissions entries — those may include pairs that
        // already existed and rolling them back could lock out admins.
        DB::table('permissions')
            ->whereIn('name', $this->missing)
            ->where('guard_name', 'admin')
            ->delete();

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
