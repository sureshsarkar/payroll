<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-09 — admin permissions for the custom-domain manager.
 *
 * Adds the three permission names the Admin\CustomDomainController guards
 * with, on the 'admin' guard, and grants them to the Super Admin role.
 * Without these, $admin->can('custom_domain.view') returns false and the
 * whole menu 403s — same pattern as the 2026-05-15 permission repair.
 *
 * Idempotent.
 */
return new class extends Migration
{
    /** @var string[] */
    private array $permissions = [
        'custom_domain.view',      // see the list + detail
        'custom_domain.manage',    // approve/reject/suspend/resume/remove/recheck
        'custom_domain.settings',  // toggle feature, set server IP, approval mode, quota
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        foreach ($this->permissions as $name) {
            $exists = DB::table('permissions')
                ->where('name', $name)->where('guard_name', 'admin')->exists();
            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name, 'guard_name' => 'admin',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $superAdminRole = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where(function ($q) {
                $q->where('name', 'Super Admin')->orWhere('id', 1);
            })
            ->first();

        if ($superAdminRole) {
            $ids = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', $this->permissions)
                ->pluck('id')->all();

            $existing = DB::table('role_has_permissions')
                ->where('role_id', $superAdminRole->id)
                ->pluck('permission_id')->all();

            $missing = array_diff($ids, $existing);
            if (! empty($missing)) {
                DB::table('role_has_permissions')->insert(array_map(
                    fn ($pid) => ['role_id' => $superAdminRole->id, 'permission_id' => $pid],
                    $missing,
                ));
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('name', $this->permissions)
                ->where('guard_name', 'admin')
                ->delete();
        }
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
