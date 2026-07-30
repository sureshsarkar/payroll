<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-17 — REMOVE Laravel Telescope permanently.
 *
 * Telescope was shipped to production (installed with dev deps + provider
 * registered) and recorded an entry on every request/query/cache hit, ballooning
 * telescope_entries to tens of thousands of rows. Combined with the DB cache
 * driver this exhausted MySQL connections ("Too many connections") and took the
 * whole site down. Telescope is now fully removed from the app (provider
 * unregistered, package dont-discovered, files deleted). This migration drops
 * its tables so they stop consuming space and can never be written to again.
 *
 * Child table (telescope_entries_tags) is dropped first to satisfy the FK, with
 * checks disabled as a belt-and-suspenders. Guarded — safe if already absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            Schema::dropIfExists('telescope_entries_tags');
            Schema::dropIfExists('telescope_entries');
            Schema::dropIfExists('telescope_monitoring');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Intentionally NOT recreated — Telescope has been removed from the
        // project. Reinstalling Telescope (dev-only) would recreate them.
    }
};
