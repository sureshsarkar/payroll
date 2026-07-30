<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-24 — Super-Admin-managed coach pricing plans (MBS Guru pricing doc).
 * Extends the existing membership_plans catalog with the enterprise pricing
 * fields: setup fee, per-plan platform commission, student capacity, and
 * payout / direct-settlement controls. Idempotent (each column guarded) and
 * purely additive — existing plans/subscriptions keep working (new columns
 * default to the current behaviour).
 */
return new class extends Migration
{
    private function missing(string $col): bool
    {
        return Schema::hasTable('membership_plans') && ! Schema::hasColumn('membership_plans', $col);
    }

    public function up(): void
    {
        if (! Schema::hasTable('membership_plans')) {
            return;
        }
        Schema::table('membership_plans', function (Blueprint $table) {
            if ($this->missing('tier')) {
                // Pricing tier — drives enterprise-only behaviour (custom setup,
                // direct settlement). 'custom' keeps any pre-existing plan neutral.
                $table->string('tier', 20)->default('custom')->after('slug');
            }
            if ($this->missing('setup_fee')) {
                $table->decimal('setup_fee', 10, 2)->default(0)->after('price');
            }
            if ($this->missing('setup_fee_custom')) {
                // Enterprise: setup fee is quoted per requirement (not a fixed number).
                $table->boolean('setup_fee_custom')->default(false)->after('setup_fee');
            }
            if ($this->missing('platform_commission_rate')) {
                // Per-plan platform commission %. NULL = fall back to the coach
                // override / global rate (preserves today's behaviour).
                $table->decimal('platform_commission_rate', 5, 2)->nullable()->after('setup_fee_custom');
            }
            if ($this->missing('commission_min_rate')) {
                // Enterprise commission band (e.g. 0%–1%). Informational + clamp.
                $table->decimal('commission_min_rate', 5, 2)->nullable()->after('platform_commission_rate');
            }
            if ($this->missing('commission_max_rate')) {
                $table->decimal('commission_max_rate', 5, 2)->nullable()->after('commission_min_rate');
            }
            if ($this->missing('student_capacity')) {
                // Max students the coach may take on this plan. NULL = unlimited.
                $table->unsignedInteger('student_capacity')->nullable()->after('commission_max_rate');
            }
            if ($this->missing('payout_required')) {
                // true  = coach raises payout requests (Starter/Medium default).
                // false = direct settlement, no payout request (Enterprise).
                $table->boolean('payout_required')->default(true)->after('student_capacity');
            }
            if ($this->missing('direct_settlement')) {
                // Course payments settle straight to the coach/institute bank
                // (Enterprise). Commission still auto-deducted per config.
                $table->boolean('direct_settlement')->default(false)->after('payout_required');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('membership_plans')) {
            return;
        }
        Schema::table('membership_plans', function (Blueprint $table) {
            foreach ([
                'tier', 'setup_fee', 'setup_fee_custom', 'platform_commission_rate',
                'commission_min_rate', 'commission_max_rate', 'student_capacity',
                'payout_required', 'direct_settlement',
            ] as $col) {
                if (Schema::hasColumn('membership_plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
