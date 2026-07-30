<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-03 (Referral A+) — bring referral_commissions onto the corporate
 * status lifecycle the audit defined:
 *   pending → eligible → approved → credited → rejected → reversed
 *
 * Additive + a safe value remap (no data loss):
 *   - legacy 'paid'    → 'credited'
 *   - legacy 'pending' → 'eligible'  (a commission row only ever exists AFTER
 *                                     a paid order, so it is already eligible)
 * New per-stage timestamps + a reversal reason are added for auditing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('referral_commissions', 'eligible_at')) {
                $table->timestamp('eligible_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('referral_commissions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('eligible_at');
            }
            if (!Schema::hasColumn('referral_commissions', 'credited_at')) {
                $table->timestamp('credited_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('referral_commissions', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('credited_at');
            }
            if (!Schema::hasColumn('referral_commissions', 'reversal_reason')) {
                $table->string('reversal_reason', 500)->nullable()->after('reversed_at');
            }
        });

        // Widen the enum to a superset (old + new), remap legacy values, then
        // settle on the final lifecycle set. Raw SQL because the column is an
        // ENUM (doctrine/dbal can't alter enums reliably).
        DB::statement("ALTER TABLE referral_commissions MODIFY status
            ENUM('pending','eligible','approved','credited','paid','rejected','reversed')
            NOT NULL DEFAULT 'eligible'");

        DB::table('referral_commissions')->where('status', 'paid')->update([
            'status'      => 'credited',
            'credited_at' => DB::raw('COALESCE(credited_at, paid_at, updated_at)'),
        ]);
        DB::table('referral_commissions')->where('status', 'pending')->update([
            'status'      => 'eligible',
            'eligible_at' => DB::raw('COALESCE(eligible_at, created_at)'),
        ]);

        DB::statement("ALTER TABLE referral_commissions MODIFY status
            ENUM('eligible','approved','credited','rejected','reversed')
            NOT NULL DEFAULT 'eligible'");
    }

    public function down(): void
    {
        // Reverse the enum first (map new values back to the legacy set).
        DB::statement("ALTER TABLE referral_commissions MODIFY status
            ENUM('pending','eligible','approved','credited','paid','rejected','reversed')
            NOT NULL DEFAULT 'pending'");

        DB::table('referral_commissions')->where('status', 'credited')->update(['status' => 'paid']);
        DB::table('referral_commissions')->whereIn('status', ['eligible', 'rejected'])->update(['status' => 'pending']);

        DB::statement("ALTER TABLE referral_commissions MODIFY status
            ENUM('pending','approved','paid','reversed')
            NOT NULL DEFAULT 'pending'");

        Schema::table('referral_commissions', function (Blueprint $table) {
            foreach (['eligible_at', 'approved_at', 'credited_at', 'reversed_at', 'reversal_reason'] as $col) {
                if (Schema::hasColumn('referral_commissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
