<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Performance indexes — flagged by the perf audit. Each column is hit in
 * either the admin dashboard metrics or one of the daily scheduled commands.
 */
return new class extends Migration
{
    public function up(): void
    {
        // user_memberships.payment_method — used by trial-expiring reminder
        // command (filters trial=) and admin dashboard ("trials_active" card).
        Schema::table('user_memberships', function (Blueprint $t) {
            $t->index('payment_method', 'user_memberships_payment_method_idx');
        });

        // referral_wallet_transactions.type — admin reports filter by type
        // (referral_reward / membership_checkout / etc.) when audit-tracing.
        Schema::table('referral_wallet_transactions', function (Blueprint $t) {
            $t->index('type', 'referral_wallet_transactions_type_idx');
        });

        // users.role — read on EVERY route (instructor middleware, plan
        // filters, admin dashboards, "find instructors" queries). 5-column
        // composite would be even better but a single-column suffices.
        Schema::table('users', function (Blueprint $t) {
            $t->index('role', 'users_role_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_memberships', fn (Blueprint $t) => $t->dropIndex('user_memberships_payment_method_idx'));
        Schema::table('referral_wallet_transactions', fn (Blueprint $t) => $t->dropIndex('referral_wallet_transactions_type_idx'));
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex('users_role_idx'));
    }
};
