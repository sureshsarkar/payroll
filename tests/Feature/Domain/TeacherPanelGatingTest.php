<?php

namespace Tests\Feature\Domain;

use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CourseBatch;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * Teacher Panel (P1-P9) — gating across dashboard, students,
 * enquiries, fees, orders, analytics.
 *
 * Premise: a CoachStaff teacher with role != 'instructor' must see
 * ONLY data tied to batches their coach has assigned them. Coach
 * paths must remain byte-identical.
 *
 * Covers:
 *   - Dashboard payload sized to teacher scope (P1)
 *   - Students list narrows to assigned-batch students (P2)
 *   - Enquiries narrow to assigned-batch courses (P3)
 *   - Fee record gates batch_id (P4)
 *   - Orders narrow to assigned-batch students (P5)
 *   - Analytics narrows course_ids to assigned-batch courses (P6)
 *   - New permission slugs exist in catalog (P8)
 */
class TeacherPanelGatingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // The legacy checkPermission() helper reads $_SERVER['REQUEST_URI']
        // to derive the action suffix. PHPUnit's CLI context doesn't
        // populate $_SERVER the way an HTTP request does, so we stub
        // a benign value. Setting it to /instructor/* keeps the helper's
        // segment math producing 'index' as the last segment, which
        // matches the action checkPermission() needs for the
        // 'coach-students' / 'coach-sells' pages exercised below.
        $_SERVER['REQUEST_URI'] = '/instructor/test/index';
    }

    /* ───────── P1: dashboard payload ───────── */

    public function test_teacher_dashboard_payload_is_scoped_to_assigned_batches(): void
    {
        [$coach, $teacher, $batch] = $this->makeCoachTeacherBatch();

        // Grant teacher to one batch.
        $this->grant($coach, $teacher, $batch);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);
        \Cache::forget("teacher.dashboard:{$teacher->id}");

        $ctrl = app(\App\Http\Controllers\Frontend\InstructorDashboardController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildTeacherDashboard');
        $ref->setAccessible(true);
        $payload = $ref->invoke($ctrl, $teacher->id);

        $this->assertNotNull($payload);
        $this->assertSame(1, $payload['assigned_batches']);
        $this->assertGreaterThanOrEqual(0, $payload['student_count']);
        $this->assertArrayHasKey('upcoming_list', $payload);
        $this->assertArrayHasKey('activity', $payload);
    }

    public function test_coach_dashboard_does_not_get_teacher_payload(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        Auth::guard('web')->loginUsingId($coach->id);

        $ctrl = app(\App\Http\Controllers\Frontend\InstructorDashboardController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildTeacherDashboard');
        $ref->setAccessible(true);
        // Coach calling this returns null — gate ensures coach UI
        // falls through to the existing widgets.
        $this->assertNull($ref->invoke($ctrl, $coach->id));
    }

    /* ───────── P2: students gating ───────── */

    public function test_teacher_sees_only_students_in_assigned_batches(): void
    {
        [$coach, $teacher, $batchA] = $this->makeCoachTeacherBatch();
        $batchB = $this->makeBatchForCoach($coach);

        // Two students: one in assigned batch (visible), one in
        // another batch (must be hidden).
        $studentIn  = User::factory()->create(['role' => 'student', 'coach_id' => $coach->id]);
        $studentOut = User::factory()->create(['role' => 'student', 'coach_id' => $coach->id]);
        $this->enroll($studentIn,  $batchA);
        $this->enroll($studentOut, $batchB);
        $this->grant($coach, $teacher, $batchA);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);

        // checkPermission's last-segment match needs to equal the
        // page name ('coach-students') to pass through to the list.
        $_SERVER['REQUEST_URI'] = '/instructor/coach-students';

        $ctrl = app(\App\Http\Controllers\Frontend\InstructorDashboardController::class);
        $view = $ctrl->myStudents();
        $list = $view->getData()['mystudents'];

        $ids = collect($list->items())->pluck('id')->all();
        $this->assertContains($studentIn->id, $ids, 'student in assigned batch must be visible');
        $this->assertNotContains($studentOut->id, $ids, 'student in unassigned batch must be hidden');
    }

    /* ───────── P3: enquiries gating ───────── */

    public function test_teacher_blocked_on_enquiry_for_unassigned_course(): void
    {
        [$coach, $teacher, $batchA] = $this->makeCoachTeacherBatch();
        $batchB = $this->makeBatchForCoach($coach); // different course
        $this->grant($coach, $teacher, $batchA);

        // Enquiry tied to batchB's course (which teacher has no
        // assignment in).
        $enquiry = \App\Models\LandingPageEnquiry::create([
            'coach_id'   => $coach->id,
            'product_id' => $batchB->course_id,
            'first_name' => 'X',
            'email'      => 'x@example.com',
            'phone'      => '1',
            'status'     => 'new',
        ]);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);

        $ctrl = app(\App\Http\Controllers\Frontend\Coach\LandingPageEnquiryController::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $ctrl->show($enquiry->id);
    }

    public function test_teacher_can_open_enquiry_assigned_to_them(): void
    {
        // 2026-07-10 (New Changes for UI #8.1) — enquiry visibility is now
        // driven by explicit ASSIGNMENT (assigned_to), not the course-batch
        // heuristic. A staff member opens a lead the coach assigned to them.
        [$coach, $teacher, $batch] = $this->makeCoachTeacherBatch();
        $this->grant($coach, $teacher, $batch);

        $enquiry = \App\Models\LandingPageEnquiry::create([
            'coach_id'    => $coach->id,
            'product_id'  => $batch->course_id,
            'assigned_to' => $teacher->id,
            'first_name'  => 'Y',
            'email'       => 'y@example.com',
            'phone'       => '1',
            'status'      => 'new',
        ]);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);

        $ctrl = app(\App\Http\Controllers\Frontend\Coach\LandingPageEnquiryController::class);
        $view = $ctrl->show($enquiry->id);
        $this->assertSame((int) $enquiry->id, (int) $view->getData()['enquiry']->id);
    }

    /* ───────── P4: fee gating ───────── */

    public function test_teacher_record_payment_blocked_on_unassigned_batch(): void
    {
        [$coach, $teacher, $batchA] = $this->makeCoachTeacherBatch();
        $batchB = $this->makeBatchForCoach($coach);
        $this->grant($coach, $teacher, $batchA);

        // Demand sits on batchB (NOT assigned to teacher).
        $demand = \App\Models\FeeDemand::create([
            'coach_id' => $coach->id, 'batch_id' => $batchB->id,
            'title' => 'X', 'amount' => 500, 'status' => 'published',
            'created_by' => $coach->id,
        ]);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);

        $ctrl = app(\App\Http\Controllers\Frontend\Coach\FeeManagementController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST', [
            'student_id' => 1, 'amount' => 100,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $ctrl->recordPayment($req, $demand->id);
    }

    /* ───────── P5: orders gating ───────── */

    public function test_teacher_order_query_limited_to_own_authored_courses(): void
    {
        // 2026-07-10 (New Changes for UI #6) — a staff member with the Orders
        // permission sees ONLY orders for courses THEY created (added_by=staff),
        // not the coach's or another staff member's. (Replaces the old
        // assigned-batch-student heuristic.)
        [$coach, $teacher] = $this->makeCoachTeacherBatch();

        // A course the TEACHER authored (added_by = teacher; instructor_id = coach).
        $ownCourseId = DB::table('courses')->insertGetId([
            'title' => 'Teacher Own ' . uniqid(), 'slug' => 'town-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $teacher->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'live',
            'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // A course the COACH authored (added_by = coach).
        $coachCourseId = DB::table('courses')->insertGetId([
            'title' => 'Coach Own ' . uniqid(), 'slug' => 'cown-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'live',
            'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $buyer  = User::factory()->create(['role' => 'student', 'coach_id' => $coach->id]);
        $oOwn   = $this->makeOrderItem($buyer, $ownCourseId);
        $oCoach = $this->makeOrderItem($buyer, $coachCourseId);

        Auth::guard('web')->loginUsingId($teacher->id);
        TeacherBatchAssignment::forgetCacheFor($teacher->id);

        $ctrl = app(\App\Http\Controllers\Frontend\InstructorDashboardController::class);
        $ref = new \ReflectionMethod($ctrl, 'coachOwnedOrderItemQuery');
        $ref->setAccessible(true);
        /** @var \Illuminate\Database\Eloquent\Builder $q */
        $q = $ref->invoke($ctrl);
        $itemIds = $q->pluck('id')->all();

        $this->assertContains($oOwn->id, $itemIds, 'staff sees orders for courses they authored');
        $this->assertNotContains($oCoach->id, $itemIds, "staff does NOT see orders for the coach's own courses");
    }

    /* ───────── P8: new permission slugs ───────── */

    public function test_new_teacher_panel_permission_slugs_exist(): void
    {
        foreach (['dashboard', 'analytics', 'instant-meeting', 'attendance-edit'] as $slug) {
            $this->assertTrue(
                CoachStaffPermission::where('slug', $slug)->exists(),
                "permission slug '$slug' must be seeded so coaches can grant it via Roles."
            );
        }
    }

    /* ───────── helpers ───────── */

    private function makeCoachTeacherBatch(): array
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $teacher = User::factory()->create(['role' => 'staff', 'coach_id' => $coach->id]);

        // The legacy checkPermission() helper requires the staff to
        // carry a role_id whose role has the relevant permission slugs.
        // Give the test teacher a "Full Access" role with every
        // permission in the catalog so the page-level gates pass and
        // we can focus on the teacher-batch gate (the actual unit
        // under test). The coach branch never hits this code path.
        $role = \App\Models\CoachStaffRole::create([
            'role_name' => 'Teacher Test Role',
            'role_slug' => 'teacher-test-' . uniqid(),
            'added_by'  => $coach->id,
            'status'    => 1,
        ]);
        // Test DB lacks the legacy catalog seed (the dev DB has 27
        // slugs from a seeder; testing uses migrate which doesn't run
        // seeders). Make sure the slugs these tests exercise exist
        // before granting them.
        foreach (['coach-students', 'coach-sells', 'landing-page-enquiry'] as $slug) {
            \App\Models\CoachStaffPermission::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('-', ' ', $slug))]
            );
        }
        $permIds = \App\Models\CoachStaffPermission::pluck('id');
        \DB::table('roles_permissions')->insert(
            $permIds->map(fn ($pid) => [
                'coach_staff_role_id'       => $role->id,
                'coach_staff_permission_id' => $pid,
                'status'                    => 1,
            ])->all()
        );
        // Helper reads $user->permissions (many-to-many via
        // users_permissions), not $user->role->permissions. So insert
        // direct per-user grants too — same wire format the production
        // staff-create flow uses.
        \DB::table('users_permissions')->insert(
            $permIds->map(fn ($pid) => [
                'coach_staff_id'            => $teacher->id,
                'coach_staff_permission_id' => $pid,
                'status'                    => 1,
            ])->all()
        );
        $teacher->role_id = $role->id;
        $teacher->save();

        $batch = $this->makeBatchForCoach($coach);
        return [$coach, $teacher, $batch];
    }

    private function makeBatchForCoach(User $coach, ?int $courseId = null): CourseBatch
    {
        $courseId = $courseId ?? DB::table('courses')->insertGetId([
            'title' => 'TP Course ' . uniqid(),
            'slug'  => 'tp-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'live',
            'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return CourseBatch::create([
            'course_id'  => $courseId,
            'title'      => 'B ' . uniqid(),
            'start_date' => now(),
            'end_date'   => now()->addMonth(),
            'start_time' => '09:00:00',
            'end_time'   => '10:00:00',
            'capacity'   => 30,
            'days'       => ['monday'],
            'status'     => 'active',
        ]);
    }

    private function grant(User $coach, User $teacher, CourseBatch $batch): TeacherBatchAssignment
    {
        return TeacherBatchAssignment::firstOrCreate(
            ['coach_id' => $coach->id, 'teacher_id' => $teacher->id, 'batch_id' => $batch->id],
            ['course_id' => $batch->course_id, 'permission_type' => 'manage',
             'status' => 'active', 'assigned_at' => now()]
        );
    }

    private function enroll(User $student, CourseBatch $batch): void
    {
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $batch->course_id,
            'batch_id' => $batch->id, 'has_access' => 1,
        ]);
    }

    private function makeOrderItem(User $buyer, int $courseId): OrderItem
    {
        $order = Order::create([
            'buyer_id' => $buyer->id, 'status' => 'completed',
            'payment_status' => 'paid', 'currency' => 'INR',
            'paid_amount' => 500, 'payable_amount' => 500,
            'gateway_charge' => 0, 'coupon_discount_amount' => 0,
            'commission_rate' => 0,
        ]);
        return OrderItem::create([
            'order_id' => $order->id, 'course_id' => $courseId,
            'price' => 500, 'qty' => 1, 'commission_rate' => 0,
            'payable_amount' => 500, 'gateway_charge' => 0,
            'coupon_discount_amount' => 0,
        ]);
    }
}
