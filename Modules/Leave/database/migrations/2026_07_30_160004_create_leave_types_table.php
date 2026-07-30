<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Leave type master (Casual, Sick, Earned, LOP, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->nullable()->unique();
            $table->boolean('is_paid')->default(true)->comment('paid leave vs loss-of-pay');
            $table->decimal('annual_quota', 5, 1)->default(0)->comment('days per year');
            $table->boolean('carry_forward')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
