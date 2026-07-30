<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22 — CRM follow-up reminders. Tracks when the due-follow-up reminder
 * was last sent for an enquiry so the cron reminds once per scheduled
 * follow_up_at (and again if the coach reschedules to a later date).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('landing_page_enquiries')) {
            return;
        }
        if (Schema::hasColumn('landing_page_enquiries', 'follow_up_reminded_at')) {
            return;
        }
        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            $table->timestamp('follow_up_reminded_at')->nullable()->after('follow_up_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('landing_page_enquiries')
            && Schema::hasColumn('landing_page_enquiries', 'follow_up_reminded_at')) {
            Schema::table('landing_page_enquiries', function (Blueprint $table) {
                $table->dropColumn('follow_up_reminded_at');
            });
        }
    }
};
