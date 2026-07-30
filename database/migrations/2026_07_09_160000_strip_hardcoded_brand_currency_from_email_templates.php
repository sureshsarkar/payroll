<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-09 (Email/Notification audit — Phase 2.4).
 *
 * White-label leak in seeded legacy templates:
 *  - approved/rejected/pending_withdraw signed "<p>MBS Guru</p>" — a coach's
 *    payout email should never be signed with the platform name.
 *  - approved_refund hardcoded "USD" — wrong for non-USD tenants.
 *
 * Strips both from any EXISTING rows (the seeder is fixed separately for fresh
 * installs). Idempotent: REPLACE is a no-op when the string is absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        // Remove the hardcoded platform signature from coach payout emails.
        foreach (['approved_withdraw', 'rejected_withdraw', 'pending_withdraw'] as $name) {
            DB::table('email_templates')
                ->where('name', $name)
                ->update(['message' => DB::raw("REPLACE(message, '<p>MBS Guru</p>', '')")]);
        }

        // Drop the hardcoded currency code (and fix the grammar) on the refund email.
        DB::table('email_templates')
            ->where('name', 'approved_refund')
            ->update(['message' => DB::raw("REPLACE(message, 'we have send {{refund_amount}} USD', 'we have sent {{refund_amount}}')")]);
    }

    public function down(): void
    {
        // Not reversible (we don't re-introduce hardcoded branding/currency).
    }
};
