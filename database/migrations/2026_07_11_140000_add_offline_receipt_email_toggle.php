<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline Payment — email the branded receipt to the student (2026-07-11).
 *
 * Per-coach toggle `offline_payment_email_receipt` — when a recorded payment
 * takes effect, the student is emailed the coach-branded receipt via the coach's
 * own SMTP (else the platform default). Default ON. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_brand_settings')
            && ! Schema::hasColumn('coach_brand_settings', 'offline_payment_email_receipt')) {
            Schema::table('coach_brand_settings', function (Blueprint $table) {
                $table->boolean('offline_payment_email_receipt')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coach_brand_settings')
            && Schema::hasColumn('coach_brand_settings', 'offline_payment_email_receipt')) {
            Schema::table('coach_brand_settings', function (Blueprint $table) {
                $table->dropColumn('offline_payment_email_receipt');
            });
        }
    }
};
