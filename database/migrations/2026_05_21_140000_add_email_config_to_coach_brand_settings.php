<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-coach white-label — Phase 3 (per-coach email config).
 *
 * Today every coach's outbound mail uses the platform's MAIL_FROM_ADDRESS
 * and mail_sender_name. For Path B white-label, a student of Coach A
 * should receive password resets / receipts / announcements from
 * Coach A's address with Coach A's signature.
 *
 * Three configuration tiers per coach:
 *
 *   1. Use platform default                  smtp_use_default = true
 *      Most coaches. Email goes via the platform's MAIL_HOST but
 *      with the coach's mail_from_address / mail_from_name spliced
 *      into the message (override headers, keep transport).
 *
 *   2. Override From / Reply-To only         (set address fields,
 *                                              leave smtp_host NULL)
 *      Same as tier 1.
 *
 *   3. Full SMTP override                    smtp_host non-null
 *      Coach provides their own SMTP creds. Mailer instantiates a
 *      one-off transport from these values for the duration of the
 *      send. smtp_pass is encrypted at rest via the model's cast.
 *
 * Tier 3 requires more attention (we're handling someone's
 * credentials), so the brand-settings UI puts a clear "Verify SMTP"
 * button that does a single test send before persisting.
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_brand_settings', function (Blueprint $table) {
            // Tier 2 — header overrides (most common path)
            if (! Schema::hasColumn('coach_brand_settings', 'mail_from_address')) {
                $table->string('mail_from_address', 120)->nullable()->after('email_signature');
            }
            if (! Schema::hasColumn('coach_brand_settings', 'mail_from_name')) {
                $table->string('mail_from_name', 120)->nullable()->after('mail_from_address');
            }
            if (! Schema::hasColumn('coach_brand_settings', 'mail_reply_to')) {
                $table->string('mail_reply_to', 120)->nullable()->after('mail_from_name');
            }

            // Tier 3 — full SMTP override
            if (! Schema::hasColumn('coach_brand_settings', 'smtp_host')) {
                $table->string('smtp_host', 200)->nullable()->after('mail_reply_to');
            }
            if (! Schema::hasColumn('coach_brand_settings', 'smtp_port')) {
                $table->unsignedSmallInteger('smtp_port')->nullable()->after('smtp_host');
            }
            if (! Schema::hasColumn('coach_brand_settings', 'smtp_username')) {
                $table->string('smtp_username', 200)->nullable()->after('smtp_port');
            }
            // text not string so the encrypted blob has room to grow.
            if (! Schema::hasColumn('coach_brand_settings', 'smtp_password_encrypted')) {
                $table->text('smtp_password_encrypted')->nullable()->after('smtp_username');
            }
            if (! Schema::hasColumn('coach_brand_settings', 'smtp_encryption')) {
                $table->enum('smtp_encryption', ['none', 'ssl', 'tls'])
                      ->default('tls')
                      ->after('smtp_password_encrypted');
            }

            // When we last successfully sent through coach SMTP.
            // Lets the UI show "Last verified 3 days ago" + the
            // operator catch a quietly-broken config.
            if (! Schema::hasColumn('coach_brand_settings', 'smtp_verified_at')) {
                $table->timestamp('smtp_verified_at')->nullable()->after('smtp_encryption');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coach_brand_settings', function (Blueprint $table) {
            $cols = [
                'mail_from_address', 'mail_from_name', 'mail_reply_to',
                'smtp_host', 'smtp_port', 'smtp_username',
                'smtp_password_encrypted', 'smtp_encryption',
                'smtp_verified_at',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('coach_brand_settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
