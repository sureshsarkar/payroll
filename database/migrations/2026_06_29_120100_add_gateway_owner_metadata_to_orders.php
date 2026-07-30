<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-29 — Tenant-aware payment metadata on orders.
 *
 * Records WHICH gateway configuration actually processed an order so that
 * webhook + callback verification can re-resolve the correct (coach-owned or
 * global) credentials WITHOUT relying on session state.
 *
 *   gateway_owner_type : super_admin_default | super_admin_for_coach | coach_self_managed
 *   gateway_config_id  : coach_payment_gateways.id (null for the global default)
 *   gateway_coach_id   : the coach whose credentials were used (null for global default)
 *   payment_verified_at: stamped when the SYNC callback verified the payment
 *   webhook_verified_at: stamped when an async WEBHOOK verified the payment
 *
 * Additive + idempotent. Existing orders keep NULLs and behave exactly as
 * before (NULL owner_type is treated as the global default).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'gateway_owner_type')) {
                $table->string('gateway_owner_type', 32)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('orders', 'gateway_config_id')) {
                $table->unsignedBigInteger('gateway_config_id')->nullable()->after('gateway_owner_type');
            }
            if (! Schema::hasColumn('orders', 'gateway_coach_id')) {
                $table->unsignedBigInteger('gateway_coach_id')->nullable()->after('gateway_config_id');
            }
            if (! Schema::hasColumn('orders', 'payment_verified_at')) {
                $table->timestamp('payment_verified_at')->nullable()->after('transaction_id');
            }
            if (! Schema::hasColumn('orders', 'webhook_verified_at')) {
                $table->timestamp('webhook_verified_at')->nullable()->after('payment_verified_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'gateway_owner_type', 'gateway_config_id', 'gateway_coach_id',
                'payment_verified_at', 'webhook_verified_at',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
