<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coach_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();  
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('html_content')->nullable();
            $table->longText('css_content')->nullable();
            $table->string('theme')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_landing_pages');
    }
};
