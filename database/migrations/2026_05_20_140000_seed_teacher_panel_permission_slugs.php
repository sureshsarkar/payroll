<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Teacher Panel — Phase 8.
 *
 * Adds four permission slugs the teacher-panel spec calls out but
 * which weren't in the seeded catalog. Coaches can grant/revoke
 * these per-role through the IAM matrix (already shipped).
 *
 *   dashboard         (singleton, "access" semantics) — gates the
 *                     coach/teacher home page
 *   analytics         (singleton)                     — gates the analytics page
 *   instant-meeting   (singleton)                     — gates the instant
 *                                                       Zoom-start endpoint
 *                                                       distinct from the
 *                                                       broader 'live-classes'
 *                                                       create perm
 *   attendance-edit   (singleton)                     — gates the manual
 *                                                       attendance-mark override
 *
 * Idempotent: insertOrIgnore on the unique (slug) — won't re-insert
 * on re-run. No down() because once a permission is wired into
 * any role, dropping it would silently revoke access.
 */
return new class extends Migration
{
    public function up(): void
    {
        $slugs = [
            ['name' => 'Dashboard',         'slug' => 'dashboard'],
            ['name' => 'Analytics',         'slug' => 'analytics'],
            ['name' => 'Instant Meeting',   'slug' => 'instant-meeting'],
            ['name' => 'Attendance Edit',   'slug' => 'attendance-edit'],
        ];

        $now = now();
        foreach ($slugs as $row) {
            $exists = DB::table('coach_staff_permissions')
                ->where('slug', $row['slug'])
                ->exists();
            if ($exists) continue;

            DB::table('coach_staff_permissions')->insert([
                'name'       => $row['name'],
                'slug'       => $row['slug'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally a no-op. Permissions wired into any role
        // can't be safely dropped without first cleaning up the
        // pivot table; a destructive rollback could silently revoke
        // production access.
    }
};
