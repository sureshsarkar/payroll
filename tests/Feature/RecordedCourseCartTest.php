<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\StudentDashboardController;
use App\Models\Cart;
use App\Models\CourseBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * 2026-06-11 — Recorded courses must be purchasable WITHOUT a batch; batch
 * validation applies only to Live/Batch courses ('live' | 'hybrid').
 * Covers the 7 required cases (cart, batch-gate, access, no-regression).
 */
class RecordedCourseCartTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // mbs_test doesn't seed these cached settings; addToCart's GTM helper
        // reads them. Seed inactive so the helper short-circuits (display-only).
        \Illuminate\Support\Facades\Cache::put('setting', (object) ['google_tagmanager_status' => 'inactive'], 600);
        \Illuminate\Support\Facades\Cache::put('marketing_setting', (object) ['add_to_cart' => false], 600);
    }

    private function student(): User { return User::factory()->create(['role' => 'student']); }
    private function coach(): User { return User::factory()->create(['role' => 'instructor', 'coach_id' => null]); }

    private function course(string $type): int
    {
        $coach = $this->coach();
        return DB::table('courses')->insertGetId([
            'title' => 'C ' . uniqid(), 'slug' => 'c-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => $type,
            'price' => 0, 'discount' => 0, 'coach_soft_delete' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function callAddToCart(int $courseId)
    {
        $req = Request::create('/add-to-cart/' . $courseId, 'POST', ['_token' => 'x']);
        $this->app->instance('request', $req);
        return app(CartController::class)->addToCart($req, (string) $courseId);
    }

    private function enrolledCourseIds(int $studentId): array
    {
        Auth::guard('web')->loginUsingId($studentId);
        $this->app->instance('request', Request::create('/student/enrolled-courses', 'GET'));
        $view = app(StudentDashboardController::class)->enrolledCourses();
        return collect($view->getData()['enrolls']->items())->pluck('course_id')->all();
    }

    // Test Case 1 — recorded course, no batch → add to cart works
    public function test_recorded_course_adds_to_cart_without_batch(): void
    {
        $student = $this->student();
        Auth::guard('web')->loginUsingId($student->id);
        $courseId = $this->course('recorded');

        $resp = $this->callAddToCart($courseId);
        $data = json_decode($resp->getContent(), true);

        $this->assertSame('success', $data['status'] ?? null);
        $cart = Cart::where('user_id', $student->id)->where('course_id', $courseId)->first();
        $this->assertNotNull($cart, 'recorded course must be added to the cart');
        $this->assertNull($cart->batch_id, 'recorded course cart row must carry NO batch_id');
    }

    // Legacy 'course' type behaves like recorded (no batch)
    public function test_legacy_course_type_adds_without_batch(): void
    {
        $student = $this->student();
        Auth::guard('web')->loginUsingId($student->id);
        $courseId = $this->course('course');

        $resp = $this->callAddToCart($courseId);
        $this->assertSame('success', json_decode($resp->getContent(), true)['status'] ?? null);
    }

    // Test Case 4 — live course without batch → must NOT add (validation)
    public function test_live_course_is_rejected_without_a_batch(): void
    {
        $student = $this->student();
        Auth::guard('web')->loginUsingId($student->id);
        $courseId = $this->course('live');

        $resp = $this->callAddToCart($courseId);
        $this->assertSame(422, $resp->getStatusCode(), 'live course must be rejected without a batch');
        $this->assertSame(0, Cart::where('course_id', $courseId)->count(), 'live course must not enter the cart batch-less');
    }

    public function test_hybrid_course_is_rejected_without_a_batch(): void
    {
        $student = $this->student();
        Auth::guard('web')->loginUsingId($student->id);
        $courseId = $this->course('hybrid');
        $this->assertSame(422, $this->callAddToCart($courseId)->getStatusCode());
    }

    // Test Case 5 — live course with a valid batch → add to cart works
    public function test_live_course_adds_with_a_valid_batch(): void
    {
        $student = $this->student();
        Auth::guard('web')->loginUsingId($student->id);
        $courseId = $this->course('live');
        $batch = CourseBatch::create([
            'course_id' => $courseId, 'title' => 'B', 'start_date' => now(),
            'end_date' => now()->addMonth(), 'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 30, 'days' => ['monday'], 'status' => 'active',
        ]);

        $req = Request::create('/add-to-cart-with-batch', 'POST', [
            'course_id' => $courseId, 'batch_id' => $batch->id, '_token' => 'x',
        ]);
        $this->app->instance('request', $req);
        $resp = app(CartController::class)->addToCartWithBatch($req);

        $this->assertSame('success', json_decode($resp->getContent(), true)['status'] ?? null);
        $cart = Cart::where('user_id', $student->id)->where('course_id', $courseId)->first();
        $this->assertEquals($batch->id, (int) $cart->batch_id, 'live course cart row must carry the chosen batch_id');
    }

    // Test Case 3 + 6 — paid recorded course (null batch) appears in dashboard
    public function test_paid_recorded_course_appears_in_dashboard(): void
    {
        $student = $this->student();
        $courseId = $this->course('recorded');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $courseId, 'batch_id' => null, 'order_id' => null, 'has_access' => 1]);

        $this->assertContains($courseId, $this->enrolledCourseIds($student->id),
            'paid recorded course (null batch) must be visible/accessible in the dashboard');
    }

    // Test Case 2 + 3 — a PAID recorded order (no batch) is fulfilled:
    // status flips to paid + enrollment + access (regression for the missing
    // CourseBatch::soleBatchIdForCourse that crashed markPaid → payment-failed).
    public function test_recorded_paid_order_is_fulfilled_and_grants_access(): void
    {
        $student = $this->student();
        $courseId = $this->course('recorded');
        $order = \Modules\Order\app\Models\Order::create([
            'buyer_id' => $student->id, 'status' => 'pending', 'payment_status' => 'pending',
            'currency' => 'INR', 'paid_amount' => 100, 'payable_amount' => 100,
            'gateway_charge' => 0, 'coupon_discount_amount' => 0, 'commission_rate' => 0,
            'payment_method' => 'Razorpay',
        ]);
        \Modules\Order\app\Models\OrderItem::create([
            'order_id' => $order->id, 'course_id' => $courseId, 'price' => 100, 'batch_id' => null,
        ]);

        \DB::transaction(function () use ($order) {
            app(\App\Services\PaymentFulfilmentService::class)
                ->markPaid($order, 'txn_test_' . uniqid(), ['status' => 'captured']);
        });

        $order->refresh();
        $this->assertSame('paid', $order->payment_status, 'recorded paid order must be marked paid');
        $enr = Enrollment::where('user_id', $student->id)->where('course_id', $courseId)->first();
        $this->assertNotNull($enr, 'recorded course must create an enrollment after payment');
        $this->assertSame(1, (int) $enr->has_access, 'access must be granted after payment');
        $this->assertNull($enr->batch_id, 'recorded enrollment must be batch-less');
        $this->assertContains($courseId, $this->enrolledCourseIds($student->id),
            'paid recorded course must appear in the dashboard');
    }

    // Test Case 7 — unpaid recorded course is NOT accessible
    public function test_unpaid_recorded_course_is_not_accessible(): void
    {
        $student = $this->student();
        $courseId = $this->course('recorded');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $courseId, 'batch_id' => null, 'order_id' => null, 'has_access' => 0]);

        $this->assertNotContains($courseId, $this->enrolledCourseIds($student->id),
            'recorded course without granted access (has_access=0) must NOT appear in the dashboard');
    }
}
