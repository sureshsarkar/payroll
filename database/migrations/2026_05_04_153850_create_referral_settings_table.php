<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row settings table for the referral system. We use a table (rather
 * than the generic settings cache) so admins can bind a form directly to it,
 * and so the values survive a settings-cache wipe without re-seeding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();

            // Master kill switch. When false, no referrals are tracked, no rewards awarded.
            $table->boolean('enabled')->default(true);

            // Reward amounts vary by the referred user's role at signup.
            $table->decimal('reward_when_referred_is_student', 10, 2)->default(5.00);
            $table->decimal('reward_when_referred_is_coach',   10, 2)->default(10.00);

            // Minimum membership price (after wallet deduction) required for a
            // referral to actually award a reward. Stops $0/free plans from
            // farming rewards. 0 = no minimum.
            $table->decimal('min_membership_amount', 10, 2)->default(0);

            // Optional fraud guards.
            $table->boolean('block_self_referral')->default(true);
            $table->boolean('block_same_ip_referral')->default(false);

            // Admin can require manual approval for every reward instead of
            // auto-awarding on activation.
            $table->boolean('require_admin_approval')->default(false);

            $table->timestamps();
        });

        // Seed the single row.
        DB::table('referral_settings')->insert([
            'enabled'                          => true,
            'reward_when_referred_is_student'  => 5.00,
            'reward_when_referred_is_coach'    => 10.00,
            'min_membership_amount'            => 0,
            'block_self_referral'              => true,
            'block_same_ip_referral'           => false,
            'require_admin_approval'           => false,
            'created_at'                       => now(),
            'updated_at'                       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
