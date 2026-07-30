<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-16 — add Pinterest to the coach-site footer social links. Matches the
 * existing social_* columns (nullable string 500). Additive + idempotent
 * (hasColumn guard); nullable so every existing coach site is unaffected and
 * Pinterest stays hidden in the footer until a coach enters a URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_site_settings')) {
            return;
        }
        if (! Schema::hasColumn('coach_site_settings', 'social_pinterest')) {
            Schema::table('coach_site_settings', function (Blueprint $table) {
                $table->string('social_pinterest', 500)->nullable()->after('social_tiktok');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coach_site_settings') && Schema::hasColumn('coach_site_settings', 'social_pinterest')) {
            Schema::table('coach_site_settings', function (Blueprint $table) {
                $table->dropColumn('social_pinterest');
            });
        }
    }
};
