<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offline fee payments — richer capture (2026-07-10).
 *
 * The coach can already record a manual/offline payment against a demand
 * (FeeManagementController::recordPayment). This adds the two fields that
 * complete the offline flow:
 *   - reference_no : cheque no. / UPI txn id / bank ref (distinct from the
 *     free-text note and from gateway_txn_id which is for gateway payments).
 *   - 'upi' as a gateway/method value (superset of the existing enum — no
 *     data loss).
 *
 * Idempotent (hasColumn guard + enum MODIFY is a no-op when already applied).
 * PROD does NOT auto-migrate — safe to run standalone / re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_payments')) {
            return;
        }

        if (! Schema::hasColumn('fee_payments', 'reference_no')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                $table->string('reference_no', 128)->nullable()->after('gateway_txn_id');
            });
        }

        // Extend the method enum with 'upi'. The new set is a superset of the
        // old one, so existing rows stay valid. Wrapped for non-MySQL drivers
        // (e.g. sqlite in some test setups) where enum is a plain string and
        // 'upi' already works without an ALTER.
        try {
            DB::statement(
                "ALTER TABLE fee_payments MODIFY COLUMN gateway "
                . "ENUM('razorpay','stripe','manual','cash','cheque','bank_transfer','upi','other') "
                . "NOT NULL DEFAULT 'manual'"
            );
        } catch (\Throwable $e) {
            // ignore — driver without native enum
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fee_payments')) {
            return;
        }
        if (Schema::hasColumn('fee_payments', 'reference_no')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                $table->dropColumn('reference_no');
            });
        }
        try {
            DB::statement(
                "ALTER TABLE fee_payments MODIFY COLUMN gateway "
                . "ENUM('razorpay','stripe','manual','cash','cheque','bank_transfer','other') "
                . "NOT NULL DEFAULT 'manual'"
            );
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
