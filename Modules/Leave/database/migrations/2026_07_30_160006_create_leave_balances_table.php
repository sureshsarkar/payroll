<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Per-employee, per-type, per-year leave balance ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->unsignedSmallInteger('year');
            $table->decimal('allotted', 6, 1)->default(0);
            $table->decimal('used', 6, 1)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
