<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\API\StudentApiDashboardController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Audit finding [6] — free enrolment order must use the canonical
 * payment_status='paid' marker, not the outlier 'completed'.
 *
 * Everything else (PaymentFulfilmentService, OrderStatusChangedToStudent,
 * coach/admin analytics, the "paid + completed" access rule) keys "fulfilled"
 * off payment_status='paid'. free_enroll wrote 'completed', which excluded
 * free orders from every payment_status='paid' query and violated the access
 * rule. A free order is paid in full ($0) so 'paid' is correct.
 */
class FreeEnrollPaymentStatusTest extends TestCase
{
    use DatabaseTransactions;

    private function makeFreeCourse(int $coachId): int
    {
        return DB::table('courses')->insertGetId([
            'title' => 'Free course ' . uniqid(), 'slug' => 'free-' . uniqid(),
            'instructor_id' => $coachId, 'added_by' => $coachId,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_free_enroll_creates_paid_completed_order_with_access(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = $this->makeFreeCourse($coach->id);
        $slug = DB::table('courses')->where('id', $courseId)->value('slug');

        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student);

        $resp = app(StudentApiDashboardController::class)->free_enroll($slug);
        $this->assertEquals(200, $resp->getStatusCode());

        $order = Order::where('buyer_id', $student->id)
            ->where('payment_method', 'free')
            ->latest('id')->first();

        $this->assertNotNull($order, 'free_enroll must create an order');
        $this->assertSame('paid', $order->payment_status, 'free order must be payment_status=paid (canonical), not completed');
        $this->assertSame('completed', $order->status, 'free order status stays completed');
        $this->assertEquals(0, (float) $order->paid_amount);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id, 'course_id' => $courseId, 'has_access' => 1,
        ]);
    }

    public function test_free_enroll_is_idempotent(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = $this->makeFreeCourse($coach->id);
        $slug = DB::table('courses')->where('id', $courseId)->value('slug');

        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student);

        app(StudentApiDashboardController::class)->free_enroll($slug);
        app(StudentApiDashboardController::class)->free_enroll($slug); // second call: already enrolled

        $this->assertEquals(1, Enrollment::where('user_id', $student->id)->where('course_id', $courseId)->count(),
            'second free_enroll must not create a duplicate enrolment');
    }
}
