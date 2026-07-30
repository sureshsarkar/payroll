<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Per-employee line in a payroll run == the payslip.
 * `earnings` / `deductions` capture the computed breakup as JSON so a payslip
 * can be reproduced exactly even after the salary structure later changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('user_id');

            $table->unsignedTinyInteger('payable_days')->default(0);
            $table->decimal('lop_days', 5, 1)->default(0);

            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('lop_amount', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);

            $table->json('earnings')->nullable();     // [{name, amount}, ...]
            $table->json('deductions')->nullable();   // [{name, amount, statutory}]

            $table->string('payslip_path')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
