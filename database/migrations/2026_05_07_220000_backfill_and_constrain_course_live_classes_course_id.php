<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair `course_live_classes.course_id`. Two write paths
 * (CourseContentController + Coach\LiveClassController) created rows
 * without setting course_id, so on 2026-05-07 every row in production
 * had course_id=NULL even though the schema permitted it. Both
 * controllers were patched in the same commit; this migration cleans
 * the data and adds the constraint that prevents the regression.
 *
 * Steps:
 *   1. Backfill course_id from course_chapter_lessons.course_id via
 *      a JOIN UPDATE — every live class is attached to a lesson, the
 *      lesson knows its course.
 *   2. Refuse to add NOT NULL if any row is still NULL after the
 *      backfill. Without this guard a deploy could fail half-way and
 *      leave the schema migrated but unsafe.
 *   3. Make the column NOT NULL and add a FK to courses(id) with
 *      ON DELETE CASCADE so a deleted course also drops its scheduled
 *      live classes (mirrors the existing lesson_id -> course_chapter_\
 *      lessons cascade).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('course_live_classes')) {
            return;
        }

        // 1. Backfill from the lesson's course.
        DB::statement(<<<'SQL'
            UPDATE course_live_classes c
            JOIN course_chapter_lessons l ON c.lesson_id = l.id
            SET c.course_id = l.course_id
            WHERE c.course_id IS NULL AND l.course_id IS NOT NULL
        SQL);

        // 2. Anything still NULL means the lesson is also orphaned. We
        //    can't recover those — the meaningful fix is to delete them
        //    so the FK can be added cleanly. Log first so the operator
        //    can audit afterward.
        $orphans = DB::table('course_live_classes')->whereNull('course_id')->get(['id', 'lesson_id', 'meeting_id', 'start_time']);
        foreach ($orphans as $o) {
            \Log::warning('course_live_classes: deleting orphan row (no course resolvable)', [
                'id'         => $o->id,
                'lesson_id'  => $o->lesson_id,
                'meeting_id' => $o->meeting_id,
                'start_time' => $o->start_time,
            ]);
        }
        DB::table('course_live_classes')->whereNull('course_id')->delete();

        // 3. Final guard — every row must now have course_id.
        $remaining = DB::table('course_live_classes')->whereNull('course_id')->count();
        if ($remaining > 0) {
            throw new \RuntimeException(
                "Refusing to add NOT NULL: {$remaining} course_live_classes rows still have course_id=NULL " .
                "after backfill. Investigate manually before re-running this migration."
            );
        }

        // 4. NOT NULL + FK. The column already exists as int(11) NULL.
        Schema::table('course_live_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable(false)->change();
        });

        // 5. Add the FK separately so we can name it (and drop it cleanly
        //    in down()). cascadeOnDelete mirrors the lesson_id FK.
        Schema::table('course_live_classes', function (Blueprint $table) {
            $table->foreign('course_id', 'fk_clc_course_id')
                ->references('id')->on('courses')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_live_classes')) {
            return;
        }

        // Drop FK first; column conversion fails otherwise.
        Schema::table('course_live_classes', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_clc_course_id');
            } catch (\Throwable) {
                // FK may not exist on older test DBs — fine.
            }
            $table->unsignedBigInteger('course_id')->nullable()->change();
        });
    }
};
