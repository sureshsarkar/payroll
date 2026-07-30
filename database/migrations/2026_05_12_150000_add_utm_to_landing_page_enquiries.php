<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 of the multi-template business website system (2026-05-12) —
 * preserve UTM/campaign tags from the referrer URL onto every captured
 * enquiry so the CRM can answer "which campaign brought this lead?"
 *
 * UTM tags are added on the public form submission by reading the
 * referer header's query string. Width is 100 chars each — UTM
 * specifications are unbounded but real-world values rarely exceed
 * 60 chars; 100 leaves slack without being wasteful.
 *
 * All three columns are nullable so:
 *   (a) pre-existing rows keep working untouched
 *   (b) direct-traffic visitors (no UTM) save NULL rather than empty
 *       string, so SELECTs filtering by NOT NULL accurately report
 *       campaign-attributed leads only.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('landing_page_enquiries')) {
            return;
        }
        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_page_enquiries', 'utm_source')) {
                $table->string('utm_source', 100)->nullable()->after('source_url');
            }
            if (!Schema::hasColumn('landing_page_enquiries', 'utm_medium')) {
                $table->string('utm_medium', 100)->nullable()->after('utm_source');
            }
            if (!Schema::hasColumn('landing_page_enquiries', 'utm_campaign')) {
                $table->string('utm_campaign', 100)->nullable()->after('utm_medium');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('landing_page_enquiries')) {
            return;
        }
        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $col) {
                if (Schema::hasColumn('landing_page_enquiries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
