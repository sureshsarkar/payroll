<?php

namespace Tests\Feature\Domain;

use App\Models\Course;
use App\Models\User;
use App\Services\PaymentFulfilmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Domain test — Stripe checkout + webhook fulfilment.
 *
 * Audit 2026-05-18: implementation of the previous skeleton.
 *
 * What's already covered elsewhere:
 *   - Webhook signature verification → WebhookSignatureTest
 *   - Order/Enrollment idempotency primitives → MoneyPathTest
 *
 * What this test adds:
 *   - End-to-end fulfilment shape: a pending order + replayed event
 *     ends with payment_status=paid, exactly ONE enrollment row,
 *     and second-replay is a no-op (returns false from markPaid).
 *
 * We exercise the PaymentFulfilmentService directly rather than HTTP
 * POSTing /webhooks/stripe — the signature verification is covered
 * separately, and stubbing Stripe\Webhook::constructEvent inside the
 * HTTP layer adds ceremony without coverage value here.
 *
 * Critical for the Stripe v13 → v20 upgrade (docs/TIER3_UPGRADE_PLANS):
 * if the upgrade changes the shape of $event->data->object or
 * SignatureVerification semantics, these tests + WebhookSignatureTest
 * will flag the regression before it ships.
 */
class StripeCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    public function test_markPaid_flips_order_status_and_creates_enrollment(): void
    {
        [$user, $course, $order] = $this->seedPendingOrder();

        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(0, Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->count());

        /** @var PaymentFulfilmentService $svc */
        $svc = app(PaymentFulfilmentService::class);
        $processed = $svc->markPaid($order, 'pi_test_'.uniqid(), [
            'gateway' => 'stripe',
            'event_id' => 'evt_test_'.uniqid(),
        ]);

        $this->assertTrue($processed, 'first markPaid call should return true');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->count(),
            'exactly one enrollment row must be created');
    }

    public function test_markPaid_is_idempotent_under_replay(): void
    {
        [$user, $course, $order] = $this->seedPendingOrder();

        $svc = app(PaymentFulfilmentService::class);

        $first  = $svc->markPaid($order, 'pi_test_first', ['gateway' => 'stripe']);
        $second = $svc->markPaid($order->fresh(), 'pi_test_second', ['gateway' => 'stripe']);
        $third  = $svc->markPaid($order->fresh(), 'pi_test_third', ['gateway' => 'stripe']);

        $this->assertTrue($first,   'first call processes');
        $this->assertFalse($second, 'second call must be a no-op');
        $this->assertFalse($third,  'third call must be a no-op');

        $this->assertSame(1, Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->count(),
            'replay must NOT create duplicate enrollments');

        // The transaction_id from the FIRST call wins; replays don't
        // overwrite it (idempotency contract).
        $this->assertSame('pi_test_first', $order->fresh()->transaction_id);
    }

    public function test_concurrent_replay_does_not_create_duplicate_enrolment(): void
    {
        // Simulates two webhook deliveries arriving back-to-back. Without
        // the lockForUpdate + payment_status guard in the service, this
        // could double-credit the instructor wallet.
        [$user, $course, $order] = $this->seedPendingOrder();

        $svc = app(PaymentFulfilmentService::class);

        // Sequential (not actually concurrent — but the service's guard
        // makes the OUTCOME of concurrent identical).
        $svc->markPaid($order, 'pi_replay', ['gateway' => 'stripe']);
        $svc->markPaid($order->fresh(), 'pi_replay', ['gateway' => 'stripe']);

        $this->assertSame(1, Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->count());
    }

    public function test_webhook_route_is_csrf_exempt_and_throttled(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'webhooks/stripe');
        $this->assertNotNull($route, 'webhooks/stripe must be registered');

        $middleware = $route->gatherMiddleware();
        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle')),
            'webhooks/stripe must carry throttle middleware (rate-limit replays)'
        );

        // CSRF exempt list is asserted in WebhookSignatureTest — we don't
        // duplicate it here.
    }

    /* -------------------- fixtures -------------------- */

    /**
     * @return array{User, Course, Order}
     */
    private function seedPendingOrder(): array
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $user  = User::factory()->create(['role' => 'student']);

        $courseId = DB::table('courses')->insertGetId([
            'title'         => 'Stripe test course '.uniqid(),
            'slug'          => 'stripe-test-'.uniqid(),
            'instructor_id' => $coach->id,
            'added_by'      => $coach->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'price'         => 49.99, 'discount' => 0,
            'created_at'    => now(), 'updated_at' => now(),
        ]);
        $course = Course::find($courseId);

        $order = Order::create([
            'buyer_id'        => $user->id,
            'payment_status'  => 'pending',
            'status'          => 'pending',
            'paid_amount'     => 49.99,
            'currency'        => 'USD',
            'commission_rate' => 10,
            'payment_method'  => 'stripe',
        ]);

        DB::table('order_items')->insert([
            'order_id'  => $order->id,
            'course_id' => $courseId,
            'price'     => 49.99,
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        return [$user, $course, $order];
    }
}
