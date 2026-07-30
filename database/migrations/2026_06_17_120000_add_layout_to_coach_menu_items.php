<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-17 — MEGA MENU support for the per-coach navigation builder.
 *
 * Adds `layout` to coach_menu_items. Only meaningful on a TOP-LEVEL item
 * (parent_id IS NULL):
 *   - 'dropdown' (default) — the existing single-column dropdown.
 *   - 'mega'                — a wide multi-column panel: the item's direct
 *                             children are COLUMNS (headings), and each column's
 *                             own children are the LINKS in that column.
 *
 * Fully backward compatible: every existing item defaults to 'dropdown', so
 * nothing changes for coaches who don't opt in. Guarded + idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_menu_items') && ! Schema::hasColumn('coach_menu_items', 'layout')) {
            Schema::table('coach_menu_items', function (Blueprint $table) {
                $table->string('layout', 12)->default('dropdown')->after('target');
            });
        }
    }

    public function down(): void
    {
        // Non-destructive — renderer + builder depend on it.
    }
};
