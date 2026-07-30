<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll conversion — Department / Team master (replaces LMS "batches").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->nullable()->unique();
            $table->unsignedBigInteger('head_user_id')->nullable()->comment('HR/manager users.id');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('self ref for sub-teams');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('head_user_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
