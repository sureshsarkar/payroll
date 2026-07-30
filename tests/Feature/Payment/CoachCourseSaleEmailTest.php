<?php

namespace Tests\Feature\Payment;

use App\Models\Course;
use App\Models\User;
use App\Notifications\CourseSaleToCoach;
use App\Services\PaymentFulfilmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * Doc item 5 — Coach Course-Sale email.
 *
 * A coach is emailed when their course sells, ONLY after the order is paid +
 * enrolled, and ONLY the owning coach (tenant-safe). Fired from markPaid().
 */
class CoachCourseSaleEmailTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        $id = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach ' . uniqid(), 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::find($id);
    }

    private function student(): User
    {
        $id = DB::table('users')->insertGetId([
            'role' => 'student', 'name' => 'Stu ' . uniqid(), 'email' => 's' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::find($id);
    }

    private function course(int $coachId, float $price = 499): int
    {
        return DB::table('courses')->insertGetId([
            'title' => 'C ' . uniqid(), 'slug' => 'c' . uniqid(), 'instructor_id' => $coachId, 'added_by' => $coachId,
            'is_approved' => 'approved', 'status' => 'active', 'price' => $price, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pendingOrder(int $studentId): Order
    {
        return Order::create([
            'invoice_id' => 'INV' . strtoupper(uniqid()), 'buyer_id' => $studentId, 'status' => 'pending',
            'payment_method' => 'razorpay', 'payment_status' => 'pending', 'payable_amount' => 499,
            'paid_amount' => 499, 'payable_currency' => 'INR',
        ]);
    }

    /* ---- content / tenant targeting (pure construction) ---- */

    public function test_notification_carries_all_required_fields_and_targets_owner(): void
    {
        $coachId = $this->coach()->id;
        $courseId = $this->course($coachId, 1500);
        $course = Course::find($courseId);
        $student = $this->student();
        $order = $this->pendingOrder($student->id);
        // Mirror real usage: markPaid flips the order to paid BEFORE the email fires.
        $order->payment_status = 'paid';
        $item = OrderItem::create(['order_id' => $order->id, 'qty' => 1, 'price' => 1500, 'item_type' => 'course', 'course_id' => $courseId]);

        $n = new CourseSaleToCoach($order, $item, $course, $student);
        $ref = new \ReflectionMethod($n, 'placeholders');
        $ref->setAccessible(true);
        $p = $ref->invoke($n, User::find($coachId));

        $this->assertSame($student->name, $p['student_name']);
        $this->assertSame($course->title, $p['course_title']);
        $this->assertSame($order->invoice_id, $p['order_id']);
        $this->assertStringContainsString('1,500', $p['amount']);
        $this->assertStringContainsString('INR', $p['amount']);
        $this->assertSame('Paid', $p['payment_status']);
        $this->assertNotEmpty($p['order_url']);
        $this->assertNotEmpty($p['student_url']);
    }

    /* ---- end-to-end via markPaid + tenant isolation ---- */

    public function test_markpaid_emails_only_the_owning_coach(): void
    {
        Notification::fake();

        $coachA = $this->coach();
        $coachB = $this->coach();
        $student = $this->student();

        $courseA = $this->course($coachA->id);
        $courseB = $this->course($coachB->id);

        $order = $this->pendingOrder($student->id);
        OrderItem::create(['order_id' => $order->id, 'qty' => 1, 'price' => 499, 'item_type' => 'course', 'course_id' => $courseA]);

        app(PaymentFulfilmentService::class)->markPaid($order, 'txn_' . uniqid(), ['gateway' => 'razorpay']);

        // Owning coach A is notified; unrelated coach B is NOT (tenant-safe).
        Notification::assertSentTo($coachA, CourseSaleToCoach::class);
        Notification::assertNotSentTo($coachB, CourseSaleToCoach::class);
        // The student is never the recipient of a coach-sale email.
        Notification::assertNotSentTo($student, CourseSaleToCoach::class);
    }

    public function test_replayed_markpaid_does_not_resend(): void
    {
        Notification::fake();
        $coach = $this->coach();
        $student = $this->student();
        $courseId = $this->course($coach->id);
        $order = $this->pendingOrder($student->id);
        OrderItem::create(['order_id' => $order->id, 'qty' => 1, 'price' => 499, 'item_type' => 'course', 'course_id' => $courseId]);

        $svc = app(PaymentFulfilmentService::class);
        $svc->markPaid($order, 'txn_a', ['gateway' => 'razorpay']);   // first → sends
        $svc->markPaid($order->fresh(), 'txn_a', ['gateway' => 'razorpay']); // replay → already paid, no send

        Notification::assertSentToTimes($coach, CourseSaleToCoach::class, 1);
    }
}
