<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teacher-Batch Assignment — Phase 1 (foundation).
 *
 * Coach assigns one of their CoachStaff members ("teacher") to one
 * or more of their CourseBatch records. Each row is a permission
 * grant: while status='active' the teacher can view + manage live
 * classes for that batch; status='inactive' is a soft-delete that
 * immediately revokes access without losing the audit trail.
 *
 * Ownership invariant — enforced at the controller layer, not by
 * SQL constraint:
 *   - coach_id   = the assigning coach (users.role='instructor')
 *   - teacher_id = a CoachStaff whose users.coach_id == coach_id
 *   - course_id  = a course whose instructor_id == coach_id
 *   - batch_id   = a course_batch whose course_id == course_id
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teacher_batch_assignments')) {
            return;
        }

        Schema::create('teacher_batch_assignments', function (Blueprint $table) {
            $table->id();

            // The coach who created the assignment.
            $table->unsignedBigInteger('coach_id');
            $table->foreign('coach_id')->references('id')->on('users')
                  ->onDelete('cascade');

            // The CoachStaff member receiving the assignment.
            $table->unsignedBigInteger('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('users')
                  ->onDelete('cascade');

            // Course + batch the teacher is being granted access to.
            // course_id stored for query convenience (list assignments
            // grouped by course) and to validate the (course, batch)
            // pair belongs together at the controller layer.
            $table->unsignedBigInteger('course_id');
            $table->foreign('course_id')->references('id')->on('courses')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('batch_id');
            $table->foreign('batch_id')->references('id')->on('course_batches')
                  ->onDelete('cascade');

            // Granular permission. Today only 'manage' (full CRUD on
            // live classes for the batch). Reserved for future values
            // like 'view_only' or 'host_only' — keep the column so we
            // don't need another migration to add semantics later.
            $table->string('permission_type', 32)->default('manage');

            // active = grant is live; inactive = soft-removed (blocks
            // access immediately but row stays for audit).
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Distinct from created_at so a re-activated assignment
            // can re-stamp when it was last (re)assigned without
            // disturbing the original creation timestamp.
            $table->timestamp('assigned_at')->nullable();

            $table->timestamps();

            // No duplicate (coach, teacher, batch) — coach can't grant
            // the same batch twice to the same teacher. Re-granting
            // after a soft-remove must reuse the row (flip status back
            // to active) rather than insert.
            $table->unique(['coach_id', 'teacher_id', 'batch_id'], 'tba_unique_coach_teacher_batch');

            // Hot read paths:
            //   - teacher's assigned batch IDs (gate every request)
            //   - coach's assignments list (admin UI)
            //   - revoke-on-batch-delete cascade follows the FK
            $table->index(['teacher_id', 'status'], 'tba_teacher_status_idx');
            $table->index(['coach_id', 'status'], 'tba_coach_status_idx');
            $table->index('batch_id', 'tba_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_batch_assignments');
    }
};
