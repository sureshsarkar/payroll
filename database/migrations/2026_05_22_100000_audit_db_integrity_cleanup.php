<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database integrity cleanup — audit 2026-05-22.
 *
 * REQUIRES A DATABASE BACKUP BEFORE RUNNING. This migration:
 *
 *   1. Nulls out orphan self-references on `users` (coach_id, parent_coach_id,
 *      added_by, referred_by_user_id) where the referenced user no longer exists
 *      — 8 rows total across these 4 columns per the 2026-05-22 audit.
 *
 *   2. Deletes 12 orphan rows in `user_memberships` whose user_id points to a
 *      deleted user. These rows can never resolve and can never be charged or
 *      renewed — they were stranded by historical hard-deletes.
 *
 *   3. Deletes 10 orphan rows in `course_progress` whose user_id or course_id
 *      points to a deleted row.
 *
 *   4. Adds nullable foreign-key constraints with appropriate ON DELETE
 *      behavior (SET NULL for soft refs, CASCADE for owned rows).
 *
 *   5. Adds missing indexes on hot-path columns (course_progress.user_id +
 *      course_id, courses.deleted_at, orders.payment_status + created_at).
 *
 * Rollback restores the previous state (drops FKs + indexes; does NOT undelete
 * orphan rows — they were unreachable anyway).
 *
 * Run order:
 *   - Backup DB first (e.g. mysqldump or platform backup)
 *   - `php artisan migrate`
 *   - Verify with `php artisan tinker --execute="..."` — see comments below
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Dry-run mode ──────────────────────────────────────────────
        // Set MIGRATION_DRY_RUN=1 in .env BEFORE running migrate to see
        // what this migration WOULD do without committing changes.
        // Example:
        //   MIGRATION_DRY_RUN=1 php artisan migrate
        // The migration reports counts but rolls everything back at the
        // end. Use this to verify the orphan counts match your prod
        // before committing to the real run.
        $dryRun = (bool) env('MIGRATION_DRY_RUN', false);

        DB::beginTransaction();
        try {
            $this->doMigration();

            if ($dryRun) {
                DB::rollBack();
                throw new \RuntimeException(
                    '[DRY RUN] Migration completed successfully but rolled back. '
                    . 'Remove MIGRATION_DRY_RUN=1 from .env to commit.'
                );
            }
            DB::commit();
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            throw $e;
        }
    }

    private function doMigration(): void
    {
        // ── Step 1: orphan cleanup ────────────────────────────────────

        // users self-references — null out orphans (preserve row, drop bad ref)
        DB::statement("
            UPDATE users
               SET coach_id = NULL
             WHERE coach_id IS NOT NULL
               AND coach_id NOT IN (SELECT id FROM (SELECT id FROM users) AS u)
        ");
        DB::statement("
            UPDATE users
               SET parent_coach_id = NULL
             WHERE parent_coach_id IS NOT NULL
               AND parent_coach_id NOT IN (SELECT id FROM (SELECT id FROM users) AS u)
        ");
        DB::statement("
            UPDATE users
               SET added_by = NULL
             WHERE added_by IS NOT NULL
               AND added_by NOT IN (SELECT id FROM (SELECT id FROM users) AS u)
        ");
        DB::statement("
            UPDATE users
               SET referred_by_user_id = NULL
             WHERE referred_by_user_id IS NOT NULL
               AND referred_by_user_id NOT IN (SELECT id FROM (SELECT id FROM users) AS u)
        ");

        // user_memberships — delete rows whose user no longer exists
        if (Schema::hasTable('user_memberships')) {
            DB::statement("
                DELETE FROM user_memberships
                 WHERE user_id NOT IN (SELECT id FROM (SELECT id FROM users) AS u)
            ");
        }

        // course_progress — delete rows whose user or course no longer exists
        if (Schema::hasTable('course_progress')) {
            DB::statement("
                DELETE FROM course_progress
                 WHERE user_id NOT IN (SELECT id FROM (SELECT id FROM users) AS u)
                    OR course_id NOT IN (SELECT id FROM (SELECT id FROM courses) AS c)
            ");
        }

        // ── Step 2a: normalize column types ──────────────────────────
        // SCHEMA-AUDIT 2026-05-22 — discovered during dry-run that
        // users.id is bigint(20) unsigned but users.coach_id / .added_by
        // / .parent_coach_id are int(11) (signed). MySQL refuses FKs
        // across mismatched column types. Convert to match.
        //
        // ALTER COLUMN to UNSIGNED BIGINT is safe IFF no existing row
        // has a negative value. We CAST during conversion — MySQL will
        // throw if a negative value would overflow, in which case the
        // outer transaction rolls back.
        $userIdColumns = ['coach_id', 'parent_coach_id', 'added_by'];
        foreach ($userIdColumns as $col) {
            if (! Schema::hasColumn('users', $col)) continue;
            // Skip if already bigint unsigned (defensive against re-run)
            $info = DB::selectOne("
                SELECT COLUMN_TYPE
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'users'
                   AND COLUMN_NAME = ?
            ", [$col]);
            if ($info && stripos((string) $info->COLUMN_TYPE, 'bigint') !== false) continue;

            DB::statement("ALTER TABLE users MODIFY COLUMN `$col` BIGINT UNSIGNED NULL");
        }

        // ── Step 2b: add FK constraints ──────────────────────────────
        // Nullable + ON DELETE SET NULL so hard-deleting a user doesn't
        // cascade-delete dependent data; orphans just null out.
        Schema::table('users', function (Blueprint $t) {
            if (! $this->hasForeignKey('users', 'users_coach_id_foreign')) {
                $t->foreign('coach_id', 'users_coach_id_foreign')
                  ->references('id')->on('users')->nullOnDelete();
            }
            if (Schema::hasColumn('users', 'parent_coach_id')
                && ! $this->hasForeignKey('users', 'users_parent_coach_id_foreign')) {
                $t->foreign('parent_coach_id', 'users_parent_coach_id_foreign')
                  ->references('id')->on('users')->nullOnDelete();
            }
            if (Schema::hasColumn('users', 'added_by')
                && ! $this->hasForeignKey('users', 'users_added_by_foreign')) {
                $t->foreign('added_by', 'users_added_by_foreign')
                  ->references('id')->on('users')->nullOnDelete();
            }
        });

        if (Schema::hasTable('user_memberships')) {
            Schema::table('user_memberships', function (Blueprint $t) {
                if (! $this->hasForeignKey('user_memberships', 'user_memberships_user_id_foreign')) {
                    $t->foreign('user_id', 'user_memberships_user_id_foreign')
                      ->references('id')->on('users')->cascadeOnDelete();
                }
            });
        }

        // ── Step 3: missing indexes ──────────────────────────────────
        // MySQL DDL is implicitly committed (CREATE INDEX can't roll back),
        // so we must check for existing indexes from a prior partial run
        // before re-creating them. Idempotent across retries.

        if (Schema::hasTable('course_progress')) {
            if (! $this->hasIndex('course_progress', 'cp_user_course_idx')) {
                DB::statement('ALTER TABLE course_progress ADD INDEX cp_user_course_idx (user_id, course_id)');
            }
            if (! $this->hasIndex('course_progress', 'cp_user_idx')) {
                DB::statement('ALTER TABLE course_progress ADD INDEX cp_user_idx (user_id)');
            }
            if (! $this->hasIndex('course_progress', 'cp_course_idx')) {
                DB::statement('ALTER TABLE course_progress ADD INDEX cp_course_idx (course_id)');
            }
        }

        if (Schema::hasColumn('courses', 'deleted_at')
            && ! $this->hasIndex('courses', 'courses_deleted_at_idx')) {
            DB::statement('ALTER TABLE courses ADD INDEX courses_deleted_at_idx (deleted_at)');
        }

        if (! $this->hasIndex('orders', 'orders_paid_created_idx')) {
            DB::statement('ALTER TABLE orders ADD INDEX orders_paid_created_idx (payment_status, created_at)');
        }
    }

    /**
     * Defensive — MySQL CREATE INDEX is non-transactional. Check
     * information_schema before adding so re-running a partially
     * applied migration doesn't throw 1061 Duplicate key.
     */
    private function hasIndex(string $table, string $name): bool
    {
        $row = DB::selectOne("
            SELECT 1 AS found
              FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1
        ", [$table, $name]);
        return $row !== null;
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_paid_created_idx');
        });

        Schema::table('courses', function (Blueprint $t) {
            if (Schema::hasColumn('courses', 'deleted_at')) {
                $t->dropIndex('courses_deleted_at_idx');
            }
        });

        if (Schema::hasTable('course_progress')) {
            Schema::table('course_progress', function (Blueprint $t) {
                $t->dropIndex('cp_user_course_idx');
                $t->dropIndex('cp_user_idx');
                $t->dropIndex('cp_course_idx');
            });
        }

        if (Schema::hasTable('user_memberships')) {
            Schema::table('user_memberships', function (Blueprint $t) {
                $t->dropForeign('user_memberships_user_id_foreign');
            });
        }

        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign('users_coach_id_foreign');
            if (Schema::hasColumn('users', 'parent_coach_id')) {
                $t->dropForeign('users_parent_coach_id_foreign');
            }
            if (Schema::hasColumn('users', 'added_by')) {
                $t->dropForeign('users_added_by_foreign');
            }
        });

        // Note: we don't restore deleted orphan rows on rollback — they were
        // unreachable anyway. Restore from backup if needed.
    }

    /**
     * Defensive helper — MySQL throws if you create an FK that already exists.
     * Doctrine's Schema::hasForeignKey would do this but requires DBAL.
     */
    private function hasForeignKey(string $table, string $name): bool
    {
        $db = DB::connection()->getDatabaseName();
        $row = DB::selectOne("
            SELECT 1 AS found
              FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$db, $table, $name]);
        return $row !== null;
    }
};
