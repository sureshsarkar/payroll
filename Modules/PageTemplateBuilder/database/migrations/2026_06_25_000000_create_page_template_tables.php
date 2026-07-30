<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-25 — PageTemplateBuilder real-table migration.
 *
 * Replaces this module's two scaffold-leftover migrations that were
 * byte-identical copies of PageBuilder's (`create_custom_pages_table`
 * + `create_custom_page_translations_table`). Those:
 *   1. collided with PageBuilder by basename → Laravel keys migrations by
 *      basename, so only one of each pair ever loaded/ran;
 *   2. created PageBuilder's tables, NOT the tables this module's models
 *      actually use (`page_template_builders` / `page_template_categories`)
 *      — so a truly fresh deploy never created the template tables and the
 *      module would break.
 *
 * This migration creates the real tables the module uses, matching the
 * live schema. Idempotent (hasTable guards) so it is a safe no-op on every
 * already-deployed environment where the tables exist. `custom_page_translations`
 * stays owned by PageBuilder (shared) — not recreated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_template_categories')) {
            Schema::create('page_template_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->integer('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('page_template_builders')) {
            Schema::create('page_template_builders', function (Blueprint $table) {
                $table->id();
                $table->integer('category')->nullable();
                $table->string('template_name', 100)->nullable();
                $table->text('file')->nullable();
                $table->text('image')->nullable();
                $table->integer('status')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: these tables predate this migration
        // on existing systems and hold live template data. Drop manually if
        // a true teardown is required.
    }
};
