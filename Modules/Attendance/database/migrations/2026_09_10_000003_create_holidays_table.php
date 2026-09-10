<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company holiday master.
 *
 * One row per company per calendar date. The Attendance Register PDF pulls the
 * rows for the report month and marks those dates HD (Holiday) automatically,
 * so a holiday shows up on every employee's sheet without anyone marking
 * per-day attendance for it. Company-scoped like the rest of the HR data
 * (nullable company_id + CompanyScope on the model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->date('holiday_date');
            $table->string('name');
            $table->timestamps();

            $table->index('company_id');
            $table->index('holiday_date');
            $table->unique(['company_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
