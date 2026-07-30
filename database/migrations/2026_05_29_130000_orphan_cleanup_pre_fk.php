<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-1 (2026-05-29) — Orphan-row cleanup pre-flight for FK consolidation.
 *
 * This migration MUST run before 2026_05_29_140000_add_missing_foreign_keys.
 * It cleans the 207 orphan rows discovered during the audit on dev DB so
 * that the FK-addition migration that follows can install constraints
 * without 1452 (foreign key constraint fails) errors.
 *
 * Discovered on dev DB (mbs) 2026-05-29 via storage/app/fk-orphan-scan.php:
 *
 *   user_experiences.user_id              1 orphan   — DELETE
 *   course_selected_languages.language_id 130 orphans — DELETE (pivot junk)
 *   quiz_results.user_id                  2 orphans   — DELETE
 *   lesson_replies.user_id                1 orphan    — DELETE (col is NOT NULL,
 *                                                       can't SET NULL)
 *   course_progress.chapter_id            36 orphans  — SET NULL (col nullable)
 *   course_progress.lesson_id             35 orphans  — SET NULL (col nullable)
 *   announcements.instructor_id           1 orphan    — DELETE
 *   course_reviews.user_id                1 orphan    — DELETE
 *
 * Operator note for production rollout:
 *   - The actual orphan counts will differ on prod. Re-run
 *     `php storage/app/fk-orphan-scan.php` against the prod DB in a
 *     maintenance window to enumerate the real numbers BEFORE running
 *     this migration.
 *   - If prod orphan counts are dramatically higher, investigate root
 *     cause (which delete path is leaving orphans) before cleaning, so
 *     the same path can be fixed in code.
 *   - This migration is irreversible — there is no down() that restores
 *     orphan rows. That's intentional: they reference parents that no
 *     longer exist, so restoring them serves no purpose.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $log = [];

            // user_experiences: 1 orphan on dev.
            if (Schema::hasTable('user_experiences')) {
                $n = DB::affectingStatement(
                    'DELETE FROM user_experiences WHERE user_id NOT IN (SELECT id FROM users)'
                );
                $log[] = "user_experiences: deleted $n orphan rows";
            }

            // course_selected_languages: 130 orphans on dev.
            // No `languages` table exists yet on dev — these rows reference
            // an ID that never resolved. Pure pivot junk.
            if (Schema::hasTable('course_selected_languages') && Schema::hasTable('languages')) {
                $n = DB::affectingStatement(
                    'DELETE FROM course_selected_languages WHERE language_id NOT IN (SELECT id FROM languages)'
                );
                $log[] = "course_selected_languages: deleted $n orphan rows";
            } elseif (Schema::hasTable('course_selected_languages') && !Schema::hasTable('languages')) {
                // No parent — clear the whole table; nothing it could resolve to.
                $n = DB::affectingStatement('DELETE FROM course_selected_languages');
                $log[] = "course_selected_languages: deleted $n rows (parent `languages` does not exist)";
            }

            // quiz_results: 2 orphans on dev.
            if (Schema::hasTable('quiz_results')) {
                $n = DB::affectingStatement(
                    'DELETE FROM quiz_results WHERE user_id NOT IN (SELECT id FROM users)'
                );
                $log[] = "quiz_results: deleted $n orphan rows";
            }

            // lesson_replies: 1 orphan on dev. Column is NOT NULL → DELETE.
            // If the operator wants to anonymize instead, they should make
            // the column nullable FIRST, then re-run with SET NULL.
            if (Schema::hasTable('lesson_replies')) {
                $n = DB::affectingStatement(
                    'DELETE FROM lesson_replies WHERE user_id NOT IN (SELECT id FROM users)'
                );
                $log[] = "lesson_replies: deleted $n orphan rows";
            }

            // course_progress: chapter_id (36) and lesson_id (35) orphans —
            // both nullable, so SET NULL preserves the user's overall
            // progress on the course while clearing stale child references.
            if (Schema::hasTable('course_progress')) {
                $n1 = DB::affectingStatement(
                    'UPDATE course_progress SET chapter_id = NULL
                       WHERE chapter_id IS NOT NULL
                         AND chapter_id NOT IN (SELECT id FROM course_chapters)'
                );
                $n2 = DB::affectingStatement(
                    'UPDATE course_progress SET lesson_id = NULL
                       WHERE lesson_id IS NOT NULL
                         AND lesson_id NOT IN (SELECT id FROM course_chapter_lessons)'
                );
                $log[] = "course_progress: nulled $n1 stale chapter_id, $n2 stale lesson_id";
            }

            // announcements: 1 orphan instructor_id on dev.
            if (Schema::hasTable('announcements')) {
                $n = DB::affectingStatement(
                    'DELETE FROM announcements WHERE instructor_id NOT IN (SELECT id FROM users)'
                );
                $log[] = "announcements: deleted $n orphan rows";
            }

            // course_reviews: 1 orphan user_id on dev.
            if (Schema::hasTable('course_reviews')) {
                $n = DB::affectingStatement(
                    'DELETE FROM course_reviews WHERE user_id NOT IN (SELECT id FROM users)'
                );
                $log[] = "course_reviews: deleted $n orphan rows";
            }

            foreach ($log as $line) {
                echo "  [orphan-cleanup] $line" . PHP_EOL;
            }
        });
    }

    public function down(): void
    {
        // Intentionally no-op. Deleted orphan rows referenced parents that
        // no longer exist; there's nothing to restore them to. Rolling
        // back this migration after the FK migration succeeds is also
        // unsafe (would require dropping FKs first). Operators who need
        // to "undo" this should restore from backup.
    }
};
