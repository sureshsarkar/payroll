<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-26 — the reusable Booking Enquiry modal (Class Schedule "Book Now",
 * Trainer "Book Personal Classes", and any future CTA) stores into the SAME
 * coach_pricing_enquiries table so coaches review every lead in one place
 * (Coach Panel → Pricing Enquiries). This adds the contextual metadata the
 * modal carries so the coach knows where each lead came from.
 *
 * trainer_id / schedule_id are kept as strings: schedule items live in a
 * section's JSON (no DB id) and trainers aren't a first-class entity yet, so a
 * free-form reference (name / slug / index) is the white-label-safe choice.
 * Idempotent + additive → safe to re-run on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_pricing_enquiries')) {
            return;
        }

        Schema::table('coach_pricing_enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('coach_pricing_enquiries', 'source_page')) {
                $table->string('source_page', 190)->nullable()->after('status');   // "Class Schedule" / "Trainer Detail" / path
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'source_button')) {
                $table->string('source_button', 120)->nullable()->after('source_page'); // "Book Now" / "Book Personal Classes"
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'trainer_id')) {
                $table->string('trainer_id', 150)->nullable()->after('source_button');   // trainer name / ref, if any
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'schedule_id')) {
                $table->string('schedule_id', 150)->nullable()->after('trainer_id');      // class / slot ref, if any
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('schedule_id');
            }
            if (! Schema::hasColumn('coach_pricing_enquiries', 'user_agent')) {
                $table->string('user_agent', 255)->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coach_pricing_enquiries')) {
            return;
        }
        Schema::table('coach_pricing_enquiries', function (Blueprint $table) {
            foreach (['source_page', 'source_button', 'trainer_id', 'schedule_id', 'ip_address', 'user_agent'] as $col) {
                if (Schema::hasColumn('coach_pricing_enquiries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
