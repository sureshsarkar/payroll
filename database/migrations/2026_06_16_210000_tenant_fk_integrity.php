<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-16 (audit Phase 3) — tenant FK / referential-integrity hardening.
 *
 * These are INTEGRITY fixes, not active-leak fixes (app-layer scoping already
 * isolates tenants). They close the "orphan / ambiguous tenant row" gaps the
 * audit found: tenant-owner columns stored as int(11) with no FK, so a row
 * could point at a non-existent user / page / enquiry.
 *
 * SET-NULL targets (column is/becomes nullable; an owner deletion degrades the
 * reference to NULL rather than orphaning or blocking):
 *   - carts.batch_id                  int  → bigint, FK → course_batches  (completes
 *                                            the batch_id family alongside the
 *                                            order_items/course_live_classes migration)
 *   - courses.added_by                int  → bigint, FK → users
 *   - landing_page_enquiries.coach_id int  → bigint, FK → users
 *   - landing_page_enquiries.added_by int  → bigint, FK → users
 *   - coach_pages.site_id             bigint(nullable), FK → coach_landing_pages
 *   - coach_landing_pages.added_by    bigint NOT NULL → nullable, FK → users
 *
 * CASCADE target (NOT NULL child of its parent — a note dies with its enquiry):
 *   - lead_notes.enquiry_id           FK → landing_page_enquiries (only if no orphans)
 *
 * SAFETY: every step is guarded + idempotent. For SET-NULL targets we first NULL
 * any 0-sentinel or orphan value so the FK is always satisfiable (verified 0
 * orphans on the dev mirror except 1 on coach_landing_pages.added_by, which this
 * nulls). For the CASCADE target we skip (and log) rather than fail the deploy if
 * prod data carries orphans. Run with `php artisan migrate`.
 */
return new class extends Migration
{
    /** @var array<int,array{table:string,col:string,parent:string,pcol:string,fk:string}> */
    private array $setNull = [
        ['table' => 'carts',                  'col' => 'batch_id', 'parent' => 'course_batches',      'pcol' => 'id', 'fk' => 'carts_batch_id_foreign'],
        ['table' => 'courses',                'col' => 'added_by', 'parent' => 'users',               'pcol' => 'id', 'fk' => 'courses_added_by_foreign'],
        ['table' => 'landing_page_enquiries', 'col' => 'coach_id', 'parent' => 'users',               'pcol' => 'id', 'fk' => 'lpe_coach_id_foreign'],
        ['table' => 'landing_page_enquiries', 'col' => 'added_by', 'parent' => 'users',               'pcol' => 'id', 'fk' => 'lpe_added_by_foreign'],
        ['table' => 'coach_pages',            'col' => 'site_id',  'parent' => 'coach_landing_pages',  'pcol' => 'id', 'fk' => 'coach_pages_site_id_foreign'],
        ['table' => 'coach_landing_pages',    'col' => 'added_by', 'parent' => 'users',               'pcol' => 'id', 'fk' => 'coach_landing_pages_added_by_foreign'],
    ];

    public function up(): void
    {
        foreach ($this->setNull as $t) {
            if (! Schema::hasTable($t['table']) || ! Schema::hasColumn($t['table'], $t['col'])) {
                continue;
            }

            // 1. widen to bigint unsigned + ensure nullable (idempotent; this also
            //    relaxes coach_landing_pages.added_by from NOT NULL so SET NULL works).
            DB::statement("ALTER TABLE `{$t['table']}` MODIFY `{$t['col']}` BIGINT(20) UNSIGNED NULL DEFAULT NULL");

            // 2. NULL out 0-sentinels + orphans so the FK is satisfiable.
            DB::statement(
                "UPDATE `{$t['table']}` SET `{$t['col']}` = NULL " .
                "WHERE `{$t['col']}` IS NOT NULL " .
                "AND `{$t['col']}` NOT IN (SELECT `{$t['pcol']}` FROM `{$t['parent']}`)"
            );

            // 3. add FK (guarded against re-run / pre-existing constraint).
            if (! $this->fkExists($t['fk'])) {
                DB::statement(
                    "ALTER TABLE `{$t['table']}` ADD CONSTRAINT `{$t['fk']}` " .
                    "FOREIGN KEY (`{$t['col']}`) REFERENCES `{$t['parent']}` (`{$t['pcol']}`) ON DELETE SET NULL"
                );
            }
        }

        // lead_notes.enquiry_id — NOT NULL child of an enquiry → ON DELETE CASCADE.
        // Can't NULL orphans (column is NOT NULL), so add the FK only when clean;
        // otherwise log + skip so a dirty prod row never aborts the whole deploy.
        if (Schema::hasTable('lead_notes') && Schema::hasColumn('lead_notes', 'enquiry_id')
            && ! $this->fkExists('lead_notes_enquiry_id_foreign')) {
            $orphans = (int) DB::scalar(
                'SELECT COUNT(*) FROM lead_notes n ' .
                'WHERE n.enquiry_id IS NOT NULL ' .
                'AND NOT EXISTS (SELECT 1 FROM landing_page_enquiries e WHERE e.id = n.enquiry_id)'
            );
            if ($orphans === 0) {
                DB::statement(
                    'ALTER TABLE `lead_notes` ADD CONSTRAINT `lead_notes_enquiry_id_foreign` ' .
                    'FOREIGN KEY (`enquiry_id`) REFERENCES `landing_page_enquiries` (`id`) ON DELETE CASCADE'
                );
            } else {
                Log::warning("Phase3 FK integrity: lead_notes has {$orphans} orphan enquiry_id rows — FK skipped. Clean them then re-run.");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->setNull as $t) {
            if (! Schema::hasTable($t['table'])) {
                continue;
            }
            if ($this->fkExists($t['fk'])) {
                DB::statement("ALTER TABLE `{$t['table']}` DROP FOREIGN KEY `{$t['fk']}`");
            }
        }
        if (Schema::hasTable('lead_notes') && $this->fkExists('lead_notes_enquiry_id_foreign')) {
            DB::statement('ALTER TABLE `lead_notes` DROP FOREIGN KEY `lead_notes_enquiry_id_foreign`');
        }
        // Column types intentionally left as bigint — reverting to int(11) buys
        // nothing and risks data loss on ids > 2^31.
    }

    private function fkExists(string $name): bool
    {
        return collect(DB::select(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS ' .
            'WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = "FOREIGN KEY"',
            [$name]
        ))->isNotEmpty();
    }
};
