<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature: soft delete for HR. HR can now delete an employee; the HR profile is
 * kept with a `deleted_at` timestamp, which drops the person from every
 * team-scoped view (attendance, leave, payroll, salary structures) while
 * leaving the underlying login account and all history recoverable in the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_profiles', 'deleted_at')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_profiles', 'deleted_at')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
