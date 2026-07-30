<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-10 (Staff Panel changes doc — Orders access).
 *
 * The Orders module controller was gated on the non-existent slug 'coach-sells'
 * (now corrected to 'coach-orders'). The read/update slugs exist in the catalog,
 * but 'coach-orders-create' was missing, so a staff member could never be
 * granted permission to create an order. Add it to the catalog on EXISTING DBs.
 * Idempotent — inserts only if absent; adds it to no role (deny by default).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_staff_permissions')) {
            return;
        }
        if (! DB::table('coach_staff_permissions')->where('slug', 'coach-orders-create')->exists()) {
            DB::table('coach_staff_permissions')->insert([
                'name' => 'Create Order', 'slug' => 'coach-orders-create',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Append-only catalog — no-op (removing could orphan a live assignment).
    }
};
