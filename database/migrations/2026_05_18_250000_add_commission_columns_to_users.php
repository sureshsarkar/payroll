<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05-18 Req 3 — per-coach commission override.
 *
 * Adds:
 *   - users.commission_rate    (DECIMAL(5,2), nullable). When NOT NULL,
 *                              overrides the global setting at checkout
 *                              for orders against this coach's courses.
 *                              NULL = use global setting (legacy behavior).
 *   - users.commission_mode    (enum 'temporary'|'permanent'|'free',
 *                              nullable). Admin-facing label for what kind
 *                              of override this is — no runtime impact
 *                              beyond reporting / display.
 *                                 * temporary → time-limited concession
 *                                 * permanent → ongoing override
 *                                 * free      → 0% (set commission_rate=0)
 *   - users.commission_note    (varchar 255, nullable). Admin's free-form
 *                              note: "Promo through Q1 2026", "Founding
 *                              coach", etc.
 *
 * Storage decision: per-coach lives on the coach's own users row, not
 * a separate table. Only one rate per coach is meaningful at a time;
 * history can be reconstructed from orders.commission_rate snapshots.
 *
 * Backwards-compatible: NULL on all existing coach rows preserves the
 * pre-migration behavior (global setting used).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) return;
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->nullable()->after('wallet_balance');
            }
            if (!Schema::hasColumn('users', 'commission_mode')) {
                $table->enum('commission_mode', ['temporary', 'permanent', 'free'])
                      ->nullable()
                      ->after('commission_rate');
            }
            if (!Schema::hasColumn('users', 'commission_note')) {
                $table->string('commission_note', 255)->nullable()->after('commission_mode');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) return;
        Schema::table('users', function (Blueprint $table) {
            foreach (['commission_note', 'commission_mode', 'commission_rate'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
