<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-1 (2026-05-29) — FK consolidation migration.
 *
 * Audit finding: 43 `foreignId()` columns across 19 tables were created
 * without `->constrained()` / `->foreign()->references()`, so they exist
 * as plain bigint columns with an implicit index but no referential
 * integrity. That allows:
 *   - orphan rows to accumulate when parents are deleted
 *   - silently broken FK-style queries (no SQL error)
 *   - data-loss bugs in cascade-delete flows
 *
 * This migration adds proper FK constraints with explicit delete rules.
 * It REQUIRES the orphan-cleanup migration (2026_05_29_130000_*) to have
 * run first — otherwise InnoDB will reject the constraint with errno 1452.
 *
 * Skipped relations (parent table does not exist in current schema):
 *   course_selected_levels.level_id           → `levels` not present
 *   course_selected_filter_options.filter_id  → `course_filters` not present
 *   course_selected_filter_options.filter_option_id → `course_filter_options` not present
 *   order_items.product_id                    → `products` not present
 * If/when those parent tables are introduced, a follow-up migration
 * should add the FKs.
 *
 * Delete-rule rationale (per relation, see in-line comments below):
 *   - CASCADE  → row is meaningless without parent (owned data, pivots,
 *                per-user/per-course state)
 *   - SET NULL → child should survive parent deletion (nullable columns
 *                only; preserves user-facing artifacts like order history)
 *   - RESTRICT not used in this pass to minimize behavior change vs the
 *                current "delete anything, anywhere" pattern. Operators
 *                who want hard-block semantics on specific relations
 *                (e.g. courses with active enrollments) should add them
 *                in a follow-up migration once the gating UI is in place.
 *
 * Idempotent — checks for existing constraint by name before adding.
 * Safe to re-run.
 */
return new class extends Migration {
    /**
     * [table, column, parent, delete_rule, fk_name]
     *
     * fk_name is short (max 64 chars per MariaDB) and uniquely identifies
     * the constraint so the idempotency check works.
     */
    private const FKS = [
        // ---------------- users / profile ----------------
        ['users',              'country_id',   'countries',         'set null', 'fk_users_country_id'],
        ['user_education',     'user_id',      'users',             'cascade',  'fk_user_education_user_id'],
        ['user_experiences',   'user_id',      'users',             'cascade',  'fk_user_experiences_user_id'],
        ['user_skill_topics',  'category_id',  'course_categories', 'cascade',  'fk_user_skill_topics_category_id'],

        // ---------------- courses + taxonomy ----------------
        ['courses',                    'instructor_id', 'users',             'cascade',  'fk_courses_instructor_id'],
        ['courses',                    'category_id',   'course_categories', 'set null', 'fk_courses_category_id'],
        ['course_selected_levels',     'course_id',     'courses',           'cascade',  'fk_csl_course_id'],
        ['course_selected_languages',  'course_id',     'courses',           'cascade',  'fk_cslang_course_id'],
        ['course_selected_filter_options', 'course_id', 'courses',           'cascade',  'fk_csfo_course_id'],
        ['course_partner_instructors', 'course_id',     'courses',           'cascade',  'fk_cpi_course_id'],
        ['course_partner_instructors', 'instructor_id', 'users',             'cascade',  'fk_cpi_instructor_id'],
        ['course_categories',          'parent_id',     'course_categories', 'set null', 'fk_course_categories_parent_id'],
        ['course_delete_requests',     'course_id',     'courses',           'cascade',  'fk_cdr_course_id'],

        // ---------------- quizzes ----------------
        ['quizzes',              'instructor_id',  'users',                'cascade', 'fk_quizzes_instructor_id'],
        ['quizzes',              'chapter_id',     'course_chapters',      'cascade', 'fk_quizzes_chapter_id'],
        ['quizzes',              'chapter_item_id','course_chapter_items', 'cascade', 'fk_quizzes_chapter_item_id'],
        ['quizzes',              'course_id',      'courses',              'cascade', 'fk_quizzes_course_id'],
        ['quiz_question_answers','question_id',    'quiz_questions',       'cascade', 'fk_qqa_question_id'],
        ['quiz_results',         'user_id',        'users',                'cascade', 'fk_quiz_results_user_id'],
        ['quiz_results',         'quiz_id',        'quizzes',              'cascade', 'fk_quiz_results_quiz_id'],

        // ---------------- Q&A ----------------
        ['lesson_questions', 'user_id',   'users',                  'cascade', 'fk_lq_user_id'],
        ['lesson_questions', 'course_id', 'courses',                'cascade', 'fk_lq_course_id'],
        ['lesson_questions', 'lesson_id', 'course_chapter_lessons', 'cascade', 'fk_lq_lesson_id'],
        ['lesson_replies',   'user_id',   'users',                  'cascade', 'fk_lr_user_id'],

        // ---------------- progress / analytics ----------------
        ['course_progress', 'user_id',    'users',                  'cascade',  'fk_cp_user_id'],
        ['course_progress', 'course_id',  'courses',                'cascade',  'fk_cp_course_id'],
        ['course_progress', 'chapter_id', 'course_chapters',        'set null', 'fk_cp_chapter_id'],
        ['course_progress', 'lesson_id',  'course_chapter_lessons', 'set null', 'fk_cp_lesson_id'],

        // ---------------- comms ----------------
        ['announcements', 'course_id',     'courses', 'cascade', 'fk_ann_course_id'],
        ['announcements', 'instructor_id', 'users',   'cascade', 'fk_ann_instructor_id'],

        // ---------------- reviews ----------------
        ['course_reviews', 'course_id', 'courses', 'cascade', 'fk_cr_course_id'],
        ['course_reviews', 'user_id',   'users',   'cascade', 'fk_cr_user_id'],

        // ---------------- commerce ----------------
        // enrollments.order_id already has FK via the original migration.
        // user_id + course_id are the two gaps.
        ['enrollments', 'user_id',   'users',   'cascade', 'fk_enrollments_user_id'],
        ['enrollments', 'course_id', 'courses', 'cascade', 'fk_enrollments_course_id'],
        // order_items.order_id already constrained; course_id is the gap.
        // product_id deferred (no products table).
        ['order_items', 'course_id', 'courses', 'cascade',  'fk_oi_course_id'],
        // orders: primary_coach_id has FK; buyer_id + seller_id are the gaps.
        // Nullable so SET NULL preserves order history when a user is deleted.
        ['orders', 'buyer_id',  'users', 'set null', 'fk_orders_buyer_id'],
        ['orders', 'seller_id', 'users', 'set null', 'fk_orders_seller_id'],

        // ---------------- requests ----------------
        ['instructor_requests', 'user_id', 'users', 'cascade', 'fk_ir_user_id'],
    ];

    public function up(): void
    {
        // Pre-flight: verify zero orphans across all relations we're about
        // to constrain. If anything turns up, abort with a clear list so
        // the operator can run the cleanup migration first.
        $orphans = $this->detectOrphans();
        if (!empty($orphans)) {
            $msg = "FK consolidation aborted — orphan rows present:\n";
            foreach ($orphans as $o) {
                $msg .= "  - {$o['table']}.{$o['col']} → {$o['parent']}: {$o['count']} orphans\n";
            }
            $msg .= "Run the orphan-cleanup migration (2026_05_29_130000_orphan_cleanup_pre_fk)\n";
            $msg .= "or storage/app/fk-orphan-scan.php to investigate, then re-run.";
            throw new \RuntimeException($msg);
        }

        foreach (self::FKS as [$table, $col, $parent, $rule, $name]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasTable($parent)) {
                // Parent missing — leave a breadcrumb but don't fail.
                echo "  [skip] $table.$col → $parent: parent table not present\n";
                continue;
            }
            if (!Schema::hasColumn($table, $col)) {
                continue;
            }
            if ($this->constraintExists($table, $name)) {
                continue;
            }
            $ruleSql = strtoupper($rule);
            // Use raw ALTER TABLE so we can pin the constraint name and
            // ON DELETE rule exactly. Blueprint's $table->foreign() does
            // not let us set the constraint name in this Laravel version
            // when the column already exists.
            DB::statement(
                "ALTER TABLE `$table`
                   ADD CONSTRAINT `$name`
                   FOREIGN KEY (`$col`)
                   REFERENCES `$parent`(`id`)
                   ON DELETE $ruleSql
                   ON UPDATE NO ACTION"
            );
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::FKS) as [$table, $col, $parent, $rule, $name]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if ($this->constraintExists($table, $name)) {
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$name`");
            }
        }
    }

    /**
     * Walk every relation in self::FKS and count orphan rows. Returns a
     * list of [table, col, parent, count] for any non-zero count.
     */
    private function detectOrphans(): array
    {
        $out = [];
        foreach (self::FKS as [$table, $col, $parent, $rule, $name]) {
            if (!Schema::hasTable($table) || !Schema::hasTable($parent) || !Schema::hasColumn($table, $col)) {
                continue;
            }
            $isNullable = $this->isColumnNullable($table, $col);
            $where = $isNullable
                ? "$col IS NOT NULL AND $col NOT IN (SELECT id FROM `$parent`)"
                : "$col NOT IN (SELECT id FROM `$parent`)";
            $c = (int) DB::selectOne("SELECT COUNT(*) AS c FROM `$table` WHERE $where")->c;
            if ($c > 0) {
                $out[] = ['table' => $table, 'col' => $col, 'parent' => $parent, 'count' => $c];
            }
        }
        return $out;
    }

    private function isColumnNullable(string $table, string $col): bool
    {
        $db = DB::connection()->getDatabaseName();
        $r = DB::selectOne(
            'SELECT IS_NULLABLE AS n FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db, $table, $col]
        );
        return $r && strtoupper($r->n) === 'YES';
    }

    private function constraintExists(string $table, string $name): bool
    {
        $db = DB::connection()->getDatabaseName();
        $r = DB::selectOne(
            'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$db, $table, $name]
        );
        return ((int) ($r->c ?? 0)) > 0;
    }
};
