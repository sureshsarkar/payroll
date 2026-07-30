<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Attendance regularization requests.
 * Employee raises ("forgot to punch") -> HR approves/rejects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_regularizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('employee raising the request');
            $table->date('attendance_date');
            $table->string('requested_status', 20)->comment('desired status e.g. Present');
            $table->time('requested_check_in')->nullable();
            $table->time('requested_check_out')->nullable();
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending')->comment('pending|approved|rejected');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'attendance_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_regularizations');
    }
};
