<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 — add a delegated "Announcement Manager" admin role.
 *
 * Why: announcement oversight (view all, toggle active/inactive, delete)
 * was previously gated to Super Admin only. Real ops teams want to
 * delegate this to a junior admin without granting Super-Admin access
 * to everything else.
 *
 * Also extends the existing "Course Manager" role (id=3) to include
 * announcement.view + announcement.toggle-status so course managers
 * can supervise their own area without the delete privilege.
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        // 1. Create the role if it doesn't exist
        $roleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where('name', 'Announcement Manager')
            ->value('id');

        if (!$roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name'        => 'Announcement Manager',
                'guard_name'  => 'admin',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // 2. Grant the role exactly these permissions
        $permNames = [
            'dashboard.view',
            'announcement.view',
            'announcement.toggle-status',
            'announcement.delete',
        ];

        $permIds = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', $permNames)
            ->pluck('id')
            ->all();

        $existing = DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->all();

        $toInsert = array_diff($permIds, $existing);
        if (!empty($toInsert)) {
            DB::table('role_has_permissions')->insert(array_map(
                fn ($pid) => ['role_id' => $roleId, 'permission_id' => $pid],
                $toInsert
            ));
        }

        // 3. Extend Course Manager (id=3) with announcement view + toggle (NOT delete)
        $courseManagerId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where('name', 'Course Manager')
            ->value('id');

        if ($courseManagerId) {
            $cmPermIds = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', ['announcement.view', 'announcement.toggle-status'])
                ->pluck('id')
                ->all();

            $cmExisting = DB::table('role_has_permissions')
                ->where('role_id', $courseManagerId)
                ->pluck('permission_id')
                ->all();

            $cmToInsert = array_diff($cmPermIds, $cmExisting);
            if (!empty($cmToInsert)) {
                DB::table('role_has_permissions')->insert(array_map(
                    fn ($pid) => ['role_id' => $courseManagerId, 'permission_id' => $pid],
                    $cmToInsert
                ));
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        // Remove the role + its permission grants (cascade via raw delete).
        $roleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where('name', 'Announcement Manager')
            ->value('id');

        if ($roleId) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        // Walk back the Course Manager extensions
        $courseManagerId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where('name', 'Course Manager')
            ->value('id');

        if ($courseManagerId) {
            $cmPermIds = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', ['announcement.view', 'announcement.toggle-status'])
                ->pluck('id')
                ->all();
            DB::table('role_has_permissions')
                ->where('role_id', $courseManagerId)
                ->whereIn('permission_id', $cmPermIds)
                ->delete();
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
