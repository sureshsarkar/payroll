<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger for the referral wallet. Every credit (reward earned)
 * and debit (applied to membership) writes one row. The current balance lives
 * on users.referral_wallet_balance for fast reads but the ledger is the source
 * of truth for audits and reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            // Positive = credit (reward earned). Negative = debit (applied to membership).
            $table->decimal('amount', 12, 2);

            // Running balance after this entry — denormalised so reports don't
            // have to sum the entire history.
            $table->decimal('balance_after', 12, 2);

            // What kind of movement.
            // - referral_reward: someone they referred activated a membership
            // - membership_checkout: applied to a membership purchase
            // - admin_adjustment: manual admin debit/credit
            // - reversal: an earlier reward was reversed (e.g. fraud)
            $table->enum('type', [
                'referral_reward',
                'membership_checkout',
                'admin_adjustment',
                'reversal',
            ]);

            // Optional polymorphic link to the entity that caused the entry —
            // typically a UserMembership (for redemption) or a Referral (for reward).
            $table->string('related_type', 191)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_wallet_transactions');
    }
};
