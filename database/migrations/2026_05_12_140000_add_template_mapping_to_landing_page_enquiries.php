<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 of the multi-template business website system (2026-05-12) —
 * tie each landing-page enquiry back to the page + template + business
 * vertical it came from. Without this, a coach with 3 published landing
 * pages can't tell which one a lead saw — they only know "the coach this
 * lead is for" (already on the row).
 *
 * Columns added (all nullable so existing rows keep working untouched):
 *   - landing_page_id    → coach_landing_pages.id of the page that
 *                          rendered the form. Lets the CRM link straight
 *                          back to the published landing page.
 *   - template_id        → page_template_builders.id of the template the
 *                          coach was using when the lead came in. Powers
 *                          per-template ROI breakdown ("which template
 *                          converts best?").
 *   - business_category  → denormalized category name (e.g. "Yoga", "Gym")
 *                          captured at submission time. Lets the CRM
 *                          filter by vertical without joining 3 tables.
 *   - source_url         → full referer URL captured at submission.
 *                          Useful when the lead came from a UTM-tagged
 *                          campaign or an embedded form on another site.
 *
 * The pre-existing `source` column (Tier-2 CRM, 2026-05-12 100000) is
 * preserved and continues to hold the high-level bucket
 * ("landing_page" / "service_form" / "manual" / "import") for filtering.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('landing_page_enquiries')) {
            return;
        }
        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_page_enquiries', 'landing_page_id')) {
                $table->unsignedBigInteger('landing_page_id')->nullable()->after('coach_id');
            }
            if (!Schema::hasColumn('landing_page_enquiries', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable()->after('landing_page_id');
            }
            if (!Schema::hasColumn('landing_page_enquiries', 'business_category')) {
                $table->string('business_category', 100)->nullable()->after('template_id');
            }
            if (!Schema::hasColumn('landing_page_enquiries', 'source_url')) {
                $table->string('source_url', 500)->nullable()->after('source');
            }
        });

        // Indexes for the two filters the CRM will use most often.
        // - (coach_id, business_category) for "Yoga leads only" dashboards
        // - (coach_id, template_id) for "which template performs best" reports
        // CREATE INDEX IF NOT EXISTS is MySQL 8+ / MariaDB 10.5+; project runs
        // on MariaDB 10.4 which doesn't support IF NOT EXISTS on indexes, so
        // we guard with a SHOW INDEX check instead.
        $hasCat = collect(DB::select("SHOW INDEX FROM landing_page_enquiries WHERE Key_name = 'lpe_coach_category_idx'"))->isNotEmpty();
        if (!$hasCat) {
            DB::statement('CREATE INDEX lpe_coach_category_idx ON landing_page_enquiries (coach_id, business_category)');
        }
        $hasTpl = collect(DB::select("SHOW INDEX FROM landing_page_enquiries WHERE Key_name = 'lpe_coach_template_idx'"))->isNotEmpty();
        if (!$hasTpl) {
            DB::statement('CREATE INDEX lpe_coach_template_idx ON landing_page_enquiries (coach_id, template_id)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('landing_page_enquiries')) {
            return;
        }
        foreach (['lpe_coach_category_idx', 'lpe_coach_template_idx'] as $idx) {
            $exists = collect(DB::select("SHOW INDEX FROM landing_page_enquiries WHERE Key_name = '{$idx}'"))->isNotEmpty();
            if ($exists) {
                DB::statement("DROP INDEX {$idx} ON landing_page_enquiries");
            }
        }
        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            foreach (['landing_page_id', 'template_id', 'business_category', 'source_url'] as $col) {
                if (Schema::hasColumn('landing_page_enquiries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
