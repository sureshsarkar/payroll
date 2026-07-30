<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — HR profile for a user (role=student => "Employee").
 *
 * Sits alongside the existing `users` row rather than altering it, so the
 * legacy LMS columns stay intact during the transition. `reporting_hr_id`
 * scopes an employee to an HR (role=instructor) for team-based access control.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('users.id of the employee');
            $table->string('employee_code', 40)->nullable()->unique();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('reporting_hr_id')->nullable()->comment('users.id of the HR (instructor)');
            $table->string('designation')->nullable();
            $table->string('employment_type', 30)->nullable()->comment('full_time|part_time|contract|intern');
            $table->date('date_of_joining')->nullable();
            $table->date('date_of_exit')->nullable();
            $table->string('status', 20)->default('active')->comment('active|onboarding|exited|suspended');
            $table->timestamps();

            $table->index('department_id');
            $table->index('reporting_hr_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
