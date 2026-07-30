<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks per-order commission credits for referrers.
     *
     *   referrer_user_id     who gets paid
     *   referred_user_id     the buyer who was referred
     *   order_id             the paid order that triggered the commission
     *   amount               commission amount in the order's currency
     *   currency             stored alongside in case of multi-currency
     *   percent              the % rate applied (snapshot for audit)
     *   status               pending | approved | paid | reversed
     *   paid_at              when admin (or auto-payout) marked it paid
     */
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_user_id')->index();
            $table->unsignedBigInteger('referred_user_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->decimal('percent', 5, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'paid', 'reversed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'referrer_user_id']); // one commission per (order, referrer)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
