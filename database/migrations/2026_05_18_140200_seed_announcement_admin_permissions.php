<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 — add admin permissions for the new
 * batch-announcement management screens.
 *
 * Permissions added (admin guard):
 *   - announcement.view           — list all announcements
 *   - announcement.toggle-status  — activate/deactivate
 *   - announcement.delete         — delete an announcement
 *
 * Granted to Super Admin (role id=1). Idempotent.
 */
return new class extends Migration
{
    private array $perms = [
        'announcement.view',
        'announcement.toggle-status',
        'announcement.delete',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        foreach ($this->perms as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        // Grant to Super Admin
        $superRoleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where(fn($q) => $q->where('name', 'Super Admin')->orWhere('id', 1))
            ->value('id');

        if ($superRoleId) {
            $permIds = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', $this->perms)
                ->pluck('id')->all();
            $existing = DB::table('role_has_permissions')
                ->where('role_id', $superRoleId)->pluck('permission_id')->all();
            $missing = array_diff($permIds, $existing);
            if (!empty($missing)) {
                DB::table('role_has_permissions')->insert(array_map(
                    fn($pid) => ['role_id' => $superRoleId, 'permission_id' => $pid],
                    $missing
                ));
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', $this->perms)
            ->where('guard_name', 'admin')
            ->delete();
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
