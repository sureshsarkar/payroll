<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-09 — Certificate typography & layout options (per coach).
 *   font_family — primary font: 'serif' | 'sans' | 'mono' (DomPDF-safe built-in
 *     families only, so the preview always matches the exported PDF).
 *   text_align  — 'center' (default) | 'left'.
 *   paper_color — optional hex overriding the template's default paper colour
 *     (nullable ⇒ each design keeps its own default).
 * Additive & idempotent for hand-apply on PROD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_builders')) {
            return;
        }
        Schema::table('certificate_builders', function (Blueprint $t) {
            if (! Schema::hasColumn('certificate_builders', 'font_family')) {
                $t->string('font_family', 20)->default('serif')->after('accent_color');
            }
            if (! Schema::hasColumn('certificate_builders', 'text_align')) {
                $t->string('text_align', 10)->default('center')->after('font_family');
            }
            if (! Schema::hasColumn('certificate_builders', 'paper_color')) {
                $t->string('paper_color', 20)->nullable()->after('text_align');
            }
        });
    }

    public function down(): void
    {
        // Additive; leave in place on rollback.
    }
};
