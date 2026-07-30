<?php

namespace Tests\Feature\Domain;

use App\Models\CoachStudentLink;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * Multi-coach support (2026-05-21).
 *
 * Locks in the four product decisions:
 *   Q1  Student CAN self-remove from a coach's roster (soft).
 *   Q2  Student auto-links to a coach when they buy a course.
 *   Q3  users.added_by retained as audit-only; not read for routing.
 *   Q4  My Students includes purchasers, not just explicitly-added.
 *
 * Plus the foundational schema + helper contracts.
 */
class CoachStudentMultiCoachTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // The legacy checkPermission() helper reads $_SERVER['REQUEST_URI'];
        // PHPUnit's CLI context doesn't populate it. Stub one that
        // satisfies the coach-students page match (segment-end).
        $_SERVER['REQUEST_URI'] = '/instructor/coach-students';
    }

    /* ───────── schema + helpers ───────── */

    public function test_coach_student_links_table_exists_with_required_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('coach_student_links'));
        foreach ([
            'id', 'coach_id', 'student_id', 'source', 'status',
            'joined_at', 'removed_at', 'created_at', 'updated_at',
        ] as $col) {
            $this->assertTrue(
                \Schema::hasColumn('coach_student_links', $col),
                "coach_student_links must have $col column"
            );
        }
    }

    public function test_unique_index_blocks_duplicate_coach_student_pair(): void
    {
        [$coach, $student] = $this->makeCoachAndStudent();
        CoachStudentLink::create([
            'coach_id' => $coach->id, 'student_id' => $student->id,
            'source' => 'added', 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        CoachStudentLink::create([
            'coach_id' => $coach->id, 'student_id' => $student->id,
            'source' => 'purchase', 'status' => 'active', 'joined_at' => now(),
        ]);
    }

    public function test_link_helper_is_idempotent_and_reactivates_removed_rows(): void
    {
        [$coach, $student] = $this->makeCoachAndStudent();

        // First link.
        $a = CoachStudentLink::link($coach->id, $student->id, 'added');
        $this->assertSame('active', $a->status);
        $this->assertSame('added', $a->source);

        // Re-link → same row, no-op.
        $b = CoachStudentLink::link($coach->id, $student->id, 'purchase');
        $this->assertSame($a->id, $b->id);
        $this->assertSame('added', $b->source, 'second link must NOT clobber source');

        // Unlink (self-remove) → soft-remove.
        $this->assertTrue(CoachStudentLink::unlink($coach->id, $student->id));
        $this->assertSame('removed', $a->fresh()->status);
        $this->assertNotNull($a->fresh()->removed_at);

        // Re-link after removal → reactivates.
        $c = CoachStudentLink::link($coach->id, $student->id, 'purchase');
        $this->assertSame($a->id, $c->id, 'reactivation must reuse the row');
        $this->assertSame('active', $c->fresh()->status);
        $this->assertNull($c->fresh()->removed_at);
    }

    public function test_student_ids_for_coach_returns_only_active_links(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $studentIn  = User::factory()->create(['role' => 'student']);
        $studentOut = User::factory()->create(['role' => 'student']);

        CoachStudentLink::create([
            'coach_id' => $coach->id, 'student_id' => $studentIn->id,
            'source' => 'added', 'status' => 'active', 'joined_at' => now(),
        ]);
        CoachStudentLink::create([
            'coach_id' => $coach->id, 'student_id' => $studentOut->id,
            'source' => 'added', 'status' => 'removed', 'joined_at' => now(),
        ]);
        CoachStudentLink::forgetCacheForCoach($coach->id);

        $ids = CoachStudentLink::studentIdsForCoach($coach->id);
        $this->assertContains($studentIn->id, $ids);
        $this->assertNotContains($studentOut->id, $ids, 'removed link must NOT appear in active roster');
    }

    /* ───────── Q4: my-students includes purchasers ───────── */

    public function test_my_students_lists_buyer_who_was_never_explicitly_added(): void
    {
        $coachA = User::factory()->create(['role' => 'instructor']);
        $coachB = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create([
            'role' => 'student',
            'added_by' => $coachB->id, // student was originally added by coach B
        ]);

        // Student bought a course from coach A → simulated by inserting
        // the link directly (we test the order-fulfilment hook separately).
        CoachStudentLink::link($coachA->id, $student->id, 'purchase');

        Auth::guard('web')->loginUsingId($coachA->id);
        $ctrl = app(\App\Http\Controllers\Frontend\InstructorDashboardController::class);
        $view = $ctrl->myStudents();
        $list = $view->getData()['mystudents'];
        $ids  = collect($list->items())->pluck('id')->all();

        $this->assertContains(
            $student->id, $ids,
            'Coach A must see this student in My Students even though added_by points at Coach B — pivot is the source of truth.'
        );
    }

    /* ───────── Q2: auto-link on order completion ───────── */

    public function test_marking_order_paid_auto_links_buyer_to_course_coach(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $buyer = User::factory()->create(['role' => 'student']);
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'CSL Course ' . uniqid(),
            'slug'  => 'csl-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => 100, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'status' => 'pending',
            'payment_status' => 'pending', 'currency' => 'INR',
            'paid_amount' => 100, 'payable_amount' => 100,
            'gateway_charge' => 0, 'coupon_discount_amount' => 0,
            'commission_rate' => 0,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'course_id' => $courseId,
            'price' => 100, 'qty' => 1, 'commission_rate' => 0,
            'payable_amount' => 100, 'gateway_charge' => 0,
            'coupon_discount_amount' => 0,
        ]);

        // Pre-condition: no link yet.
        $this->assertFalse(
            CoachStudentLink::where('coach_id', $coach->id)
                ->where('student_id', $buyer->id)->exists()
        );

        // Fulfil → expect the link to appear.
        $svc = app(\App\Services\PaymentFulfilmentService::class);
        $svc->markPaid($order, 'txn_test_' . uniqid(), 'test details');

        $link = CoachStudentLink::where('coach_id', $coach->id)
            ->where('student_id', $buyer->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($link, 'order completion must auto-link buyer to coach');
        $this->assertSame('purchase', $link->source);
    }

    /* ───────── Q1: student self-remove ───────── */

    public function test_student_self_remove_endpoint_soft_removes_link(): void
    {
        [$coach, $student] = $this->makeCoachAndStudent();
        CoachStudentLink::link($coach->id, $student->id, 'added');

        Auth::guard('web')->loginUsingId($student->id);
        $ctrl = app(\App\Http\Controllers\Frontend\StudentDashboardController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST');
        $req->setLaravelSession(app('session.store'));
        $ctrl->leaveCoach($req, $coach->id);

        $row = CoachStudentLink::where('coach_id', $coach->id)
            ->where('student_id', $student->id)->first();
        $this->assertSame('removed', $row->status);
        $this->assertNotNull($row->removed_at);
    }

    public function test_self_remove_is_idempotent_when_no_active_link_exists(): void
    {
        [$coach, $student] = $this->makeCoachAndStudent();
        // No link at all — endpoint should still respond gracefully.

        Auth::guard('web')->loginUsingId($student->id);
        $ctrl = app(\App\Http\Controllers\Frontend\StudentDashboardController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST');
        $req->setLaravelSession(app('session.store'));
        $resp = $ctrl->leaveCoach($req, $coach->id);

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertFalse(
            CoachStudentLink::where('coach_id', $coach->id)
                ->where('student_id', $student->id)->exists(),
            'idempotent self-remove must not insert a row when none existed'
        );
    }

    public function test_self_remove_rejects_non_student_callers(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        Auth::guard('web')->loginUsingId($coach->id);

        $ctrl = app(\App\Http\Controllers\Frontend\StudentDashboardController::class);
        $req = \Illuminate\Http\Request::create('/x', 'POST');
        $req->setLaravelSession(app('session.store'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $ctrl->leaveCoach($req, $coach->id);
    }

    /* ───────── helpers ───────── */

    private function makeCoachAndStudent(): array
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);
        return [$coach, $student];
    }
}
