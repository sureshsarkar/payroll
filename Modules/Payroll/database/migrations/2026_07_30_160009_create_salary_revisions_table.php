<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Salary revision history (increments with dates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('old_ctc', 12, 2)->nullable();
            $table->decimal('new_ctc', 12, 2);
            $table->date('effective_from');
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_revisions');
    }
};
