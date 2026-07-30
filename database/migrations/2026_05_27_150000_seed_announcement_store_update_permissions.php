<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FT-IDOR-2 fix (2026-05-27) — admin permissions for announcement
 * write operations.
 *
 * The original 2026-05-18 seed introduced THREE announcement permissions:
 *   - announcement.view
 *   - announcement.toggle-status
 *   - announcement.delete
 *
 * but the AnnouncementController's `store()`, `create()`, `update()`,
 * and `edit()` methods were gated using `announcement.view` — a READ
 * permission. A read-only sub-admin (e.g. the "Announcement Manager"
 * role which has view + toggle-status only) could create / edit /
 * publish announcements that fan out as push notifications + emails
 * to every student on the platform when audience_type=all_students.
 *
 * This migration adds:
 *   - announcement.store    — create / publish new announcements
 *   - announcement.update   — edit existing announcements
 *
 * Both granted to Super Admin (id=1). The Announcement Manager role
 * receives `.store` and `.update` so they continue to function — the
 * read-only sub-admin loses write access (correct outcome).
 *
 * Idempotent; safe to re-run.
 */
return new class extends Migration
{
    private array $perms = [
        'announcement.store',
        'announcement.update',
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

        // Grant to Super Admin (id=1) — same pattern as the original seed.
        $superRoleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where(fn($q) => $q->where('name', 'Super Admin')->orWhere('id', 1))
            ->value('id');

        $announcementMgrRoleId = DB::table('roles')
            ->where('guard_name', 'admin')
            ->where('name', 'Announcement Manager')
            ->value('id');

        $permIds = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', $this->perms)
            ->pluck('id')->all();

        foreach (array_filter([$superRoleId, $announcementMgrRoleId]) as $roleId) {
            $existing = DB::table('role_has_permissions')
                ->where('role_id', $roleId)->pluck('permission_id')->all();
            $missing = array_diff($permIds, $existing);
            if (!empty($missing)) {
                DB::table('role_has_permissions')->insert(array_map(
                    fn($pid) => ['role_id' => $roleId, 'permission_id' => $pid],
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
