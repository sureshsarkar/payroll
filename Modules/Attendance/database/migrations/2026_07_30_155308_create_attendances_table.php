<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Phase 1 (additive).
 *
 * Daily attendance record per employee (a user with role=student, relabelled
 * "Employee"). One row per user per calendar date. FKs are kept as indexed
 * columns (no DB-level constraint) to stay safe against the legacy users-table
 * collation; integrity is enforced in the application layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->comment('employee (users.id)');
            $table->date('attendance_date');

            // Present | Absent | HalfDay | Leave | Holiday | WFH
            $table->string('status', 20)->default('Present');

            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();

            // manual | self | biometric | import  — keeps the door open for a
            // future biometric/API feed without a schema change.
            $table->string('source', 20)->default('manual');

            $table->unsignedBigInteger('marked_by')->nullable()->comment('HR/Admin user id who marked it');
            $table->string('remarks', 255)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'attendance_date']);
            $table->index('attendance_date');
            $table->index('status');
            $table->index('marked_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
