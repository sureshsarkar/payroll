<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 (Trainer feature, Phase 5 — align to the coach's design doc).
 *
 * The public Trainer Detail Page shows a "COACH DETAIL" block with a certificate
 * issue date + number, and the "Book Personal Class Session" popup carries the
 * fuller taxonomy (Plan Type / Course Type / Reason) — coach-editable per trainer
 * so it stays white-label (no hardcoded option belongs to one coach). Additive +
 * idempotent; existing trainers keep working (empty config falls back to sane
 * defaults in the model).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_trainers')) {
            return;
        }

        Schema::table('coach_trainers', function (Blueprint $table) {
            if (! Schema::hasColumn('coach_trainers', 'certificate_date')) {
                $table->string('certificate_date', 60)->nullable()->after('experience');
            }
            if (! Schema::hasColumn('coach_trainers', 'certificate_number')) {
                $table->string('certificate_number', 100)->nullable()->after('certificate_date');
            }
            if (! Schema::hasColumn('coach_trainers', 'plan_types')) {
                $table->json('plan_types')->nullable()->after('tags');
            }
            if (! Schema::hasColumn('coach_trainers', 'course_types')) {
                $table->json('course_types')->nullable()->after('plan_types');
            }
            if (! Schema::hasColumn('coach_trainers', 'reasons')) {
                $table->json('reasons')->nullable()->after('course_types');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coach_trainers')) {
            return;
        }

        Schema::table('coach_trainers', function (Blueprint $table) {
            foreach (['certificate_date', 'certificate_number', 'plan_types', 'course_types', 'reasons'] as $col) {
                if (Schema::hasColumn('coach_trainers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
