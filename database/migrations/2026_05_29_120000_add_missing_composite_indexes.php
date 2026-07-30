<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P1-2 (2026-05-29) — Composite-index gap closure.
 *
 * After the FT-IDOR/VAL audit landed, EXPLAIN on the hottest controllers
 * (LearningController::getFileInfo, QnaController::fetchLessonQuestions,
 * QnaController::fetchReply, the "resume learning" widget on the student
 * dashboard) showed full-table scans where composite indexes would turn
 * them into single-row index lookups.
 *
 * Tables already correctly indexed (verified during audit):
 *   - enrollments(user_id, course_id)            unique composite
 *   - coupon_uses(coupon_id, user_id)            index
 *   - live_class_attendances                     two composite indexes
 *   - coach_student_links                        two composite indexes
 *
 * Tables addressed by THIS migration:
 *
 *   lesson_questions
 *     - (course_id, lesson_id)         hot: QnaController::fetchLessonQuestions
 *                                           InstructorLessonQnaController::index
 *     - (course_id, seen)              hot: instructor "unread questions" badge
 *
 *   lesson_replies
 *     - (question_id)                  hot: reply load on every Q&A page render;
 *                                           the table only had a primary key
 *
 *   course_progress
 *     - (user_id, course_id, type)     hot: LearningController quiz/lesson gates,
 *                                           CourseProgress::where(...).count()
 *                                           in the badge logic
 *     - (user_id, current)             hot: "resume learning" widget
 *                                           (single row per user)
 *
 * All indexes are non-unique (no semantic constraints added — pure speedup).
 *
 * Idempotent — checks for existing index name before creating. Safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        // ---------------- lesson_questions ----------------
        if (Schema::hasTable('lesson_questions')) {
            Schema::table('lesson_questions', function (Blueprint $t) {
                if (!$this->indexExists('lesson_questions', 'lq_course_lesson_idx')) {
                    $t->index(['course_id', 'lesson_id'], 'lq_course_lesson_idx');
                }
                if (!$this->indexExists('lesson_questions', 'lq_course_seen_idx')) {
                    $t->index(['course_id', 'seen'], 'lq_course_seen_idx');
                }
            });
        }

        // ---------------- lesson_replies ----------------
        if (Schema::hasTable('lesson_replies')) {
            Schema::table('lesson_replies', function (Blueprint $t) {
                if (!$this->indexExists('lesson_replies', 'lr_question_idx')) {
                    $t->index('question_id', 'lr_question_idx');
                }
            });
        }

        // ---------------- course_progress ----------------
        if (Schema::hasTable('course_progress')) {
            Schema::table('course_progress', function (Blueprint $t) {
                if (!$this->indexExists('course_progress', 'cp_user_course_type_idx')) {
                    $t->index(['user_id', 'course_id', 'type'], 'cp_user_course_type_idx');
                }
                if (!$this->indexExists('course_progress', 'cp_user_current_idx')) {
                    $t->index(['user_id', 'current'], 'cp_user_current_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lesson_questions')) {
            Schema::table('lesson_questions', function (Blueprint $t) {
                if ($this->indexExists('lesson_questions', 'lq_course_lesson_idx')) {
                    $t->dropIndex('lq_course_lesson_idx');
                }
                if ($this->indexExists('lesson_questions', 'lq_course_seen_idx')) {
                    $t->dropIndex('lq_course_seen_idx');
                }
            });
        }

        if (Schema::hasTable('lesson_replies')) {
            Schema::table('lesson_replies', function (Blueprint $t) {
                if ($this->indexExists('lesson_replies', 'lr_question_idx')) {
                    $t->dropIndex('lr_question_idx');
                }
            });
        }

        if (Schema::hasTable('course_progress')) {
            Schema::table('course_progress', function (Blueprint $t) {
                if ($this->indexExists('course_progress', 'cp_user_course_type_idx')) {
                    $t->dropIndex('cp_user_course_type_idx');
                }
                if ($this->indexExists('course_progress', 'cp_user_current_idx')) {
                    $t->dropIndex('cp_user_current_idx');
                }
            });
        }
    }

    /**
     * MySQL/MariaDB-portable check for whether a named index exists on a
     * table. Schema::hasIndex() doesn't exist in Laravel 10 across all
     * drivers, so we ask INFORMATION_SCHEMA directly.
     */
    private function indexExists(string $table, string $index): bool
    {
        $database = DB::connection()->getDatabaseName();
        $rows = DB::select(
            'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$database, $table, $index]
        );
        return ((int) ($rows[0]->c ?? 0)) > 0;
    }
};
