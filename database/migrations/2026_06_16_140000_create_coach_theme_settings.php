<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-16 — Enterprise theme-change: THEME-SPECIFIC customization storage.
 *
 * Core website data (coach_pages, landing_sections, SEO, menus, media, courses,
 * domain) is already independent of the theme. The only thing a theme switch
 * should change is PRESENTATION (colors/typography/styling on
 * coach_brand_settings). This table snapshots that presentation PER (coach,
 * theme) so that switching back to a previously-used theme restores exactly the
 * styling the coach had under it — without ever touching their content.
 *
 * Tenant-isolated by coach_id. Backward compatible (new table only).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_theme_settings')) {
            return;
        }

        Schema::create('coach_theme_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('theme_id');
            $table->json('settings_json')->nullable(); // presentation snapshot (colors, etc.)
            $table->timestamps();

            $table->unique(['coach_id', 'theme_id']); // one stored customization per coach+theme
            $table->index('coach_id');
            $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_theme_settings');
    }
};
