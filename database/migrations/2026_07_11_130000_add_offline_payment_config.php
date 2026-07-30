<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offline Payment — follow-ups (2026-07-11).
 *
 * FU1: per-coach ENABLED METHODS. `coach_brand_settings.offline_payment_methods`
 *      is a JSON array of the method keys a coach accepts (cash/bank_transfer/
 *      upi/cheque/other). NULL/empty = all methods (backward compatible).
 * FU2: a dedicated `offline-payments` permission slug so coaches can grant staff
 *      access to the Offline Payment surfaces independently of Orders/Trials.
 *
 * Idempotent (guards) — production does NOT auto-migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_brand_settings')
            && ! Schema::hasColumn('coach_brand_settings', 'offline_payment_methods')) {
            Schema::table('coach_brand_settings', function (Blueprint $table) {
                $table->json('offline_payment_methods')->nullable();
            });
        }

        // FU2 — seed the permission into the coach-staff catalog (idempotent).
        if (Schema::hasTable('coach_staff_permissions')) {
            $exists = DB::table('coach_staff_permissions')->where('slug', 'offline-payments')->exists();
            if (! $exists) {
                $row = ['slug' => 'offline-payments', 'name' => 'Offline Payments'];
                if (Schema::hasColumn('coach_staff_permissions', 'created_at')) {
                    $row['created_at'] = now();
                    $row['updated_at'] = now();
                }
                DB::table('coach_staff_permissions')->insert($row);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coach_brand_settings')
            && Schema::hasColumn('coach_brand_settings', 'offline_payment_methods')) {
            Schema::table('coach_brand_settings', function (Blueprint $table) {
                $table->dropColumn('offline_payment_methods');
            });
        }
        if (Schema::hasTable('coach_staff_permissions')) {
            DB::table('coach_staff_permissions')->where('slug', 'offline-payments')->delete();
        }
    }
};
