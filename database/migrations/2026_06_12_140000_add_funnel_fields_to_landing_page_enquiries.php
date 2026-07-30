<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-12 — Phase 2 funnel: enterprise CRM fields on landing_page_enquiries.
 *
 *  - value            : deal/lead amount (revenue-weighted pipeline + won value)
 *  - lost_reason      : structured reason when a lead is marked Lost
 *  - won_at           : timestamp the lead converted (cycle-time + revenue date)
 *  - converted_user_id: the student account a won lead became (FK users, SET NULL)
 *  - order_id         : optional order created at conversion
 *  - deleted_at       : SOFT DELETES (destroy / bulk-delete become recoverable)
 *
 * Fully guarded + idempotent so a cPanel re-run / partial schema can't brick a
 * deploy. All additive + nullable → safe on existing rows.
 */
return new class extends Migration
{
    private function idx(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)->where('index_name', $name)->exists();
    }

    public function up(): void
    {
        $t = 'landing_page_enquiries';
        if (! Schema::hasTable($t)) {
            return;
        }

        Schema::table($t, function (Blueprint $b) use ($t) {
            if (! Schema::hasColumn($t, 'value'))             { $b->decimal('value', 12, 2)->nullable()->after('service'); }
            if (! Schema::hasColumn($t, 'lost_reason'))       { $b->string('lost_reason', 255)->nullable()->after('status'); }
            if (! Schema::hasColumn($t, 'won_at'))            { $b->timestamp('won_at')->nullable()->after('lost_reason'); }
            if (! Schema::hasColumn($t, 'converted_user_id')) { $b->unsignedBigInteger('converted_user_id')->nullable()->after('won_at'); }
            if (! Schema::hasColumn($t, 'order_id'))          { $b->unsignedBigInteger('order_id')->nullable()->after('converted_user_id'); }
            if (! Schema::hasColumn($t, 'deleted_at'))        { $b->softDeletes(); }
        });

        if (Schema::hasColumn($t, 'converted_user_id') && ! $this->idx($t, 'lpe_converted_user_idx')) {
            Schema::table($t, fn (Blueprint $b) => $b->index('converted_user_id', 'lpe_converted_user_idx'));
            try {
                Schema::table($t, fn (Blueprint $b) => $b->foreign('converted_user_id')->references('id')->on('users')->nullOnDelete());
            } catch (\Throwable $e) {
                \Log::warning('lpe converted_user_id FK skipped: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $t = 'landing_page_enquiries';
        if (! Schema::hasTable($t)) {
            return;
        }
        Schema::table($t, function (Blueprint $b) use ($t) {
            try { $b->dropForeign(['converted_user_id']); } catch (\Throwable $e) {}
            try { $b->dropIndex('lpe_converted_user_idx'); } catch (\Throwable $e) {}
            foreach (['value', 'lost_reason', 'won_at', 'converted_user_id', 'order_id'] as $c) {
                if (Schema::hasColumn($t, $c)) { $b->dropColumn($c); }
            }
            if (Schema::hasColumn($t, 'deleted_at')) { $b->dropSoftDeletes(); }
        });
    }
};
