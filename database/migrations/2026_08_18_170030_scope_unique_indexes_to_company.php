<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant fix — make per-tenant unique keys company-scoped.
 *
 * These columns were globally unique in the single-tenant schema. With
 * companies, the same value must be allowed once PER company, so the unique
 * index becomes composite with company_id. Without this, seeding a second
 * company (leave-type "CL", department "GEN") or reusing an employee code /
 * running the same payroll month in two companies fails with a duplicate-key
 * error — which broke company registration.
 *
 * user_id / (user_id, attendance_date) / (user_id, leave_type_id, year) stay
 * global: a user belongs to exactly one company, so they can't collide.
 */
return new class extends Migration
{
    /** table => [oldIndexName, [old cols], [new composite cols]] */
    private array $map = [
        'leave_types'       => ['leave_types_code_unique',                 ['code'],          ['company_id', 'code']],
        'departments'       => ['departments_code_unique',                 ['code'],          ['company_id', 'code']],
        'employee_profiles' => ['employee_profiles_employee_code_unique',  ['employee_code'], ['company_id', 'employee_code']],
        'payroll_runs'      => ['payroll_runs_year_month_unique',          ['year', 'month'], ['company_id', 'year', 'month']],
    ];

    public function up(): void
    {
        foreach ($this->map as $table => [$oldName, $oldCols, $newCols]) {
            Schema::table($table, function (Blueprint $t) use ($oldName, $newCols) {
                $t->dropUnique($oldName);
                $t->unique($newCols);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->map as $table => [$oldName, $oldCols, $newCols]) {
            Schema::table($table, function (Blueprint $t) use ($oldCols, $newCols) {
                $t->dropUnique($newCols);
                $t->unique($oldCols);
            });
        }
    }
};
