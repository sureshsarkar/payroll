<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 — add the temporary-slot permission to the GLOBAL coach permission
 * catalog so a coach can delegate temporary batch-slot management to staff.
 * Idempotent + additive (deny by default; real coach short-circuits;
 * coach-students-edit also satisfies it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_staff_permissions')) {
            return;
        }

        $catalog = [
            'coach-students-temp-slot' => 'Manage Temporary Slots',
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
    }
};
