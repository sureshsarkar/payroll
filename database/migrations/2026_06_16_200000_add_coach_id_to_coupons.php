<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-16 (audit M2) — per-coach coupons.
 *
 * Adds a nullable `coach_id` to `coupons`. NULL = platform-global coupon
 * (admin-created; applies everywhere — existing behaviour, unchanged). A set
 * coach_id = a coupon that ONLY applies on that coach's white-label surface, so
 * Coach A's coupon can never be redeemed on Coach B's checkout.
 *
 * Guarded/idempotent so it is safe to run against prod where the column may or
 * may not already exist. FK is SET NULL so deleting a coach degrades the coupon
 * to a (now orphan) platform coupon rather than cascading away financial config.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        if (! Schema::hasColumn('coupons', 'coach_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->unsignedBigInteger('coach_id')->nullable()->after('author_id');
                $table->index('coach_id', 'coupons_coach_id_idx');
            });
        }

        // FK added separately + guarded — users.id is bigint unsigned. Wrapped so
        // a pre-existing constraint or a non-InnoDB edge case never aborts deploy.
        try {
            Schema::table('coupons', function (Blueprint $table) {
                $table->foreign('coach_id', 'coupons_coach_id_fk')
                    ->references('id')->on('users')
                    ->onDelete('set null');
            });
        } catch (\Throwable $e) {
            // constraint already present or environment doesn't support it — safe to skip
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('coupons') || ! Schema::hasColumn('coupons', 'coach_id')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            try {
                $table->dropForeign('coupons_coach_id_fk');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('coupons_coach_id_idx');
            } catch (\Throwable $e) {
            }
            $table->dropColumn('coach_id');
        });
    }
};
