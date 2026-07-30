<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 — Temporary (date-specific) batch slots. A student keeps their
 * PRIMARY batch (enrollments.batch_id, untouched) but attends a DIFFERENT batch
 * of the same course on a specific date. This ledger drives that: for the slot
 * date the student appears as a "guest" on the target batch's roster and "away"
 * on their primary. Tenant-scoped by coach_id. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_temporary_slots')) {
            return;
        }

        Schema::create('student_temporary_slots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('coach_id')->index();      // tenant gate
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('primary_batch_id')->nullable();  // where they normally are
            $table->unsignedBigInteger('target_batch_id');               // where they attend on slot_date
            $table->date('slot_date');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('scheduled');          // scheduled | cancelled
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['coach_id', 'slot_date']);
            $table->index(['target_batch_id', 'slot_date']);
            $table->index(['primary_batch_id', 'slot_date']);
            $table->index(['student_id', 'slot_date']);
            // One active slot per student per target batch per date.
            $table->unique(['student_id', 'target_batch_id', 'slot_date'], 'temp_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_temporary_slots');
    }
};
