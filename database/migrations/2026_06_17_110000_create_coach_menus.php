<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-17 — Per-coach customizable NAVIGATION MENU (Website Builder).
 *
 * Replaces the implicit "nav = list of pages" behavior with a real, coach-owned
 * menu builder: add/edit/delete/reorder items, dropdowns (parent_id), and items
 * that link to an internal page, an external URL, a page section (anchor), a
 * named app route, or the site home.
 *
 *   - coach_menus       : one menu per coach per location (default 'primary').
 *   - coach_menu_items  : the items; parent_id (self-FK) gives one level of
 *                         dropdowns; sort_order orders siblings.
 *
 * Fully backward compatible: a coach with NO menu row keeps the existing
 * page-derived navigation (CoachSitePublicController::buildSiteNav falls back).
 * Guarded + idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_menus')) {
            Schema::create('coach_menus', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                $table->string('location', 30)->default('primary'); // primary | footer (future)
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['coach_id', 'location'], 'ux_coach_menu_loc');
                $table->foreign('coach_id', 'fk_coach_menu_coach')
                    ->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('coach_menu_items')) {
            Schema::create('coach_menu_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');                 // denormalised for fast tenant scoping
                $table->unsignedBigInteger('menu_id');
                $table->unsignedBigInteger('parent_id')->nullable();    // self-FK → dropdown child
                $table->string('label', 80);
                // page | url | section | route | home
                $table->string('link_type', 20)->default('page');
                $table->unsignedBigInteger('page_id')->nullable();      // link_type=page/section
                $table->string('url', 600)->nullable();                 // link_type=url
                $table->string('section_anchor', 120)->nullable();      // link_type=section (#id)
                $table->string('route_name', 120)->nullable();          // link_type=route
                $table->string('target', 10)->default('_self');         // _self | _blank
                $table->integer('sort_order')->default(0);
                $table->boolean('is_visible')->default(true);
                $table->timestamps();

                $table->index(['coach_id', 'menu_id'], 'idx_cmi_coach_menu');
                $table->index(['menu_id', 'parent_id', 'sort_order'], 'idx_cmi_tree');
                $table->foreign('menu_id', 'fk_cmi_menu')
                    ->references('id')->on('coach_menus')->cascadeOnDelete();
                // parent_id self-FK: deleting a parent nulls children (they bubble
                // up to top level rather than vanishing).
                $table->foreign('parent_id', 'fk_cmi_parent')
                    ->references('id')->on('coach_menu_items')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive — renderer + builder depend on these.
    }
};
