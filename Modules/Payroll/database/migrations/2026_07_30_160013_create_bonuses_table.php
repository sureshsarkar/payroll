<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — One-time bonus / incentive entries applied to a month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->boolean('is_applied')->default(false)->comment('consumed by a payroll run');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonuses');
    }
};
