<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit follow-up 2026-05-18 — seed three role templates for the
 * permission-boundary test tier (TC2).
 *
 * Before: only Super Admin (all 143 perms) and Admin Role (2 perms) existed,
 * which made the audit's permission-check primitive practically unverifiable
 * — every admin user was effectively either god-mode or locked out.
 *
 * After: three additional roles each get a sensible curated slice. Tests in
 * tests/Feature/Domain/AdminRolePermissionTest.php verify each role allows
 * its scope and denies everything else.
 *
 * Idempotent — checks role existence before inserting.
 */
return new class extends Migration
{
    /** @var array<string, string[]> */
    private array $rolePermissions = [
        'Course Manager' => [
            'dashboard.view',
            'course.view', 'course.create',
            'blog.view', 'blog.create', 'blog.edit', 'blog.delete',
            'faq.view', 'faq.create', 'faq.edit', 'faq.delete',
            'testimonial.view', 'testimonial.create', 'testimonial.edit', 'testimonial.delete',
            'badge.management',
            'media.view',
        ],
        'Finance' => [
            'dashboard.view',
            'order.management',
            'withdraw.management',
            'referral.view', 'referral.approve', 'referral.pay', 'referral.reverse', 'referral.reject',
            'referral.settings.view', 'referral.settings.update',
            'user-membership.view', 'user-membership.confirm', 'user-membership.cancel',
            'user-membership.refund', 'user-membership.extend',
            'subscriptions.management',
        ],
        'Content Editor' => [
            'dashboard.view',
            'blog.view', 'blog.create', 'blog.edit', 'blog.delete',
            'faq.view', 'faq.create', 'faq.edit', 'faq.delete',
            'testimonial.view', 'testimonial.create', 'testimonial.edit', 'testimonial.delete',
            'page.management',
            'menu.view',
            'media.view',
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        foreach ($this->rolePermissions as $roleName => $permNames) {
            $existing = DB::table('roles')
                ->where('guard_name', 'admin')->where('name', $roleName)->first();

            if (!$existing) {
                $roleId = DB::table('roles')->insertGetId([
                    'name'       => $roleName,
                    'guard_name' => 'admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $roleId = $existing->id;
            }

            // Resolve permission ids; skip names that don't exist in DB (we
            // can't grant a permission that isn't registered).
            $permIds = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', $permNames)
                ->pluck('id')->all();

            // Grant only the ones that aren't already granted.
            $alreadyGranted = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->pluck('permission_id')->all();

            $toGrant = array_diff($permIds, $alreadyGranted);
            if (empty($toGrant)) {
                continue;
            }

            $rows = array_map(
                fn($pid) => ['role_id' => $roleId, 'permission_id' => $pid],
                $toGrant,
            );
            DB::table('role_has_permissions')->insert($rows);
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        $names = array_keys($this->rolePermissions);

        $roleIds = DB::table('roles')
            ->where('guard_name', 'admin')
            ->whereIn('name', $names)
            ->pluck('id')->all();

        if (!empty($roleIds)) {
            DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('roles')->whereIn('id', $roleIds)->delete();
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
