<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FT-IDOR-17 fix (2026-05-28) — separate write-level permission for
 * InstructorRequest updates so the read-only and write-level checks
 * are not collapsed onto the same slug.
 *
 * Before: InstructorRequestController::{update, destroy} both gated on
 *   `instructor.request.list`
 * — a read-level permission used by index() and edit(). A sub-admin
 * granted ONLY the list permission could promote arbitrary users to
 * `role = instructor` (a global-tenant privilege escalation) by
 * POSTing status=approved against any instructor-request id.
 *
 * Same bug-class as FT-IDOR-2 (Announcement controller). The fix is
 * symmetrical: introduce a `.update` slug for write operations, grant
 * it to Super Admin, and switch the controller calls.
 *
 * Idempotent; safe to re-run.
 */
return new class extends Migration
{
    private string $perm = 'instructor.request.update';

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => $this->perm, 'guard_name' => 'admin'],
            [
                'group_name' => 'instructor request',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $superRoleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where(fn ($q) => $q->where('name', 'Super Admin')->orWhere('id', 1))
            ->value('id');

        $permId = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->where('name', $this->perm)
            ->value('id');

        if ($superRoleId && $permId) {
            $already = DB::table('role_has_permissions')
                ->where('role_id', $superRoleId)
                ->where('permission_id', $permId)
                ->exists();
            if (!$already) {
                DB::table('role_has_permissions')->insert([
                    'role_id'       => $superRoleId,
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
