<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\Frontend\Coach\CoachStudentDashboardController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * Audit finding [11] — white-label order isolation.
 *
 * A single platform order can contain courses from MORE THAN ONE coach
 * (the platform cart isn't coach-scoped). The coach-scoped student "My
 * orders" view must therefore NEVER expose another coach's course title or
 * the order's combined cross-coach total on this coach's white-label site.
 *
 * Before the fix the view eager-loaded ALL orderItems and printed the full
 * paid_amount, leaking Coach B's course title + revenue onto Coach A's site.
 * After the fix orderItems is constrained to this coach and a coach-scoped
 * subtotal (display_amount) is used for mixed orders.
 */
class CoachOrderIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCourse(int $coachId, string $title, float $price): int
    {
        return DB::table('courses')->insertGetId([
            'title' => $title, 'slug' => 'iso-' . uniqid(),
            'instructor_id' => $coachId, 'added_by' => $coachId,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => $price, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array{0:\Illuminate\View\View} */
    private function renderOrdersFor(User $coach, User $student): \Illuminate\View\View
    {
        $this->actingAs($student, 'web');
        $request = Request::create('/coach/iso-coach/student/orders', 'GET');
        $request->attributes->set('tenant_coach', $coach);
        $controller = new CoachStudentDashboardController();
        return $controller->orders($request, 'iso-coach');
    }

    public function test_mixed_order_only_shows_this_coachs_item_and_subtotal(): void
    {
        $coachA = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $coachB = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $student = User::factory()->create(['role' => 'student']);

        $courseA = $this->makeCourse($coachA->id, 'Coach A Secret Course', 100);
        $courseB = $this->makeCourse($coachB->id, 'Coach B Private Course', 50);

        // One order spanning BOTH coaches. paid_amount = combined 150.
        $order = Order::create([
            'buyer_id' => $student->id, 'status' => 'completed',
            'payment_status' => 'paid', 'currency' => 'INR',
            'paid_amount' => 150, 'payable_amount' => 150,
            'gateway_charge' => 0, 'coupon_discount_amount' => 0, 'commission_rate' => 0,
        ]);
        OrderItem::create(['order_id' => $order->id, 'course_id' => $courseA, 'price' => 100, 'qty' => 1, 'commission_rate' => 0]);
        OrderItem::create(['order_id' => $order->id, 'course_id' => $courseB, 'price' => 50, 'qty' => 1, 'commission_rate' => 0]);

        $view = $this->renderOrdersFor($coachA, $student);
        $orders = $view->getData()['orders'];

        $this->assertCount(1, $orders, 'Coach A should see the one shared order.');
        $o = $orders->first();

        // Only Coach A's item is hydrated.
        $this->assertCount(1, $o->orderItems, 'mixed order must expose only THIS coach\'s items');
        $titles = $o->orderItems->map(fn($i) => $i->course?->title)->all();
        $this->assertContains('Coach A Secret Course', $titles);
        $this->assertNotContains('Coach B Private Course', $titles, 'other coach title must NOT leak');

        // Amount shown is THIS coach's subtotal (100), not the combined 150.
        $this->assertTrue($o->is_multi_coach, 'order spans coaches => flagged mixed');
        $this->assertEquals(100.0, (float) $o->display_amount, 'amount must be coach subtotal, not combined total');
        $this->assertEquals(2, (int) $o->order_items_count, 'withCount must see all (un-scoped) items');
    }

    public function test_single_coach_order_shows_real_paid_amount_unchanged(): void
    {
        $coachA = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $student = User::factory()->create(['role' => 'student']);
        $courseA = $this->makeCourse($coachA->id, 'Solo Course', 80);

        // Single-coach order with a coupon: paid_amount (72) deliberately
        // differs from item price (80) to prove we DON'T clobber it.
        $order = Order::create([
            'buyer_id' => $student->id, 'status' => 'completed',
            'payment_status' => 'paid', 'currency' => 'INR',
            'paid_amount' => 72, 'payable_amount' => 80,
            'gateway_charge' => 0, 'coupon_discount_amount' => 8, 'commission_rate' => 0,
        ]);
        OrderItem::create(['order_id' => $order->id, 'course_id' => $courseA, 'price' => 80, 'qty' => 1, 'commission_rate' => 0]);

        $view = $this->renderOrdersFor($coachA, $student);
        $o = $view->getData()['orders']->first();

        $this->assertFalse($o->is_multi_coach, 'single-coach order is not mixed');
        $this->assertEquals(72.0, (float) $o->display_amount, 'single-coach order keeps real paid_amount (coupon-adjusted)');
    }
}
