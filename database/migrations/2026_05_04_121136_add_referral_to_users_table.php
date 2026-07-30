<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate / referral columns on users.
 *
 * - referral_code:        unique short alphanumeric ID. Generated on first read
 *                         (see User::referralCode accessor). Used in /?ref=XXX URLs.
 * - referred_by_user_id:  who referred this user (set on registration if a
 *                         valid ref cookie was present).
 * - referred_at:          when the referral attribution was captured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // No ->after('coach_unique_id'): that column was added by a manual
            // ALTER in dev and is absent on fresh installs (CI), which made
            // the ->after() clause crash with "Column not found". Position is
            // cosmetic; columns just append to the end of the table.
            $table->string('referral_code', 20)->nullable()->unique();
            $table->unsignedBigInteger('referred_by_user_id')->nullable()->index();
            $table->timestamp('referred_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'referred_by_user_id', 'referred_at']);
        });
    }
};
