<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\API\CoachDashboardController;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Tests\TestCase;

/**
 * Instructor Dashboard API — the 5 endpoints that were 404-ing in production.
 *
 * Root causes fixed: (a) sales/trend, account/sessions, withdraw-requests were
 * never implemented; sales/{id} GET was commented out; (b) students + sales
 * returned 404 on an EMPTY result instead of an empty JSON list. All endpoints
 * are scoped to the authenticated instructor.
 */
class InstructorDashboardApiTest extends TestCase
{
    use DatabaseTransactions;

    private CoachDashboardController $ctrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctrl = app(CoachDashboardController::class);
    }

    private function coach(): User
    {
        $id = DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach ' . uniqid(), 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::find($id);
    }

    private function actAs(User $u): void
    {
        $this->actingAs($u);                  // auth()->user()
        app()->instance('request', Request::create('/', 'GET'));
    }

    private function paidSale(User $coach): array
    {
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'C', 'slug' => 'c' . uniqid(), 'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'recorded', 'price' => 1000, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $buyer = $this->coach(); // any user as buyer
        $order = Order::create([
            'invoice_id' => 'INV' . uniqid(), 'buyer_id' => $buyer->id, 'seller_id' => $coach->id,
            'status' => 'completed', 'payment_method' => 'razorpay', 'payment_status' => 'paid',
            'payable_amount' => 1000, 'paid_amount' => 1000, 'payable_currency' => 'INR',
        ]);
        $item = OrderItem::create(['order_id' => $order->id, 'qty' => 1, 'price' => 1000, 'item_type' => 'course', 'course_id' => $courseId]);
        return [$courseId, $item->id];
    }

    /* ---- empty results must be 200 (not 404) ---- */

    public function test_students_empty_returns_200_not_404(): void
    {
        $coach = $this->coach();
        $this->actAs($coach);
        $res = $this->ctrl->students(Request::create('/', 'GET'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('success', $res->getData()->status);
        $this->assertSame([], (array) $res->getData()->data);
    }

    public function test_sales_empty_returns_200_not_404(): void
    {
        $coach = $this->coach();
        $this->actAs($coach);
        $res = $this->ctrl->my_sells(Request::create('/', 'GET'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('success', $res->getData()->status);
        $this->assertSame([], (array) $res->getData()->data);
    }

    /* ---- new endpoints ---- */

    public function test_sales_trend_returns_continuous_series_with_revenue(): void
    {
        $coach = $this->coach();
        $this->paidSale($coach);
        $this->actAs($coach);

        $res = $this->ctrl->sales_trend(Request::create('/', 'GET', ['months' => 12]));
        $this->assertSame(200, $res->getStatusCode());
        $d = $res->getData();
        $this->assertCount(12, $d->data);                       // 12 months, zero-filled
        $this->assertSame(1000.0, (float) $d->summary->total_revenue); // the paid sale counted
    }

    public function test_sales_show_returns_owned_sale_and_404_for_foreign(): void
    {
        $coach = $this->coach();
        [, $saleId] = $this->paidSale($coach);

        $this->actAs($coach);
        $ok = $this->ctrl->my_sells_show($saleId);
        $this->assertSame(200, $ok->getStatusCode());
        // App contract: data root carries sale + course + buyer siblings.
        $this->assertSame($saleId, $ok->getData()->data->sale->id);
        $this->assertNotNull($ok->getData()->data->sale->buyer);    // buyer (Order::user) resolves
        $this->assertNotNull($ok->getData()->data->course);         // data.course sibling present
        $this->assertNotNull($ok->getData()->data->buyer);          // data.buyer sibling present

        // The LIST endpoint must also surface the sale + buyer (the prior `->buyer`
        // relation bug would have 500'd this the moment a sale existed).
        $list = $this->ctrl->my_sells(Request::create('/', 'GET'));
        $this->assertSame(200, $list->getStatusCode());
        $this->assertCount(1, (array) $list->getData()->data);
        $this->assertNotNull($list->getData()->data[0]->buyer);

        // Another coach must NOT be able to read this sale (tenant isolation).
        $other = $this->coach();
        $this->actAs($other);
        $this->assertSame(404, $this->ctrl->my_sells_show($saleId)->getStatusCode());
    }

    public function test_account_sessions_returns_200_list(): void
    {
        $coach = $this->coach();
        $this->actAs($coach);
        $res = $this->ctrl->account_sessions(Request::create('/', 'GET'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('success', $res->getData()->status);
        // App contract: data is an OBJECT with sessions nested under data.sessions.
        $this->assertIsArray((array) $res->getData()->data->sessions);  // [] when no tokens — still 200
    }

    public function test_withdraw_requests_scoped_and_empty_is_200(): void
    {
        $coach = $this->coach();
        $other = $this->coach();
        // a withdraw for ANOTHER coach must never appear for this coach.
        DB::table('withdraw_requests')->insert([
            'user_id' => $other->id, 'method' => 'paytm', 'current_amount' => 0, 'withdraw_amount' => 500,
            'account_info' => \Illuminate\Support\Facades\Crypt::encryptString('{}'), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actAs($coach);
        $res = $this->ctrl->withdraw_requests(Request::create('/', 'GET'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame([], (array) $res->getData()->data);   // empty → 200, and NOT the other coach's row

        // own request shows up
        DB::table('withdraw_requests')->insert([
            'user_id' => $coach->id, 'method' => 'paytm', 'current_amount' => 0, 'withdraw_amount' => 250,
            'account_info' => \Illuminate\Support\Facades\Crypt::encryptString('{}'), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $res2 = $this->ctrl->withdraw_requests(Request::create('/', 'GET'));
        $this->assertCount(1, (array) $res2->getData()->data);
        $this->assertSame(250.0, (float) $res2->getData()->data[0]->amount);
    }
}
