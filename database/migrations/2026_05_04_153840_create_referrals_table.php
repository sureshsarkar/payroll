<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the full lifecycle of a single referral relationship — separate from
 * the existing referral_commissions table (which is the order-commission
 * stream that pays into the cash wallet).
 *
 * One row is written when a new user registers via someone's referral code.
 * Status transitions:
 *   pending  → user registered, hasn't activated membership yet
 *   rewarded → referrer received reward (membership activated)
 *   rejected → admin manually rejected (suspicious / self-referral)
 *   reversed → reward was clawed back after the fact
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_user_id'); // who shared the link
            $table->unsignedBigInteger('referred_user_id'); // who signed up
            $table->string('referred_role', 30);            // student / instructor at signup
            $table->string('referral_code', 30);            // snapshot — useful for reports

            $table->decimal('reward_amount', 10, 2)->default(0); // populated when rewarded
            $table->enum('status', ['pending', 'rewarded', 'rejected', 'reversed'])->default('pending');

            $table->timestamp('rewarded_at')->nullable();
            $table->unsignedBigInteger('membership_id')->nullable(); // user_memberships row that triggered the reward
            $table->string('rejection_reason', 500)->nullable();

            $table->timestamps();

            $table->unique('referred_user_id'); // one referral relationship per signup
            $table->index(['referrer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
