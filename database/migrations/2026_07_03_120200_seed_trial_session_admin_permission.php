<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register the Super-Admin permission for the cross-coach Trial Sessions page
 * (2026-07-03). Without this, even Super Admin would get "Permission Denied"
 * because $admin->can('trial-session.view') is false for an unknown permission.
 * Idempotent; mirrors 2026_05_15_175951_seed_missing_admin_permissions.
 */
return new class extends Migration
{
    private array $permissions = ['trial-session.view'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        foreach ($this->permissions as $name) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'admin')->exists();
            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name, 'guard_name' => 'admin',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $superAdminRole = DB::table('roles')->where('guard_name', 'admin')
            ->where(function ($q) { $q->where('name', 'Super Admin')->orWhere('id', 1); })
            ->first();
        if (! $superAdminRole) {
            return;
        }

        $ids = DB::table('permissions')->where('guard_name', 'admin')
            ->whereIn('name', $this->permissions)->pluck('id')->all();
        $existing = DB::table('role_has_permissions')
            ->where('role_id', $superAdminRole->id)->pluck('permission_id')->all();
        $missing = array_diff($ids, $existing);
        if (! empty($missing)) {
            DB::table('role_has_permissions')->insert(array_map(
                fn ($pid) => ['role_id' => $superAdminRole->id, 'permission_id' => $pid],
                $missing
            ));
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', $this->permissions)->where('guard_name', 'admin')->delete();
        }
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
