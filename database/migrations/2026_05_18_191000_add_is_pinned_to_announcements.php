<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 3 — pinned ("urgent") announcement flag.
 *
 * Adds:
 *   is_pinned  TINYINT(1) default 0
 *
 * Visibility scope (Announcement::visibleToBatchStudent) already sorts
 * by sent_at DESC. With this column, callers can ORDER BY
 * is_pinned DESC, sent_at DESC — pinned ones rise to the top.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }
        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('status');
                $table->index('is_pinned', 'announcements_pinned_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'is_pinned')) {
                $table->dropIndex('announcements_pinned_idx');
                $table->dropColumn('is_pinned');
            }
        });
    }
};
