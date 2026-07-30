<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F15 (audit 2026-06-26) — wallet-credit idempotency marker. Stamped when an
 * order's coach payout is credited (online markPaid OR admin bank/offline
 * approval), cleared on reversal. Lets the credit/debit paths refuse to fire
 * twice on a paid → cancelled → paid toggle (the old code re-credited the wallet
 * on every pending→paid edge with no guard). Idempotent + additive.
 *
 * Backfill: existing PAID orders are stamped (their wallet was already credited
 * under the old flow) so a future toggle on them won't double-credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'wallet_settled_at')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('wallet_settled_at')->nullable()->after('payment_status');
        });

        // Backfill already-paid orders so they're treated as already-credited.
        try {
            \Illuminate\Support\Facades\DB::table('orders')
                ->where('payment_status', 'paid')
                ->whereNull('wallet_settled_at')
                ->update(['wallet_settled_at' => \Illuminate\Support\Facades\DB::raw('COALESCE(updated_at, NOW())')]);
        } catch (\Throwable $e) {
            // backfill is best-effort; the guard still works going forward.
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'wallet_settled_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('wallet_settled_at');
            });
        }
    }
};
