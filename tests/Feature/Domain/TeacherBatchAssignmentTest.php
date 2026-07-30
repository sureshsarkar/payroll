<?php

namespace Tests\Feature\Domain;

use App\Models\CoachStaff;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseChapter;
use App\Models\CourseChapterLesson;
use App\Models\CourseLiveClass;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * Phase 1-4 — Teacher → Batch assignment + live-class gating.
 *
 * The coach grants their CoachStaff "teachers" access to specific
 * course_batches. The grant is the only thing letting a teacher see,
 * create, edit, host, or attend live classes for that batch.
 *
 * What we don't test here:
 *   - Zoom Meeting SDK signature math (covered by ZoomSignatureTest)
 *   - Notification dispatch (covered by LiveClass notification tests)
 */
class TeacherBatchAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    /* ─────────── Phase 1 · table + model contracts ─────────── */

    public function test_teacher_batch_assignments_table_exists_with_required_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('teacher_batch_assignments'));
        foreach ([
            'id', 'coach_id', 'teacher_id', 'course_id', 'batch_id',
            'permission_type', 'status', 'assigned_at',
            'created_at', 'updated_at',
        ] as $col) {
            $this->assertTrue(
                \Schema::hasColumn('teacher_batch_assignments', $col),
                "teacher_batch_assignments must have $col column"
            );
        }
    }

    public function test_unique_index_blocks_duplicate_coach_teacher_batch(): void
    {
        [$coach, $teacher, $batch] = $this->makeCoachTeacherBatch();
        TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batch->course_id, 'batch_id' => $batch->id,
            'permission_type' => 'manage', 'status' => 'active',
            'assigned_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batch->course_id, 'batch_id' => $batch->id,
            'permission_type' => 'manage', 'status' => 'active',
            'assigned_at' => now(),
        ]);
    }

    public function test_assigned_batch_ids_returns_null_for_coach(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $this->assertNull(
            TeacherBatchAssignment::assignedBatchIdsFor($coach->id),
            'Coach (role=instructor) is unbounded — must return null.'
        );
    }

    public function test_assigned_batch_ids_returns_empty_array_for_teacher_with_no_grants(): void
    {
        [, $teacher] = $this->makeCoachTeacherBatch();
        // No assignment row created.
        $this->assertSame([], TeacherBatchAssignment::assignedBatchIdsFor($teacher->id));
    }

    public function test_assigned_batch_ids_returns_active_grants_only(): void
    {
        [$coach, $teacher, $batchA] = $this->makeCoachTeacherBatch();
        $batchB = $this->makeBatchForCoach($coach);
        $batchC = $this->makeBatchForCoach($coach);

        // Active grant for A.
        TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batchA->course_id, 'batch_id' => $batchA->id,
            'permission_type' => 'manage', 'status' => 'active', 'assigned_at' => now(),
        ]);
        // Active grant for B.
        TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batchB->course_id, 'batch_id' => $batchB->id,
            'permission_type' => 'manage', 'status' => 'active', 'assigned_at' => now(),
        ]);
        // Inactive (soft-removed) grant for C — must NOT appear.
        TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batchC->course_id, 'batch_id' => $batchC->id,
            'permission_type' => 'manage', 'status' => 'inactive', 'assigned_at' => now(),
        ]);

        TeacherBatchAssignment::forgetCacheFor($teacher->id);
        $ids = TeacherBatchAssignment::assignedBatchIdsFor($teacher->id);

        $this->assertEqualsCanonicalizing([$batchA->id, $batchB->id], $ids);
    }

    /* ─────────── Phase 2 · coach assignment CRUD ─────────── */

    public function test_coach_assignment_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        foreach ([
            'instructor.teacher-batches.index',
            'instructor.teacher-batches.create',
            'instructor.teacher-batches.store',
            'instructor.teacher-batches.edit',
            'instructor.teacher-batches.update',
            'instructor.teacher-batches.destroy',
            'instructor.teacher-batches.batches-for-course',
        ] as $name) {
            $this->assertNotNull(
                $routes->first(fn ($r) => $r->getName() === $name),
                "Route $name must be registered."
            );
        }
    }

    public function test_coach_can_assign_one_teacher_to_multiple_batches(): void
    {
        [$coach, $teacher, $batchA] = $this->makeCoachTeacherBatch();
        // Same course as batchA — the store() validator only accepts
        // batches that belong to the chosen course.
        $batchB = $this->makeBatchForCoach($coach, $batchA->course_id);

        Auth::guard('web')->loginUsingId($coach->id);
        $ctrl = app(\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST', [
            'teacher_id' => $teacher->id,
            'course_id'  => $batchA->course_id,
            'batch_ids'  => [$batchA->id, $batchB->id],
        ]);
        $resp = $ctrl->store($req);

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame(2, TeacherBatchAssignment::where('teacher_id', $teacher->id)
            ->where('status', 'active')->count());
    }

    public function test_coach_cannot_assign_another_coachs_batch(): void
    {
        [$coachA, $teacher] = $this->makeCoachTeacherBatch();
        $coachB = User::factory()->create(['role' => 'instructor']);
        $foreignBatch = $this->makeBatchForCoach($coachB); // belongs to a different coach

        Auth::guard('web')->loginUsingId($coachA->id);
        $ctrl = app(\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST', [
            'teacher_id' => $teacher->id,
            'course_id'  => $foreignBatch->course_id,
            'batch_ids'  => [$foreignBatch->id],
        ]);

        // Course ownership check aborts 403.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $ctrl->store($req);
    }

    public function test_coach_cannot_assign_a_staff_member_of_another_coach(): void
    {
        [$coachA] = $this->makeCoachTeacherBatch();
        $coachB = User::factory()->create(['role' => 'instructor']);
        $foreignTeacher = User::factory()->create(['role' => 'staff', 'coach_id' => $coachB->id]);
        $batch = $this->makeBatchForCoach($coachA);

        Auth::guard('web')->loginUsingId($coachA->id);
        $ctrl = app(\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST', [
            'teacher_id' => $foreignTeacher->id,
            'course_id'  => $batch->course_id,
            'batch_ids'  => [$batch->id],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $ctrl->store($req);
    }

    public function test_resubmit_existing_grant_does_not_duplicate(): void
    {
        [$coach, $teacher, $batch] = $this->makeCoachTeacherBatch();
        Auth::guard('web')->loginUsingId($coach->id);
        $ctrl = app(\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class);

        $payload = ['teacher_id' => $teacher->id, 'course_id' => $batch->course_id, 'batch_ids' => [$batch->id]];
        $ctrl->store(\Illuminate\Http\Request::create('/x', 'POST', $payload));
        $ctrl->store(\Illuminate\Http\Request::create('/x', 'POST', $payload));

        $this->assertSame(1, TeacherBatchAssignment::where('teacher_id', $teacher->id)->count(),
            'Second submit of the same (coach, teacher, batch) must not insert a duplicate row.');
    }

    public function test_destroy_soft_removes_assignment(): void
    {
        [$coach, $teacher, $batch] = $this->makeCoachTeacherBatch();
        $a = TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batch->course_id, 'batch_id' => $batch->id,
            'permission_type' => 'manage', 'status' => 'active', 'assigned_at' => now(),
        ]);

        Auth::guard('web')->loginUsingId($coach->id);
        $ctrl = app(\App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class);
        $ctrl->destroy($a->id);

        $this->assertSame('inactive', $a->fresh()->status,
            'destroy() must soft-remove (status=inactive), not delete the row.');
    }

    /* ─────────── Phase 3 · teacher-side gating ─────────── */

    public function test_assigned_teacher_passes_creation_context_gate(): void
    {
        [$coach, $teacher, $batch] = $this->makeCoachTeacherBatch();
        TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batch->course_id, 'batch_id' => $batch->id,
            'permission_type' => 'manage', 'status' => 'active', 'assigned_at' => now(),
        ]);
        $chapter = CourseChapter::create(['course_id' => $batch->course_id, 'title' => 'Ch', 'order' => 1]);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);

        $lcc = new \App\Http\Controllers\Frontend\Coach\LiveClassController(new CourseLiveClass());
        $m = new \ReflectionMethod($lcc, 'assertOwnsCreationContext');
        $m->setAccessible(true);
        // Must not throw.
        $m->invoke($lcc, $batch->course_id, $batch->id, $chapter->id);
        $this->assertTrue(true);
    }

    public function test_unassigned_teacher_blocked_from_creation_context_gate(): void
    {
        [$coach, $teacher, $batchA] = $this->makeCoachTeacherBatch();
        $batchB = $this->makeBatchForCoach($coach); // not assigned to teacher
        // Grant teacher only batchA.
        TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batchA->course_id, 'batch_id' => $batchA->id,
            'permission_type' => 'manage', 'status' => 'active', 'assigned_at' => now(),
        ]);
        $chapter = CourseChapter::create(['course_id' => $batchB->course_id, 'title' => 'Ch', 'order' => 1]);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);

        $lcc = new \App\Http\Controllers\Frontend\Coach\LiveClassController(new CourseLiveClass());
        $m = new \ReflectionMethod($lcc, 'assertOwnsCreationContext');
        $m->setAccessible(true);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $m->invoke($lcc, $batchB->course_id, $batchB->id, $chapter->id);
    }

    public function test_inactive_assignment_immediately_blocks_teacher(): void
    {
        [$coach, $teacher, $batch] = $this->makeCoachTeacherBatch();
        $a = TeacherBatchAssignment::create([
            'coach_id' => $coach->id, 'teacher_id' => $teacher->id,
            'course_id' => $batch->course_id, 'batch_id' => $batch->id,
            'permission_type' => 'manage', 'status' => 'active', 'assigned_at' => now(),
        ]);
        $chapter = CourseChapter::create(['course_id' => $batch->course_id, 'title' => 'Ch', 'order' => 1]);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);
        $a->update(['status' => 'inactive']);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);

        $lcc = new \App\Http\Controllers\Frontend\Coach\LiveClassController(new CourseLiveClass());
        $m = new \ReflectionMethod($lcc, 'assertOwnsCreationContext');
        $m->setAccessible(true);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $m->invoke($lcc, $batch->course_id, $batch->id, $chapter->id);
    }

    public function test_coach_remains_unbounded_after_changes(): void
    {
        // Regression — assert the coach still passes the gate for ANY
        // batch in their own course.
        [$coach, , $batch] = $this->makeCoachTeacherBatch();
        $chapter = CourseChapter::create(['course_id' => $batch->course_id, 'title' => 'Ch', 'order' => 1]);

        Auth::guard('web')->loginUsingId($coach->id);

        $lcc = new \App\Http\Controllers\Frontend\Coach\LiveClassController(new CourseLiveClass());
        $m = new \ReflectionMethod($lcc, 'assertOwnsCreationContext');
        $m->setAccessible(true);
        $m->invoke($lcc, $batch->course_id, $batch->id, $chapter->id);
        $this->assertTrue(true);
    }

    /* ─────────── Phase 4 · student-side gating ─────────── */

    public function test_student_with_matching_batch_passes_join_gate(): void
    {
        [$coach, , $batch] = $this->makeCoachTeacherBatch();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollStudent($student, $batch, $batch->id);
        $liveClass = $this->makeLiveClass($coach, $batch);

        Auth::guard('web')->loginUsingId($student->id);
        $resp = $this->callTrack($liveClass->id);

        $this->assertNotSame(403, $resp->getStatusCode(),
            'A student in the same batch as the live class must pass the gate.');
    }

    public function test_student_with_wrong_batch_is_blocked(): void
    {
        [$coach, , $batchA] = $this->makeCoachTeacherBatch();
        $batchB = $this->makeBatchForCoach($coach, $batchA->course_id);
        $student = User::factory()->create(['role' => 'student']);
        // Enrolled in course, but in batchB.
        $this->enrollStudent($student, $batchA, $batchB->id);
        // Live class targets batchA.
        $liveClass = $this->makeLiveClass($coach, $batchA);

        Auth::guard('web')->loginUsingId($student->id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->callTrack($liveClass->id);
    }

    public function test_legacy_student_with_null_batch_id_still_sees_class(): void
    {
        // Backward-compat: pre-2026-05-18 enrollments have batch_id=NULL.
        // They must continue to be admitted to course-wide and batched
        // live classes — silently breaking them is the worst migration
        // regression we could ship.
        [$coach, , $batch] = $this->makeCoachTeacherBatch();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollStudent($student, $batch, null); // NULL batch_id
        $liveClass = $this->makeLiveClass($coach, $batch);

        Auth::guard('web')->loginUsingId($student->id);
        $resp = $this->callTrack($liveClass->id);

        $this->assertNotSame(403, $resp->getStatusCode(),
            'Legacy enrollment (NULL batch_id) must still pass the gate.');
    }

    public function test_unenrolled_student_blocked(): void
    {
        [$coach, , $batch] = $this->makeCoachTeacherBatch();
        $student = User::factory()->create(['role' => 'student']);
        // No enrollment.
        $liveClass = $this->makeLiveClass($coach, $batch);

        Auth::guard('web')->loginUsingId($student->id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->callTrack($liveClass->id);
    }

    /* ─────────── helpers ─────────── */

    /** Build a coach + a CoachStaff teacher + one CourseBatch. */
    private function makeCoachTeacherBatch(): array
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $teacher = User::factory()->create([
            'role' => 'staff', 'coach_id' => $coach->id,
        ]);
        $batch = $this->makeBatchForCoach($coach);
        return [$coach, $teacher, $batch];
    }

    private function makeBatchForCoach(User $coach, ?int $courseId = null): CourseBatch
    {
        $courseId = $courseId ?? DB::table('courses')->insertGetId([
            'title' => 'TBA Course ' . uniqid(),
            'slug'  => 'tba-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'live',
            'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return CourseBatch::create([
            'course_id'  => $courseId,
            'title'      => 'Batch ' . uniqid(),
            'start_date' => now(),
            'end_date'   => now()->addMonth(),
            'start_time' => '09:00:00',
            'end_time'   => '10:00:00',
            'capacity'   => 30,
            'days'       => ['monday'],
            'status'     => 'active',
        ]);
    }

    private function enrollStudent(User $student, CourseBatch $courseBatch, ?int $batchId): void
    {
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $courseBatch->course_id,
            'batch_id'   => $batchId,
            'has_access' => 1,
        ]);
    }

    private function makeLiveClass(User $coach, CourseBatch $batch): CourseLiveClass
    {
        $chapter = CourseChapter::create([
            'course_id' => $batch->course_id, 'title' => 'Ch', 'order' => 1,
        ]);
        $lesson = CourseChapterLesson::create([
            'title' => 'Live', 'instructor_id' => $coach->id,
            'course_id' => $batch->course_id, 'chapter_id' => $chapter->id,
            'storage' => 'live', 'file_type' => 'live',
        ]);
        return CourseLiveClass::create([
            'batch_id'   => $batch->id,
            'course_id'  => $batch->course_id,
            'lesson_id'  => $lesson->id,
            'start_time' => now()->addHour(),
            'type'       => 'zoom',
            'meeting_id' => '00000',
        ]);
    }

    /**
     * Hit LiveClassAttendanceController::track which is the simplest
     * single-action endpoint that exercises the per-batch student gate.
     */
    private function callTrack(int $liveClassId): \Illuminate\Http\JsonResponse
    {
        $ctrl = app(\App\Http\Controllers\Frontend\LiveClassAttendanceController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST', ['event' => 'join']);
        // Pretend the session is started so getId() doesn't blow up.
        $req->setLaravelSession(app('session.store'));
        return $ctrl->track($req, $liveClassId);
    }
}
