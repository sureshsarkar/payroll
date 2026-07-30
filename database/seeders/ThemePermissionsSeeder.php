<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the 4 permission rows required by the Theme Studio
 * (Phase 2 — Super Admin theme catalog).
 *
 * Granted by default to:
 *   - Super Admin (role id = 1)  — full theme control
 *   - Admin Role  (role id = 2)  — platform admins can manage themes
 *
 * Idempotent — uses INSERT IGNORE on the unique (name, guard_name) constraint.
 */
class ThemePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = ['theme.view', 'theme.create', 'theme.update', 'theme.delete'];
        $now = now();

        foreach ($permissions as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name'       => $name,
                'guard_name' => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Grant to roles that should have theme management
        $rolesToGrant = [1, 2];   // Super Admin + Admin Role
        $ids = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->where('guard_name', 'admin')
            ->pluck('id');

        foreach ($rolesToGrant as $roleId) {
            $exists = DB::table('roles')->where('id', $roleId)->exists();
            if (! $exists) continue;
            foreach ($ids as $permId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId,
                    'role_id'       => $roleId,
                ]);
            }
        }

        $this->command->info('Theme permissions seeded — ' . $ids->count() . ' permission(s) granted to Super Admin + Admin Role.');
    }
}
