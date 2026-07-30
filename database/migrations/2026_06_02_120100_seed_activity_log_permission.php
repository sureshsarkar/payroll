<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise H-A — read-only permission for the activity/audit log viewer.
 * Granted to Super Admin only by default. Idempotent; safe to re-run.
 */
return new class extends Migration
{
    private string $perm = 'activity-log.view';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => $this->perm, 'guard_name' => 'admin'],
            ['group_name' => 'activity log', 'created_at' => $now, 'updated_at' => $now],
        );

        $superRoleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where(fn ($q) => $q->where('name', 'Super Admin')->orWhere('id', 1))
            ->value('id');

        $permId = DB::table('permissions')
            ->where('guard_name', 'admin')->where('name', $this->perm)->value('id');

        if ($superRoleId && $permId) {
            $exists = DB::table('role_has_permissions')
                ->where('role_id', $superRoleId)->where('permission_id', $permId)->exists();
            if (! $exists) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => $superRoleId, 'permission_id' => $permId,
                ]);
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }
        DB::table('permissions')->where('name', $this->perm)->where('guard_name', 'admin')->delete();
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
