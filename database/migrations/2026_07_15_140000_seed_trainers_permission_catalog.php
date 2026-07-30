<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 (Trainer feature, Phase 3) — add the `trainers` module to the GLOBAL
 * coach permission catalog so a coach can delegate trainer management + bookings
 * to staff roles. Convention (unchanged): bare slug = "access the module";
 * -create/-edit/-delete group into the resource×action picker matrix.
 *
 * The bare `trainers` slug also gates the new "Trainer Bookings" list (view),
 * with -edit driving the booking status update and -delete the booking removal —
 * so one module permission covers both trainer profiles and their bookings.
 *
 * Idempotent + additive: inserts ONLY slugs that don't already exist and assigns
 * NOTHING to any role (deny by default). A real coach/instructor short-circuits
 * checkPermission(), so this only affects delegated staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_staff_permissions')) {
            return;
        }

        $catalog = [
            'trainers'        => 'Trainers',
            'trainers-create' => 'Create Trainer',
            'trainers-edit'   => 'Edit Trainer / Update Bookings',
            'trainers-delete' => 'Delete Trainer / Bookings',
        ];

        $now = now();
        $existing = DB::table('coach_staff_permissions')->pluck('slug')->flip();
        $rows = [];
        foreach ($catalog as $slug => $name) {
            if ($existing->has($slug)) {
                continue;
            }
            $rows[] = ['name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now];
        }
        if ($rows) {
            DB::table('coach_staff_permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        // Append-only catalog — no-op (removing slugs could orphan live assignments).
    }
};
