<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 — pivot table for multi-batch announcements.
 *
 * Why: the 14:01 migration added announcements.batch_id (single batch).
 * A coach often wants to announce to multiple batches at once
 * (e.g. "All weekend batches this Saturday class is moved"). Rather
 * than asking the coach to repeat the announcement N times, the pivot
 * supports an array of target batches per announcement.
 *
 * Compatibility:
 *   - announcements.batch_id is KEPT (denormalized "first/primary batch").
 *     Existing code reading announcements.batch_id still works.
 *   - For multi-batch announcements: announcements.batch_id is set to
 *     the FIRST selected batch, and ALL batches (including the first)
 *     are inserted into announcement_batches.
 *   - For single-batch announcements: behaviour is unchanged — batch_id
 *     is set AND one row is inserted into announcement_batches.
 *   - For course-wide (batch_id IS NULL): NO pivot rows exist.
 *
 * Visibility resolution becomes:
 *   active AND (batch_id IS NULL OR student.batch ∈ pivot)
 *
 * Backfill: for every existing announcement where batch_id IS NOT NULL,
 * insert one pivot row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcement_batches')) {
            Schema::create('announcement_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('announcement_id');
                $table->unsignedBigInteger('batch_id');
                $table->timestamps();

                $table->unique(['announcement_id', 'batch_id'], 'ann_batch_unique');
                $table->index('batch_id', 'ann_batch_idx');

                $table->foreign('announcement_id')
                    ->references('id')->on('announcements')
                    ->onDelete('cascade');
                $table->foreign('batch_id')
                    ->references('id')->on('course_batches')
                    ->onDelete('cascade');
            });
        }

        // Backfill from announcements.batch_id (single-batch rows).
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'batch_id')) {
            DB::statement('
                INSERT IGNORE INTO announcement_batches (announcement_id, batch_id, created_at, updated_at)
                SELECT id, batch_id, NOW(), NOW()
                FROM announcements
                WHERE batch_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_batches');
    }
};
