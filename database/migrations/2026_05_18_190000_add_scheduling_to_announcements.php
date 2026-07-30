<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 3 — split sent_at into two semantic fields:
 *
 *   scheduled_at  → when the coach asked for this to be delivered
 *                   (sent_at gets renamed-by-meaning to this; coach
 *                   can set it in the future for "deliver later").
 *   delivered_at  → when the fan-out service actually ran the
 *                   Notification::send call. NULL = still queued.
 *
 * Existing `sent_at` column is KEPT (legacy reads still work) and is
 * kept in lockstep with `scheduled_at`. New code reads delivered_at to
 * answer "was this delivered yet?" and scheduled_at to answer "when is
 * it scheduled for?".
 *
 * Backfill rule for existing rows:
 *   scheduled_at = sent_at  (announcement created at this time)
 *   delivered_at = sent_at  (legacy rows were delivered synchronously)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('sent_at');
                $table->index('scheduled_at', 'announcements_scheduled_idx');
            }
            if (!Schema::hasColumn('announcements', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('scheduled_at');
                $table->index('delivered_at', 'announcements_delivered_idx');
            }
        });

        // Backfill: legacy rows are treated as scheduled-at = delivered-at = sent_at.
        DB::statement('
            UPDATE announcements
            SET scheduled_at = COALESCE(scheduled_at, sent_at, created_at),
                delivered_at = COALESCE(delivered_at, sent_at, created_at)
            WHERE scheduled_at IS NULL OR delivered_at IS NULL
        ');
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'delivered_at')) {
                $table->dropIndex('announcements_delivered_idx');
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('announcements', 'scheduled_at')) {
                $table->dropIndex('announcements_scheduled_idx');
                $table->dropColumn('scheduled_at');
            }
        });
    }
};
