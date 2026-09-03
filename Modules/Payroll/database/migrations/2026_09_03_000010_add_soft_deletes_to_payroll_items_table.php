<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: soft delete for HR. HR can now delete an individual payslip
 * (payroll item); it is kept in the table with a `deleted_at` timestamp and
 * excluded from run totals, exports and the employee's own payslip list, but
 * stays recoverable and auditable in the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_items', 'deleted_at')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_items', 'deleted_at')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
