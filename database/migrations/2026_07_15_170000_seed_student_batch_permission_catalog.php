<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 — add the student batch-assignment permissions to the GLOBAL coach
 * permission catalog so a coach can delegate batch management to staff roles
 * (spec §11: student.batch.assign / student.batch.reassign). Naming follows the
 * existing `coach-students-*` convention. Idempotent + additive: inserts only
 * missing slugs, assigns NOTHING to any role (deny by default). A real coach
 * short-circuits the check; the broader `coach-students-edit` also satisfies it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_staff_permissions')) {
            return;
        }

        $catalog = [
            'coach-students-batch-assign'   => 'Assign Student Batch',
            'coach-students-batch-reassign' => 'Reassign Student Batch',
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
        // Append-only catalog — no-op.
    }
};
