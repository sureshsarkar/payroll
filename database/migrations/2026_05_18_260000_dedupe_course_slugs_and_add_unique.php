<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 phase 6 — dedupe course slugs + lock with UNIQUE.
 *
 * Found 2 duplicate slugs in production-shape data:
 *   - cloud-computing-with-aws-from-beginner   (course ids 8 + 10)
 *   - game-development-with-unity-and-c        (course ids 9 + 11)
 *
 * Both pairs had identical title / instructor_id / created_at — clear
 * seed-script double-insert. Each duplicate carries its own 28 lessons
 * but ZERO enrolments and ZERO order_items, so dedupe is safe.
 *
 * Strategy:
 *   1. For each slug that has duplicates, append "-<id>" to the
 *      higher-id rows so the slug becomes unique. Lower id wins the
 *      original slug — URLs / bookmarks pointing at the canonical
 *      course don't break.
 *   2. After dedupe, add a UNIQUE index on courses.slug so the bug
 *      can't recur.
 *
 * Idempotent — re-running is a no-op (the SELECT finds no dups).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('courses')) return;

        DB::transaction(function () {
            $dups = DB::select(
                "SELECT slug, MIN(id) as keep_id, MAX(id) as rename_id, COUNT(*) c
                 FROM courses
                 WHERE slug IS NOT NULL AND slug != ''
                 GROUP BY slug
                 HAVING c > 1"
            );

            foreach ($dups as $row) {
                $newSlug = $row->slug . '-' . $row->rename_id;
                DB::table('courses')->where('id', $row->rename_id)->update([
                    'slug'       => $newSlug,
                    'updated_at' => now(),
                ]);
            }
        });

        // Add UNIQUE index unless one already exists.
        $hasUnique = collect(DB::select('SHOW INDEX FROM courses'))
            ->filter(fn ($r) => $r->Column_name === 'slug' && $r->Non_unique === 0)
            ->isNotEmpty();

        if (!$hasUnique) {
            Schema::table('courses', function (Blueprint $table) {
                $table->unique('slug', 'courses_slug_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('courses')) return;

        $hasUnique = collect(DB::select('SHOW INDEX FROM courses'))
            ->filter(fn ($r) => $r->Key_name === 'courses_slug_unique')
            ->isNotEmpty();

        if ($hasUnique) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropUnique('courses_slug_unique');
            });
        }
        // Slug renames are intentionally NOT reverted — we don't know
        // which were the originals on a fresh box.
    }
};
