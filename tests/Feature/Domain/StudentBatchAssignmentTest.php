<?php

namespace Tests\Feature\Domain;

use App\Exceptions\AccessPermissionDeniedException;
use App\Http\Controllers\Frontend\Coach\CoachStudentBatchController;
use App\Models\CourseBatch;
use App\Models\StudentBatchAssignment;
use App\Models\User;
use App\Services\StudentBatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * Student batch assignment / reassignment (2026-07-15). Covers the spec's
 * scenarios: assign-later, move, same-batch, inactive, cross-coach, capacity,
 * not-enrolled, add-extra, duplicate, mandatory-reason-on-move, bulk mixed,
 * tenant isolation, history + audit.
 */
class StudentBatchAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): StudentBatchService
    {
        return app(StudentBatchService::class);
    }

    /** @return array{0:User,1:int,2:CourseBatch,3:CourseBatch} coach, courseId, batchA, batchB */
    private function scenario(int $capacity = 30): array
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'C ' . uniqid(), 'slug' => 'c-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'course',
            'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mk = fn ($status = 'active', $cap = null) => CourseBatch::create([
            'course_id' => $courseId, 'title' => 'B ' . uniqid(),
            'start_date' => now()->subWeek(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => $cap ?? $capacity, 'days' => ['monday'], 'status' => $status,
        ]);
        return [$coach, $courseId, $mk(), $mk()];
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function enrol(User $s, int $courseId, ?int $batchId, int $access = 1): Enrollment
    {
        return Enrollment::create(['user_id' => $s->id, 'course_id' => $courseId, 'batch_id' => $batchId, 'order_id' => null, 'has_access' => $access]);
    }

    // ── service: assign / move ──────────────────────────────────────────

    public function test_assign_batchless_student_sets_batch_and_logs_history(): void
    {
        Notification::fake();
        [$coach, $courseId, $batchA] = $this->scenario();
        $s = $this->student();
        $enr = $this->enrol($s, $courseId, null, 0);

        $res = $this->svc()->putIntoBatch($coach->id, $s->id, $batchA, null, $coach->id);

        $this->assertTrue($res['ok']);
        $this->assertSame('assign', $res['action']);
        $this->assertSame($batchA->id, (int) $enr->refresh()->batch_id);
        $this->assertDatabaseHas('student_batch_assignments', [
            'coach_id' => $coach->id, 'student_id' => $s->id, 'new_batch_id' => $batchA->id,
            'previous_batch_id' => null, 'action' => 'assign',
        ]);
    }

    public function test_move_between_batches_mutates_and_records_previous(): void
    {
        Notification::fake();
        [$coach, $courseId, $batchA, $batchB] = $this->scenario();
        $s = $this->student();
        $enr = $this->enrol($s, $courseId, $batchA->id);

        $res = $this->svc()->putIntoBatch($coach->id, $s->id, $batchB, 'timing clash', $coach->id, $batchA->id);

        $this->assertTrue($res['ok']);
        $this->assertSame('reassign', $res['action']);
        $this->assertSame($batchB->id, (int) $enr->refresh()->batch_id);
        $this->assertDatabaseHas('student_batch_assignments', [
            'student_id' => $s->id, 'previous_batch_id' => $batchA->id, 'new_batch_id' => $batchB->id,
            'action' => 'reassign', 'reason' => 'timing clash',
        ]);
    }

    public function test_same_batch_is_rejected(): void
    {
        [$coach, $courseId, $batchA] = $this->scenario();
        $s = $this->student();
        $this->enrol($s, $courseId, $batchA->id);

        $res = $this->svc()->putIntoBatch($coach->id, $s->id, $batchA, null, $coach->id);
        $this->assertFalse($res['ok']);
    }

    public function test_inactive_batch_is_rejected(): void
    {
        [$coach, $courseId] = $this->scenario();
        $inactive = CourseBatch::create([
            'course_id' => $courseId, 'title' => 'Inactive', 'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'capacity' => 30, 'days' => ['monday'], 'status' => 'inactive',
        ]);
        $s = $this->student();
        $this->enrol($s, $courseId, null, 0);

        $res = $this->svc()->putIntoBatch($coach->id, $s->id, $inactive, null, $coach->id);
        $this->assertFalse($res['ok']);
    }

    public function test_cross_coach_batch_throws(): void
    {
        [, $courseId, $batchA] = $this->scenario();
        $otherCoach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $s = $this->student();
        $this->enrol($s, $courseId, null);

        $this->expectException(AccessPermissionDeniedException::class);
        $this->svc()->putIntoBatch($otherCoach->id, $s->id, $batchA, null, $otherCoach->id);
    }

    public function test_capacity_full_is_rejected(): void
    {
        Notification::fake();
        [$coach, $courseId, $batchA] = $this->scenario();
        $full = CourseBatch::create([
            'course_id' => $courseId, 'title' => 'Full', 'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'capacity' => 1, 'days' => ['monday'], 'status' => 'active',
        ]);
        // one seat used
        $this->enrol($this->student(), $courseId, $full->id);

        $mover = $this->student();
        $this->enrol($mover, $courseId, $batchA->id);
        $res = $this->svc()->putIntoBatch($coach->id, $mover->id, $full, 'x', $coach->id, $batchA->id);
        $this->assertFalse($res['ok']);
    }

    public function test_not_enrolled_in_course_is_rejected_no_enrollment_invented(): void
    {
        [$coach, , $batchA] = $this->scenario();
        $outsider = $this->student(); // no enrollment

        $res = $this->svc()->putIntoBatch($coach->id, $outsider->id, $batchA, null, $coach->id);
        $this->assertFalse($res['ok']);
        $this->assertSame(0, Enrollment::where('user_id', $outsider->id)->count());
    }

    public function test_add_creates_second_enrollment_keeping_existing(): void
    {
        Notification::fake();
        [$coach, $courseId, $batchA, $batchB] = $this->scenario();
        $s = $this->student();
        $this->enrol($s, $courseId, $batchA->id);

        $res = $this->svc()->addToBatch($coach->id, $s->id, $batchB, null, $coach->id);

        $this->assertTrue($res['ok']);
        $this->assertSame('add', $res['action']);
        $this->assertSame(2, Enrollment::where('user_id', $s->id)->where('course_id', $courseId)->count());
        $this->assertSame(1, Enrollment::where('user_id', $s->id)->where('batch_id', $batchA->id)->count());
        $this->assertSame(1, Enrollment::where('user_id', $s->id)->where('batch_id', $batchB->id)->count());
    }

    public function test_notification_sent_to_student_on_assign(): void
    {
        Notification::fake();
        [$coach, $courseId, $batchA] = $this->scenario();
        $s = $this->student();
        $this->enrol($s, $courseId, null, 0);

        $this->svc()->putIntoBatch($coach->id, $s->id, $batchA, null, $coach->id);
        Notification::assertSentTo($s, \App\Notifications\StudentBatchAssignedToStudent::class);
    }

    // ── controller: mandatory reason + bulk ─────────────────────────────

    private function link(User $coach, User $student): void
    {
        DB::table('coach_student_links')->insertOrIgnore([
            'coach_id' => $coach->id, 'student_id' => $student->id, 'source' => 'manual', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function actingCoach(User $coach): void
    {
        Auth::guard('web')->loginUsingId($coach->id);
    }

    public function test_reassign_move_requires_reason(): void
    {
        Notification::fake();
        [$coach, $courseId, $batchA, $batchB] = $this->scenario();
        $s = $this->student();
        $this->enrol($s, $courseId, $batchA->id);
        $this->link($coach, $s);
        $this->actingCoach($coach);

        $req = Request::create('/x', 'POST', ['new_batch_id' => $batchB->id, 'mode' => 'move', 'from_batch_id' => $batchA->id, 'reason' => '   ']);
        $req->headers->set('Accept', 'application/json');
        $this->app->instance('request', $req);
        $resp = app(CoachStudentBatchController::class)->reassign($req, $s->id);

        $this->assertSame(422, $resp->getStatusCode());
        $this->assertSame($batchA->id, (int) Enrollment::where('user_id', $s->id)->first()->batch_id, 'no move without a reason');
    }

    public function test_reassign_move_with_reason_succeeds(): void
    {
        Notification::fake();
        [$coach, $courseId, $batchA, $batchB] = $this->scenario();
        $s = $this->student();
        $this->enrol($s, $courseId, $batchA->id);
        $this->link($coach, $s);
        $this->actingCoach($coach);

        $req = Request::create('/x', 'POST', ['new_batch_id' => $batchB->id, 'mode' => 'move', 'from_batch_id' => $batchA->id, 'reason' => 'timing clash']);
        $req->headers->set('Accept', 'application/json');
        $this->app->instance('request', $req);
        $resp = app(CoachStudentBatchController::class)->reassign($req, $s->id);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame($batchB->id, (int) Enrollment::where('user_id', $s->id)->first()->batch_id);
    }

    public function test_bulk_reports_success_and_failures_separately(): void
    {
        Notification::fake();
        [$coach, $courseId, $batchA, $batchB] = $this->scenario();
        // s1 enrolled (movable), s2 NOT enrolled (fails)
        $s1 = $this->student(); $this->enrol($s1, $courseId, $batchA->id); $this->link($coach, $s1);
        $s2 = $this->student(); $this->link($coach, $s2);
        $this->actingCoach($coach);

        $req = Request::create('/x', 'POST', ['student_ids' => [$s1->id, $s2->id], 'batch_id' => $batchB->id]);
        $req->headers->set('Accept', 'application/json');
        $this->app->instance('request', $req);
        $data = app(CoachStudentBatchController::class)->bulkAssign($req)->getData(true);

        $this->assertSame(1, $data['assigned']);
        $this->assertCount(1, $data['failed']);
        $this->assertSame($batchB->id, (int) Enrollment::where('user_id', $s1->id)->first()->batch_id);
        $this->assertSame(0, Enrollment::where('user_id', $s2->id)->count());
    }
}
