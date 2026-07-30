<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-09 — Advanced enterprise certificate options.
 *   certificate_template — which enterprise DESIGN to render: 'classic'
 *     (framed + sealed), 'modern' (minimal accent band) or 'royal' (ornate
 *     laurel). Default 'classic'. (Distinct from certificate_style, which picks
 *     enterprise vs the legacy drag template.)
 *   accent_color — optional hex that overrides the coach brand colour on the
 *     certificate only (nullable ⇒ use the brand colour).
 * Idempotent for hand-apply on PROD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_builders')) {
            return;
        }
        Schema::table('certificate_builders', function (Blueprint $t) {
            if (! Schema::hasColumn('certificate_builders', 'certificate_template')) {
                $t->string('certificate_template', 20)->default('classic')->after('certificate_style');
            }
            if (! Schema::hasColumn('certificate_builders', 'accent_color')) {
                $t->string('accent_color', 20)->nullable()->after('certificate_template');
            }
        });
    }

    public function down(): void
    {
        // Additive; leave in place on rollback.
    }
};
