<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: HR can delete a payroll run from the runs list. The run header is
 * kept with a `deleted_at` timestamp — it drops off the HR/admin lists,
 * dashboards and employees' payslip screens, but stays recoverable and
 * auditable in the DB (and can be restored from "Deleted runs").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_runs', 'deleted_at')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_runs', 'deleted_at')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
