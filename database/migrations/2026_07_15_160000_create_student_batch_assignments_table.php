<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-07-15 — Student batch assignment/reassignment HISTORY + AUDIT trail.
 *
 * The live membership stays on enrollments.batch_id (unchanged). This table is
 * the append-only ledger the spec requires: previous batch, new batch, who
 * changed it, when it takes effect, and the reason (mandatory on a move). Every
 * row is tenant-scoped by coach_id. Idempotent (hasTable guard) so prod re-runs
 * are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_batch_assignments')) {
            return;
        }

        Schema::create('student_batch_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('coach_id')->index();          // tenant gate
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('previous_batch_id')->nullable();
            $table->unsignedBigInteger('new_batch_id')->nullable();
            $table->string('action', 20);                            // assign | reassign | add
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('changed_by');                // acting user (coach or staff)
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();

            $table->index(['coach_id', 'student_id']);
            $table->index(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_batch_assignments');
    }
};
