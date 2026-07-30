<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id');

            // Lifecycle dates. started_at is when payment cleared; expires_at is
            // computed at activation as started_at + plan.duration_days (or null
            // when duration_days = 0, i.e. lifetime).
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Payment + status
            $table->decimal('price_paid', 10, 2)->default(0);          // cash portion
            $table->decimal('wallet_credit_used', 10, 2)->default(0);  // referral wallet portion
            $table->string('payment_method', 50)->default('manual');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_id', 191)->nullable();

            // active: paid + within window  | expired: past expires_at  | cancelled: admin/user cancelled
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_memberships');
    }
};
