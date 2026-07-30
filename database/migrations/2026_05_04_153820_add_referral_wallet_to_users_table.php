<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a SEPARATE wallet column for referral rewards. Distinct from
 * users.wallet_balance which is the cash/withdrawable balance for coach
 * commission earnings.
 *
 * Business rule: this balance can ONLY be redeemed against membership
 * purchases. It is NEVER withdrawable. Code that touches users.wallet_balance
 * (Modules\PaymentWithdraw, the affiliate Pay action) does NOT touch this
 * column — the two are intentionally siloed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('referral_wallet_balance', 12, 2)->default(0)->after('wallet_balance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_wallet_balance');
        });
    }
};
