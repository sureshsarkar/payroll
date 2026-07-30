<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Per-employee salary structure (versioned by effective date).
 * The individual earning/deduction lines live in `salary_components`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('ctc_annual', 12, 2)->default(0);
            $table->decimal('gross_monthly', 12, 2)->default(0);
            $table->date('effective_from');
            $table->boolean('is_current')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};
