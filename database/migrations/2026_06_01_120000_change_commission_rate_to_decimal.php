<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding [9] — commission_rate stored as INT.
 *
 * orders.commission_rate and order_items.commission_rate are int(11), but a
 * commission rate is a percentage that frequently needs fractional precision
 * (e.g. 12.5%, 7.25%). Storing it as INT silently truncates any fractional
 * coach commission. Widen both to DECIMAL(5,2) (0.00–999.99 — far more than
 * the 0–100 a percentage needs).
 *
 * SAFE TO RUN: existing integer values cast losslessly (2 -> 2.00, 0 -> 0.00,
 * NULL stays NULL). Verified pre-write: only {0, 2, NULL} present.
 *
 * NOTE: this file is authored as a ready-to-run deliverable and has NOT been
 * executed. Apply with `php artisan migrate` when you choose to.
 *
 * Raw ALTER is used (instead of ->change()) so it needs no doctrine/dbal and
 * the exact column definition is explicit and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'commission_rate')) {
            DB::statement('ALTER TABLE `orders` MODIFY `commission_rate` DECIMAL(5,2) NULL DEFAULT NULL');
        }
        if (Schema::hasColumn('order_items', 'commission_rate')) {
            DB::statement('ALTER TABLE `order_items` MODIFY `commission_rate` DECIMAL(5,2) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        // Revert to the original int(11). Any fractional values created while
        // the decimal column was live will truncate on the way back down.
        if (Schema::hasColumn('order_items', 'commission_rate')) {
            DB::statement('ALTER TABLE `order_items` MODIFY `commission_rate` INT(11) NULL DEFAULT NULL');
        }
        if (Schema::hasColumn('orders', 'commission_rate')) {
            DB::statement('ALTER TABLE `orders` MODIFY `commission_rate` INT(11) NULL DEFAULT NULL');
        }
    }
};
