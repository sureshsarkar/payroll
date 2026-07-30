<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database integrity audit — walks every FK gap surfaced by the
 * 2026-05-29 FK consolidation work and counts orphan rows.
 *
 * Promotes storage/app/fk-orphan-scan.php (a one-time scratch script)
 * to a first-class artisan command so it can run nightly in CI without
 * requiring an operator to remember to invoke it.
 *
 *   php artisan audit:db-integrity
 *   php artisan audit:db-integrity --fail-on-orphans   (exit 1 if any found)
 *   php artisan audit:db-integrity --json              (machine-readable output)
 *
 * The relations checked match the FK consolidation migration
 * (2026_05_29_140000_add_missing_foreign_keys). On a freshly-migrated
 * DB, every count must be zero — the migration enforces it. If this
 * command surfaces non-zero counts on prod, it means a row was
 * created that bypassed the constraint (e.g. via DB::statement with
 * FOREIGN_KEY_CHECKS=0, or a partial migration rollback).
 *
 * Also surfaces FK constraint DRIFT — relations that the migration
 * added but are no longer present in INFORMATION_SCHEMA (someone
 * dropped them manually).
 *
 * Exit code: 0 = clean · 1 = orphans found (with --fail-on-orphans)
 *                       · 2 = constraint drift detected
 */
class AuditDbIntegrity extends Command
{
    protected $signature = 'audit:db-integrity
                            {--fail-on-orphans : Exit 1 if any orphan rows are found}
                            {--json : Emit machine-readable JSON instead of human-readable table}';
    protected $description = 'Walk every FK relation and report orphan rows + constraint drift.';

    /** Mirrors self::FKS in 2026_05_29_140000_add_missing_foreign_keys. */
    private const FKS = [
        ['users',                          'country_id',      'countries',              true,  'fk_users_country_id'],
        ['user_education',                 'user_id',         'users',                  false, 'fk_user_education_user_id'],
        ['user_experiences',               'user_id',         'users',                  false, 'fk_user_experiences_user_id'],
        ['user_skill_topics',              'category_id',     'course_categories',      false, 'fk_user_skill_topics_category_id'],
        ['courses',                        'instructor_id',   'users',                  false, 'fk_courses_instructor_id'],
        ['courses',                        'category_id',     'course_categories',      true,  'fk_courses_category_id'],
        ['course_selected_levels',         'course_id',       'courses',                false, 'fk_csl_course_id'],
        ['course_selected_languages',      'course_id',       'courses',                false, 'fk_cslang_course_id'],
        ['course_selected_filter_options', 'course_id',       'courses',                false, 'fk_csfo_course_id'],
        ['course_partner_instructors',     'course_id',       'courses',                false, 'fk_cpi_course_id'],
        ['course_partner_instructors',     'instructor_id',   'users',                  false, 'fk_cpi_instructor_id'],
        ['course_categories',              'parent_id',       'course_categories',      true,  'fk_course_categories_parent_id'],
        ['course_delete_requests',         'course_id',       'courses',                false, 'fk_cdr_course_id'],
        ['quizzes',                        'instructor_id',   'users',                  false, 'fk_quizzes_instructor_id'],
        ['quizzes',                        'chapter_id',      'course_chapters',        false, 'fk_quizzes_chapter_id'],
        ['quizzes',                        'chapter_item_id', 'course_chapter_items',   false, 'fk_quizzes_chapter_item_id'],
        ['quizzes',                        'course_id',       'courses',                false, 'fk_quizzes_course_id'],
        ['quiz_question_answers',          'question_id',     'quiz_questions',         false, 'fk_qqa_question_id'],
        ['quiz_results',                   'user_id',         'users',                  false, 'fk_quiz_results_user_id'],
        ['quiz_results',                   'quiz_id',         'quizzes',                false, 'fk_quiz_results_quiz_id'],
        ['lesson_questions',               'user_id',         'users',                  false, 'fk_lq_user_id'],
        ['lesson_questions',               'course_id',       'courses',                false, 'fk_lq_course_id'],
        ['lesson_questions',               'lesson_id',       'course_chapter_lessons', false, 'fk_lq_lesson_id'],
        ['lesson_replies',                 'user_id',         'users',                  false, 'fk_lr_user_id'],
        ['course_progress',                'user_id',         'users',                  false, 'fk_cp_user_id'],
        ['course_progress',                'course_id',       'courses',                false, 'fk_cp_course_id'],
        ['course_progress',                'chapter_id',      'course_chapters',        true,  'fk_cp_chapter_id'],
        ['course_progress',                'lesson_id',       'course_chapter_lessons', true,  'fk_cp_lesson_id'],
        ['announcements',                  'course_id',       'courses',                false, 'fk_ann_course_id'],
        ['announcements',                  'instructor_id',   'users',                  false, 'fk_ann_instructor_id'],
        ['course_reviews',                 'course_id',       'courses',                false, 'fk_cr_course_id'],
        ['course_reviews',                 'user_id',         'users',                  false, 'fk_cr_user_id'],
        ['enrollments',                    'user_id',         'users',                  false, 'fk_enrollments_user_id'],
        ['enrollments',                    'course_id',       'courses',                false, 'fk_enrollments_course_id'],
        ['order_items',                    'course_id',       'courses',                false, 'fk_oi_course_id'],
        ['orders',                         'buyer_id',        'users',                  true,  'fk_orders_buyer_id'],
        ['orders',                         'seller_id',       'users',                  true,  'fk_orders_seller_id'],
        ['instructor_requests',            'user_id',         'users',                  false, 'fk_ir_user_id'],
    ];

    public function handle(): int
    {
        $report = [
            'scanned_at'    => now()->toIso8601String(),
            'database'      => DB::connection()->getDatabaseName(),
            'total_orphans' => 0,
            'orphan_rows'   => [],
            'missing_fks'   => [],
            'skipped'       => [],
        ];

        foreach (self::FKS as [$table, $col, $parent, $nullable, $fkName]) {
            if (! Schema::hasTable($table)) {
                $report['skipped'][] = ['table' => $table, 'reason' => 'table missing'];
                continue;
            }
            if (! Schema::hasTable($parent)) {
                $report['skipped'][] = ['table' => $table, 'col' => $col, 'reason' => "parent $parent missing"];
                continue;
            }
            if (! Schema::hasColumn($table, $col)) {
                $report['skipped'][] = ['table' => $table, 'col' => $col, 'reason' => 'column missing'];
                continue;
            }

            // Orphan count.
            $where = $nullable
                ? "$col IS NOT NULL AND $col NOT IN (SELECT id FROM `$parent`)"
                : "$col NOT IN (SELECT id FROM `$parent`)";
            try {
                $count = (int) DB::selectOne("SELECT COUNT(*) AS c FROM `$table` WHERE $where")->c;
            } catch (\Throwable $e) {
                $report['skipped'][] = [
                    'table' => $table, 'col' => $col,
                    'reason' => 'query error: ' . substr($e->getMessage(), 0, 80),
                ];
                continue;
            }
            if ($count > 0) {
                $report['orphan_rows'][] = compact('table', 'col', 'parent', 'count');
                $report['total_orphans'] += $count;
            }

            // FK constraint drift — verify the named FK still exists.
            if (! $this->constraintExists($table, $fkName)) {
                $report['missing_fks'][] = compact('table', 'col', 'parent', 'fkName');
            }
        }

        // Emit
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderHuman($report);
        }

        // Exit code policy
        if (! empty($report['missing_fks'])) {
            return 2;  // FK drift — operator-actionable
        }
        if ($report['total_orphans'] > 0 && $this->option('fail-on-orphans')) {
            return 1;
        }
        return 0;
    }

    private function renderHuman(array $r): void
    {
        $this->info('────────────────────────────────────────────────────────────');
        $this->info('DB integrity scan — ' . $r['database'] . ' @ ' . $r['scanned_at']);
        $this->info('────────────────────────────────────────────────────────────');

        if (empty($r['orphan_rows'])) {
            $this->info('Orphan rows: 0 across all checked relations.');
        } else {
            $this->warn('Orphan rows found:');
            $this->table(
                ['Table', 'Column', 'Parent', 'Orphans'],
                array_map(fn ($o) => [$o['table'], $o['col'], $o['parent'], $o['count']], $r['orphan_rows'])
            );
            $this->warn('TOTAL ORPHAN ROWS: ' . $r['total_orphans']);
        }

        if (! empty($r['missing_fks'])) {
            $this->error('FK CONSTRAINT DRIFT DETECTED — named constraints from migration are missing:');
            $this->table(
                ['Table', 'Column', 'Parent', 'Missing FK name'],
                array_map(fn ($m) => [$m['table'], $m['col'], $m['parent'], $m['fkName']], $r['missing_fks'])
            );
            $this->error('Re-run migration `2026_05_29_140000_add_missing_foreign_keys` or restore manually.');
        } else {
            $this->info('FK constraints: all ' . count(self::FKS) . ' named constraints present.');
        }

        if (! empty($r['skipped'])) {
            $this->line('');
            $this->line('Skipped (informational):');
            foreach ($r['skipped'] as $s) {
                $loc = ($s['table'] ?? '?') . '.' . ($s['col'] ?? '*');
                $this->line('  ' . $loc . ' — ' . ($s['reason'] ?? 'unknown'));
            }
        }
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
}
