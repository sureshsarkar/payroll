<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-09 (Email/Notification audit — Phase 7, copy polish).
 *
 * Fixes grammar / tone / gendered-salutation issues in the seeded legacy email
 * templates on EXISTING databases (the seeder is fixed separately for fresh
 * installs). All operations are idempotent (fixed-value UPDATE or REPLACE that
 * no-ops when the phrase is absent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        // ── Full-body rewrites (robotic → professional) ──────────────────
        $bodies = [
            'user_verification' => '<p>Dear {{user_name}},</p>
                <p>Welcome! Your account has been created successfully. Please click the button below to activate your account.</p>',

            'password_reset' => '<p>Dear {{user_name}},</p>
                <p>We received a request to reset your password. Click the button below to choose a new one.</p>
                <p style="color:#6b7280;font-size:13px;">This link will expire in 1 hour. If you did not request a password reset, you can safely ignore this email.</p>',

            'order_completed' => '<p>Hi {{name}},</p>
                <p>Thank you for your purchase! Your order has been placed successfully.</p>
                <p><strong>Invoice ID:</strong> {{order_id}}</p>
                <p><strong>Amount paid:</strong> {{paid_amount}}</p>
                <p><strong>Payment method:</strong> {{payment_method}}</p>',

            'payment_status' => '<p>Hi {{name}},</p>
                <p>Here is an update on your order.</p>
                <p><strong>Invoice ID:</strong> {{order_id}}</p>
                <p><strong>Amount paid:</strong> {{paid_amount}}</p>
                <p><strong>Payment status:</strong> {{payment_status}}</p>',

            'new_refund' => '<p>Hello admin,</p>
                <p>{{user_name}} has submitted a new refund request. Please review it in the admin panel.</p>',

            'pending_wallet_payment' => '<p>Hello {{user_name}},</p>
                <p>We have received your wallet payment request and will verify it against our bank account shortly.</p>
                <p>Thanks &amp; regards</p>',
        ];

        foreach ($bodies as $name => $message) {
            DB::table('email_templates')->where('name', $name)->update(['message' => $message]);
        }

        // ── Targeted phrase fixes (leave the rest of the body intact) ────
        // De-gender the contact-form template.
        DB::table('email_templates')->where('name', 'contact_mail')
            ->update(['message' => DB::raw("REPLACE(message, 'Mr. {{name}}', '{{name}}')")]);

        // Neutralise the niche combat-sport sign-off shipped as the generic
        // trial-booking default.
        DB::table('email_templates')->where('name', 'notif_trial_booking_to_student')
            ->update(['message' => DB::raw("REPLACE(message, 'See you on the mat!', 'We look forward to seeing you.')")]);
    }

    public function down(): void
    {
        // Not reversible (we don't restore the old grammar/tone).
    }
};
