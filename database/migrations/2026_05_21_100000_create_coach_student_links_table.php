<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-coach / student support — Phase 1 (schema + backfill).
 *
 * Pre-existing model: one student row, one users.added_by column,
 * one users.coach_id column. A student belonged to AT MOST one coach.
 *
 * New model: many-to-many via this pivot. Same student row can be
 * linked to N coaches. The pivot row owns:
 *   - source      where the link came from ('added' / 'purchase' /
 *                 'admin' / 'invite')
 *   - status      active | removed   (Q1: students can self-remove
 *                 — we soft-delete the link, never the student)
 *   - joined_at   when the link was established (audit trail)
 *
 * users.added_by stays as the original-adder audit field (decision
 * documented in commit body) — it's used by many other tables too,
 * so we don't migrate it away. The pivot is the new source of truth
 * for the routing question "which coach's roster is this student on".
 *
 * Backfill, in two passes inside up() so production data is hydrated
 * the moment the migration runs:
 *
 *   Pass 1 — every existing student.added_by becomes one pivot row
 *            with source='added', joined_at=user.created_at.
 *
 *   Pass 2 — every (student, course-owner-coach) pair derived from
 *            enrollments with has_access=1 becomes one pivot row
 *            with source='purchase', joined_at=enrollment.created_at.
 *            Deduped against Pass 1 via the unique index — a student
 *            who was both ADDED by Coach A AND BOUGHT a course from
 *            Coach A keeps the 'added' link (Pass 1 wins).
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_student_links')) {
            Schema::create('coach_student_links', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('coach_id');
                $table->foreign('coach_id')->references('id')->on('users')
                      ->onDelete('cascade');

                $table->unsignedBigInteger('student_id');
                $table->foreign('student_id')->references('id')->on('users')
                      ->onDelete('cascade');

                // Provenance: how this link came to exist. New values
                // may be added later (e.g. 'invite' once invitations
                // ship) but the existing four cover the M2M write
                // paths going in with Phase 2.
                $table->enum('source', ['added', 'purchase', 'admin', 'invite'])
                      ->default('added');

                // active = student is on this coach's roster; removed
                // = student self-removed (Q1). Removed rows stay for
                // audit — they're not hard-deleted.
                $table->enum('status', ['active', 'removed'])
                      ->default('active');

                $table->timestamp('joined_at')->nullable();
                $table->timestamp('removed_at')->nullable();

                $table->timestamps();

                // Coach can have a student at most once; re-link after
                // a self-removal must re-use the row (flip status to
                // active) rather than insert a duplicate.
                $table->unique(['coach_id', 'student_id'], 'csl_unique_coach_student');

                // Hot read paths:
                //   - my-students roster for one coach (filtered active)
                //   - which coaches a single student belongs to
                $table->index(['coach_id', 'status'], 'csl_coach_status_idx');
                $table->index(['student_id', 'status'], 'csl_student_status_idx');
            });
        }

        $this->backfillFromAddedBy();
        $this->backfillFromPurchases();
    }

    /**
     * Pass 1 — copy users.added_by (where role=student) into the pivot.
     * Each student's "primary coach" becomes one pivot row tagged
     * source='added'.
     */
    protected function backfillFromAddedBy(): void
    {
        $rows = DB::table('users')
            ->where('role', 'student')
            ->whereNotNull('added_by')
            ->select('id as student_id', 'added_by as coach_id', 'created_at')
            ->get();

        $now = now();
        $inserted = 0;

        foreach ($rows as $r) {
            // Skip if the supposed "coach" doesn't actually exist
            // (a few corrupt rows in legacy data have added_by pointing
            // at deleted admins). Without this, the FK insert blows up.
            $coachExists = DB::table('users')->where('id', $r->coach_id)->exists();
            if (! $coachExists) continue;

            try {
                DB::table('coach_student_links')->insertOrIgnore([
                    'coach_id'   => $r->coach_id,
                    'student_id' => $r->student_id,
                    'source'     => 'added',
                    'status'     => 'active',
                    'joined_at'  => $r->created_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                Log::warning('csl-backfill-added-by skip', [
                    'student_id' => $r->student_id,
                    'coach_id'   => $r->coach_id,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        Log::info('csl-backfill-pass1-done', ['rows_processed' => $rows->count(), 'inserted' => $inserted]);
    }

    /**
     * Pass 2 — every distinct (student, course-owner) pair from
     * enrollments with has_access=1 becomes one pivot row tagged
     * source='purchase'. Deduped against Pass 1 by the unique index
     * (insertOrIgnore silently skips collisions, so a coach who BOTH
     * added the student AND sold them a course keeps the 'added'
     * source from Pass 1).
     */
    protected function backfillFromPurchases(): void
    {
        // Single SQL: enrollments -> courses to get instructor_id.
        // We don't go through orders.seller_id because admin-issued
        // orders set seller_id to the admin's user — the course's
        // instructor_id is the actual coach to link.
        $rows = DB::table('enrollments as e')
            ->join('courses as c', 'c.id', '=', 'e.course_id')
            ->where('e.has_access', 1)
            ->whereNotNull('c.instructor_id')
            ->select(
                'e.user_id as student_id',
                'c.instructor_id as coach_id',
                DB::raw('MIN(e.created_at) as joined_at')
            )
            ->groupBy('e.user_id', 'c.instructor_id')
            ->get();

        $now = now();
        $inserted = 0;

        foreach ($rows as $r) {
            // Same defensive coach-exists check as Pass 1.
            $coachExists = DB::table('users')->where('id', $r->coach_id)->exists();
            $studentExists = DB::table('users')->where('id', $r->student_id)->exists();
            if (! $coachExists || ! $studentExists) continue;

            try {
                DB::table('coach_student_links')->insertOrIgnore([
                    'coach_id'   => $r->coach_id,
                    'student_id' => $r->student_id,
                    'source'     => 'purchase',
                    'status'     => 'active',
                    'joined_at'  => $r->joined_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                Log::warning('csl-backfill-purchase skip', [
                    'student_id' => $r->student_id,
                    'coach_id'   => $r->coach_id,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        Log::info('csl-backfill-pass2-done', ['rows_processed' => $rows->count(), 'inserted' => $inserted]);
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_student_links');
    }
};
