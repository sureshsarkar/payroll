<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant conversion — Phase 1 (additive, nullable).
 *
 * Adds the tenant key `company_id` to the HrEmployee tables. Nullable first so
 * existing rows keep working; backfilled in a later migration, then made
 * required. Indexed, no DB constraint — matches the HR-domain FK convention.
 * `employee_profiles.company_id` is the source of truth for an employee's
 * company.
 */
return new class extends Migration
{
    private array $tables = ['departments', 'employee_profiles'];

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
