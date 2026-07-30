<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier-2 CRM extension (2026-05-12) — adds the data Tier 2 features rely on:
 *
 *   - `source`         : where the lead came from (utm_source / page slug / "manual").
 *                        Powers source-filter and per-source ROI breakdown.
 *   - `follow_up_at`   : the next-action date the coach wants to be reminded of.
 *                        Nullable; only populated when the coach sets it.
 *   - `assigned_to`    : user id of the staff member responsible (for multi-staff
 *                        coaches). Nullable; falls back to coach_id when unset.
 *   - `notes_count`    : denormalized count of LeadNote rows. Lets the index page
 *                        show a "💬 3" badge per row without an N+1 lookup.
 *
 * All four nullable / default so existing rows keep working untouched.
 * Idempotent: every column checked via Schema::hasColumn() first.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('landing_page_enquiries')) {
            return;
        }
        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_page_enquiries', 'source')) {
                $table->string('source', 120)->nullable()->after('service');
            }
            if (!Schema::hasColumn('landing_page_enquiries', 'follow_up_at')) {
                $table->timestamp('follow_up_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('landing_page_enquiries', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('coach_id');
            }
            if (!Schema::hasColumn('landing_page_enquiries', 'notes_count')) {
                $table->unsignedInteger('notes_count')->default(0)->after('message');
            }
        });

        // Index follow_up_at for the "due reminders" widget on the dashboard.
        // Without it, the widget's WHERE follow_up_at <= NOW() does a full
        // scan once enquiries grow past a few thousand rows.
        if (Schema::hasColumn('landing_page_enquiries', 'follow_up_at')) {
            \Illuminate\Support\Facades\DB::statement(
                'CREATE INDEX IF NOT EXISTS lpe_follow_up_at_idx ON landing_page_enquiries (follow_up_at)'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('landing_page_enquiries')) {
            return;
        }
        \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS lpe_follow_up_at_idx ON landing_page_enquiries');
        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            foreach (['source', 'follow_up_at', 'assigned_to', 'notes_count'] as $col) {
                if (Schema::hasColumn('landing_page_enquiries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
