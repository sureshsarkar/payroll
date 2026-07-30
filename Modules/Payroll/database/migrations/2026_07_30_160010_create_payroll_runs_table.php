<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Monthly payroll run header.
 * Workflow: draft -> hr_submitted -> admin_approved (-> paid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('draft')->comment('draft|hr_submitted|admin_approved|paid');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
