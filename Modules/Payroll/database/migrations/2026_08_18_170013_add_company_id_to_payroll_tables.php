<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant conversion — Phase 1 (additive, nullable).
 *
 * Adds the tenant key `company_id` to the Payroll tables. Nullable first,
 * backfilled later, then made required. Indexed, no DB constraint.
 */
return new class extends Migration
{
    private array $tables = [
        'salary_structures', 'salary_components', 'salary_revisions',
        'payroll_runs', 'payroll_items', 'loans_advances', 'bonuses',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tbl) {
            if (Schema::hasColumn($tbl, 'company_id')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasColumn($tbl, 'company_id')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
