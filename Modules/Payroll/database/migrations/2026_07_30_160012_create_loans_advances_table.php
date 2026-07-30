<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Loan / salary advance with monthly recovery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20)->default('advance')->comment('loan|advance');
            $table->decimal('principal', 12, 2);
            $table->decimal('monthly_recovery', 12, 2)->default(0);
            $table->decimal('recovered', 12, 2)->default(0);
            $table->string('status', 20)->default('active')->comment('active|closed');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans_advances');
    }
};
