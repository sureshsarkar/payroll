<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-19 phase 4 (post-SRS) — coach-side "Add Student"
 * gained a Batch dropdown. When a coach assigns a freshly-created
 * student to a batch, we want an Enrollment row to materialise — but
 * there's no parent Order for a manual coach add, so `order_id`
 * must be nullable.
 *
 * No FK changes required (FK already cascades). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollments')) return;

        $col = collect(DB::select(
            'SHOW COLUMNS FROM enrollments WHERE Field = ?', ['order_id']
        ))->first();

        if (!$col) return;
        if ($col->Null === 'YES') return;  // already nullable

        DB::statement('ALTER TABLE `enrollments` MODIFY COLUMN `order_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('enrollments')) return;

        // Refuse to make non-nullable again if any row has NULL —
        // that would corrupt data. Down() exists for hygiene only.
        $nullCount = (int) DB::table('enrollments')->whereNull('order_id')->count();
        if ($nullCount > 0) return;

        DB::statement('ALTER TABLE `enrollments` MODIFY COLUMN `order_id` BIGINT UNSIGNED NOT NULL');
    }
};
