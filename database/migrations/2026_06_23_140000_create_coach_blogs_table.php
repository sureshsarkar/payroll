<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-23 — per-coach Blog Management. Each coach owns their own blog posts
 * (separate from the platform Modules\Blog), shown on their custom website.
 * Idempotent so it's safe to re-run on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_blogs')) {
            return;
        }
        Schema::create('coach_blogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id')->index();   // owning coach (users.id)
            $table->string('slug', 255);
            $table->string('title', 255);
            $table->text('short_description')->nullable();
            $table->longText('content')->nullable();           // rich HTML
            $table->string('image', 500)->nullable();          // featured image path
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('status', 20)->default('draft');    // draft | published
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            // Slug unique PER coach (two coaches may use the same slug).
            $table->unique(['coach_id', 'slug']);
            $table->index(['coach_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_blogs');
    }
};
