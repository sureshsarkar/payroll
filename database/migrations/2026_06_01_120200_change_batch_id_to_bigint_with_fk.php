<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding [15] — batch_id is int(11) with no foreign key.
 *
 * order_items.batch_id and course_live_classes.batch_id are int(11) and carry
 * NO referential integrity, even though they reference course_batches.id —
 * which is BIGINT(20) UNSIGNED. The type mismatch alone blocks an FK, and the
 * missing FK lets a row point at a non-existent batch. (enrollments.batch_id
 * was already migrated to bigint unsigned by 2026_05_18_140000.)
 *
 * This migration:
 *   1. widens both columns to BIGINT(20) UNSIGNED to match course_batches.id
 *   2. adds an FK with ON DELETE SET NULL — deleting a batch nulls the
 *      reference rather than orphaning it or blocking the delete.
 *
 * DATA NORMALISATION: some legacy rows store batch_id = 0 as a "no batch"
 * sentinel. 0 is not a valid course_batches.id, so it would fail the FK — and
 * it's semantically wrong (NULL is the correct "no batch" value, which the
 * enrollment/attendance/announcement code already treats as course-wide).
 * up() rewrites batch_id = 0 to NULL before adding the FK. Verified there are
 * NO positive orphan batch_id values (every batch_id > 0 already exists in
 * course_batches), so after the 0 -> NULL pass the FK creation cannot fail.
 *
 * DESIGN NOTE: ON DELETE SET NULL is the conventional choice for a nullable
 * batch reference (a batched live class/order line gracefully degrades to
 * course-wide if its batch is removed). If you'd rather PRESERVE the batch_id
 * on financial history and instead BLOCK batch deletion while order_items
 * reference it, change order_items' rule to ON DELETE RESTRICT.
 *
 * NOTE: authored as a ready-to-run deliverable; NOT yet executed. Apply with
 * `php artisan migrate` when you choose to.
 */
return new class extends Migration
{
    /** @var array<int,array{table:string,fk:string}> */
    private array $targets = [
        ['table' => 'order_items',          'fk' => 'order_items_batch_id_foreign'],
        ['table' => 'course_live_classes',  'fk' => 'course_live_classes_batch_id_foreign'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $t) {
            if (! Schema::hasColumn($t['table'], 'batch_id')) {
                continue;
            }
            // 1. widen to match course_batches.id (bigint unsigned).
            //    Idempotent — MODIFY to the same type is a no-op if already run.
            DB::statement("ALTER TABLE `{$t['table']}` MODIFY `batch_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL");

            // 2. normalise the legacy 0 "no batch" sentinel to NULL so the FK
            //    is satisfiable (0 is not a valid course_batches.id).
            DB::table($t['table'])->where('batch_id', 0)->update(['batch_id' => null]);

            // 3. add FK (guard against re-run / pre-existing constraint)
            if (! $this->fkExists($t['fk'])) {
                DB::statement(
                    "ALTER TABLE `{$t['table']}` ADD CONSTRAINT `{$t['fk']}` " .
                    "FOREIGN KEY (`batch_id`) REFERENCES `course_batches` (`id`) ON DELETE SET NULL"
                );
            }
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $t) {
            if (! Schema::hasColumn($t['table'], 'batch_id')) {
                continue;
            }
            if ($this->fkExists($t['fk'])) {
                DB::statement("ALTER TABLE `{$t['table']}` DROP FOREIGN KEY `{$t['fk']}`");
            }
            DB::statement("ALTER TABLE `{$t['table']}` MODIFY `batch_id` INT(11) NULL DEFAULT NULL");
        }
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
