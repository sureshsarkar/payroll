<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-12 security audit (Phase 3, SAFE subset) — add indexes on
 * tenant-filter columns that were unindexed. These columns are used in
 * per-coach WHERE filters; an index makes the tenant filter index-driven
 * (perf) and is purely additive (no data constraint, no lock risk beyond a
 * brief metadata lock on these small tables).
 *
 * Deliberately fully GUARDED + IDEMPOTENT: each index is only created when the
 * table/column exists and the index is absent, so re-running or partial schemas
 * can never brick a cPanel deploy.
 *
 * DEFERRED to a staging dry-run (NOT in this migration — they can fail/lock on
 * prod-sized data and need an orphan audit first): foreign keys (incl.
 * carts.batch_id, which also needs an int→bigint type alignment), the
 * coach_id denormalization onto lessons/live-classes/quiz answers, and the
 * owning-coach SET NULL → NOT NULL conversions. The cross-tenant leakage those
 * address is already mitigated at the application query layer.
 */
return new class extends Migration
{
    /** Index targets: [table, column, index name]. */
    private array $targets = [
        ['landing_page_enquiries', 'added_by', 'lpe_added_by_idx'],
        ['youtube_credentials', 'instructor_id', 'yt_cred_instructor_idx'],
        ['carts', 'batch_id', 'carts_batch_id_idx'],
    ];

    private function indexExists(string $table, string $name): bool
    {
        $db = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $db)
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }

    public function up(): void
    {
        foreach ($this->targets as [$table, $column, $name]) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, $column)
                && ! $this->indexExists($table, $name)) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($column, $name) {
                        $t->index($column, $name);
                    });
                } catch (\Throwable $e) {
                    // Never brick a deploy on an index add (e.g. a differently-
                    // named pre-existing index). Log and continue.
                    \Log::warning("tenant-index skipped {$table}.{$column}: " . $e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->targets as [$table, $column, $name]) {
            if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($name) {
                        $t->dropIndex($name);
                    });
                } catch (\Throwable $e) {
                    \Log::warning("tenant-index drop skipped {$name}: " . $e->getMessage());
                }
            }
        }
    }
};
