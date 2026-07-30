<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Leave applications (Employee applies -> HR approves).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('employee applying');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 1)->comment('supports half-days');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending')->comment('pending|approved|rejected|cancelled');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('leave_type_id');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
