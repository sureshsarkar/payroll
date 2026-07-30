<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-23 — site-wide Typography customization for a coach's custom website.
 * Stores per-role, per-device font sizes as a single JSON blob. Idempotent so
 * it's safe to re-run on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_site_settings')
            && ! Schema::hasColumn('coach_site_settings', 'typography_config')) {
            Schema::table('coach_site_settings', function (Blueprint $table) {
                $table->json('typography_config')->nullable()->after('footer_config');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coach_site_settings')
            && Schema::hasColumn('coach_site_settings', 'typography_config')) {
            Schema::table('coach_site_settings', function (Blueprint $table) {
                $table->dropColumn('typography_config');
            });
        }
    }
};
