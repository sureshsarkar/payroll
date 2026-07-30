<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-06 — Allow a student to be enrolled in MULTIPLE batches of the SAME
 * course. The old UNIQUE(user_id, course_id) capped a student to one batch per
 * course, so a second order/assignment for a different batch of the same course
 * failed with "Duplicate entry … enrollments_user_course_unique".
 *
 * Replace it with UNIQUE(user_id, course_id, batch_id): one enrollment row per
 * (student, course, batch). (MySQL treats NULL batch_id rows as distinct, so a
 * legacy course-wide enrollment doesn't block batched ones; the app keys
 * enrollment creation on (user, course, batch) to avoid stray null duplicates.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $t) {
            $t->dropUnique('enrollments_user_course_unique');
        });
        Schema::table('enrollments', function (Blueprint $t) {
            $t->unique(['user_id', 'course_id', 'batch_id'], 'enrollments_user_course_batch_unique');
        });
    }

    public function down(): void
    {
        // Restoring the stricter (user, course) unique requires de-duping first
        // (keep the lowest-id enrollment per user+course).
        DB::statement('
            DELETE e1 FROM enrollments e1
            INNER JOIN enrollments e2
              ON e1.user_id   = e2.user_id
             AND e1.course_id = e2.course_id
             AND e1.id        > e2.id
        ');

        Schema::table('enrollments', function (Blueprint $t) {
            $t->dropUnique('enrollments_user_course_batch_unique');
        });
        Schema::table('enrollments', function (Blueprint $t) {
            $t->unique(['user_id', 'course_id'], 'enrollments_user_course_unique');
        });
    }
};
