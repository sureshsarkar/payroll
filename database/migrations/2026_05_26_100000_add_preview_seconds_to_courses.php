<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds preview_seconds to courses (audit 2026-05-26 — Phase A of the
 * recorded-courses theme section). Default 60s = 1-minute free preview.
 *
 * NOTE: `courses.type` enum already has 'recorded' value and
 * `demo_video_source` / `demo_video_storage` already handle preview
 * video URL + source type — no extra columns needed beyond duration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! $this->hasColumn('courses', 'preview_seconds')) {
                $table->unsignedSmallInteger('preview_seconds')->default(60)->after('demo_video_source')
                    ->comment('Preview video duration cap in seconds (0 = full demo, default 60 for 1-min preview)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if ($this->hasColumn('courses', 'preview_seconds')) {
                $table->dropColumn('preview_seconds');
            }
        });
    }

    private function hasColumn(string $table, string $column): bool
    {
        $db = DB::getDatabaseName();
        return (bool) DB::selectOne(
            'SELECT 1 AS x FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$db, $table, $column]
        );
    }
};
