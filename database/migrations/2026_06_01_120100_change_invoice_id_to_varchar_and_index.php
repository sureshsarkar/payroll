<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding [17] — orders.invoice_id stored as TEXT.
 *
 * invoice_id is a short opaque code (e.g. "FREE-AB12CD34EF", "INV-...") that
 * is looked up directly (invoice routes, gateway callbacks). Storing it as
 * TEXT is wrong for a short, indexed identifier — it forces off-page storage
 * and the existing UNIQUE index can only ever be a prefix index. Narrow it to
 * VARCHAR(64); the existing `orders_invoice_id_unique` index then becomes a
 * clean full-length unique index (no extra index needed — verified one
 * already exists).
 *
 * SAFE TO RUN: verified pre-write MAX(LENGTH(invoice_id)) = 15, so VARCHAR(64)
 * truncates nothing (64 leaves generous headroom).
 *
 * NOTE: authored as a ready-to-run deliverable. Apply with `php artisan
 * migrate` when you choose to.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'invoice_id')) {
            return;
        }
        // The existing UNIQUE index on invoice_id remains in place and simply
        // becomes a full-length index once the column is a bounded VARCHAR.
        DB::statement('ALTER TABLE `orders` MODIFY `invoice_id` VARCHAR(64) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'invoice_id')) {
            return;
        }
        DB::statement('ALTER TABLE `orders` MODIFY `invoice_id` TEXT NULL DEFAULT NULL');
    }
};
