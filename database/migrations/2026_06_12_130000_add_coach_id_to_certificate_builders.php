<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-12 — Per-coach branded certificates.
 *
 * Adds a NULLABLE coach_id to both certificate tables. The existing single
 * global template (rows with coach_id NULL) is preserved untouched and becomes
 * the platform DEFAULT/fallback — so behaviour is identical until a coach
 * customises their own template. A coach's template rows carry their coach_id.
 *
 * Fully guarded + idempotent so a cPanel re-run / partial schema can't brick a
 * deploy. coach_id → users, ON DELETE CASCADE (a coach's template dies with the
 * coach); the NULL global row is never affected.
 */
return new class extends Migration
{
    private array $tables = ['certificate_builders', 'certificate_builder_items'];

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'coach_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('coach_id')->nullable()->after('id');
            });

            $idx = $table . '_coach_id_idx';
            if (! $this->indexExists($table, $idx)) {
                Schema::table($table, fn (Blueprint $t) => $t->index('coach_id', $idx));
            }

            // FK to users (cascade) — guarded; if a legacy/orphan state blocks
            // it, skip rather than brick the deploy. The app resolves templates
            // by coach_id regardless of the FK.
            try {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
                });
            } catch (\Throwable $e) {
                \Log::warning("certificate {$table} coach_id FK skipped: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'coach_id')) {
                Schema::table($table, function (Blueprint $t) {
                    try { $t->dropForeign(['coach_id']); } catch (\Throwable $e) {}
                    try { $t->dropIndex($table . '_coach_id_idx'); } catch (\Throwable $e) {}
                    $t->dropColumn('coach_id');
                });
            }
        }
    }
};
