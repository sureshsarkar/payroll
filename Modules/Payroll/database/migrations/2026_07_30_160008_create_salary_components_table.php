<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Earning/deduction lines for a salary structure.
 * calc_type=fixed uses `value` as an amount; calc_type=percent_of_basic uses
 * `value` as a percentage (e.g. HRA 40% of Basic, PF 12% of Basic).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salary_structure_id');
            $table->string('type', 20)->comment('earning|deduction');
            $table->string('name');            // Basic, HRA, PF, ESIC, PT, TDS ...
            $table->string('code', 30)->nullable();
            $table->string('calc_type', 30)->default('fixed')->comment('fixed|percent_of_basic|percent_of_gross');
            $table->decimal('value', 12, 2)->default(0);
            $table->boolean('is_statutory')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['salary_structure_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
