<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New Changes for UI #8.2 — richer audit for lead emails.
 *
 * The enquiry "email the lead" feature now sends through the coach's own
 * SMTP (when configured + valid) or the platform default. Record two extra
 * fields on each email_sends row so the audit log can answer "which tenant?"
 * and "which SMTP server?" without exposing any credentials.
 *
 * Idempotent (guarded by hasColumn) — production does NOT auto-migrate, so
 * this must be safe to run standalone and re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_sends')) {
            return;
        }
        Schema::table('email_sends', function (Blueprint $table) {
            if (! Schema::hasColumn('email_sends', 'coach_id')) {
                $table->unsignedBigInteger('coach_id')->nullable()->after('enquiry_id')->index();
            }
            if (! Schema::hasColumn('email_sends', 'smtp_source')) {
                // 'coach' = coach's own SMTP, 'mbsguru' = platform default.
                $table->string('smtp_source', 20)->nullable()->after('body');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_sends')) {
            return;
        }
        Schema::table('email_sends', function (Blueprint $table) {
            if (Schema::hasColumn('email_sends', 'coach_id')) {
                $table->dropColumn('coach_id');
            }
            if (Schema::hasColumn('email_sends', 'smtp_source')) {
                $table->dropColumn('smtp_source');
            }
        });
    }
};
