<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 — Trainer feature Phase 2. The public "Book Personal Class Session"
 * flow reuses coach_pricing_enquiries (one guest-booking table). Three additive,
 * idempotent columns discriminate + link a trainer booking without disturbing the
 * existing pricing/schedule rows:
 *   - enquiry_type       : discriminator ('trainer_personal_session'); null/absent
 *                          = the pre-existing pricing/schedule bookings.
 *   - trainer_ref_id     : real FK to coach_trainers (the string `trainer_id`
 *                          column already holds a free-text name from schedule
 *                          bookings, so we do NOT overload it).
 *   - trainer_package_id : FK to trainer_session_packages (which package was booked).
 * Readable snapshots reuse existing columns: category = trainer name,
 * time_period = package label, plan_amount = server-resolved price.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_pricing_enquiries')) {
            return;
        }

        Schema::table('coach_pricing_enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('coach_pricing_enquiries', 'enquiry_type')) {
                $table->string('enquiry_type', 40)->nullable()->index()->after('coach_id');
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'trainer_ref_id')) {
                $table->unsignedBigInteger('trainer_ref_id')->nullable()->index()->after('schedule_id');
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'trainer_package_id')) {
                $table->unsignedBigInteger('trainer_package_id')->nullable()->after('trainer_ref_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coach_pricing_enquiries')) {
            return;
        }

        Schema::table('coach_pricing_enquiries', function (Blueprint $table) {
            foreach (['enquiry_type', 'trainer_ref_id', 'trainer_package_id'] as $col) {
                if (Schema::hasColumn('coach_pricing_enquiries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
