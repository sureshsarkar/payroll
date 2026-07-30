<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\PaymentFulfilmentService;
use App\Traits\GetGlobalInformationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Razorpay\Api\Api;

/**
 * Buy-now flow for a single paid course via Razorpay.
 *
 * Distinct from the web checkout, which is cart-based and creates
 * Razorpay orders client-side. The mobile flow is server-led:
 *
 *   1. /api/course/buy/{slug}/quote   — read-only price preview
 *   2. /api/course/buy/{slug}/create-order
 *       - validate slug + price + not-already-enrolled
 *       - create pending Order + OrderItem
 *       - create Razorpay order, stash its id on Order.transaction_id
 *       - return order id + razorpay order id + amount + key
 *   3. /api/course/buy/verify/{order}
 *       - verify signature, capture, hand off to
 *         PaymentFulfilmentService::markPaid which is idempotent and
 *         already handles Enrollment::firstOrCreate + instructor wallet
 *         credit + coupon usage tracking
 *
 * Coupons are deliberately out-of-scope for v1 of this flow — they need
 * cart-level UX which the mobile app doesn't have yet. A follow-up
 * phase will add a coupon-code field in the quote step.
 */
class CoursePurchaseController extends Controller
{
    use GetGlobalInformationTrait;

    public function __construct(private PaymentFulfilmentService $fulfilment) {}

    public function quote(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $course = Course::where('slug', $slug)->first();
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found'], 404);
        }
        if ($this->isAlreadyEnrolled($user->id, $course->id)) {
            return response()->json([
                'status' => 'error', 'message' => 'You are already enrolled in this course.',
            ], 409);
        }

        $price = $this->effectivePrice($course);
        if ($price <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This is a free course. Use the free-enroll endpoint.',
            ], 422);
        }

        // Optional coupon — if provided we apply the same rules the web
        // cart applies (expiry, status, min_price, usage_limit, per_user_limit).
        $couponInput = trim((string) $request->query('coupon', ''));
        $coupon = null;
        $couponError = null;
        if ($couponInput !== '') {
            [$coupon, $couponError] = $this->resolveCoupon($couponInput, $user->id, $price, (int) $course->instructor_id);
        }

        $discountAmount = 0.0;
        $discountPercent = 0;
        if ($coupon) {
            $discountPercent = (int) round((float) $coupon->offer_percentage);
            $discountAmount = round($price * ((float) $coupon->offer_percentage) / 100, 2);
        }
        $finalPrice = max(0.0, round($price - $discountAmount, 2));

        return response()->json([
            'status' => 'success',
            'data'   => [
                'course_id'         => $course->id,
                'course_title'      => $course->title,
                'price'             => $price,
                'currency'          => Cache::get('setting')?->currency_code ?? 'INR',
                'coupon'            => $coupon ? [
                    'code'             => $coupon->coupon_code,
                    'offer_percentage' => (float) $coupon->offer_percentage,
                    'discount_amount'  => $discountAmount,
                ] : null,
                'coupon_error'      => $couponError,
                'final_price'       => $finalPrice,
                'discount_percent'  => $discountPercent,
                'discount_amount'   => $discountAmount,
            ],
        ]);
    }

    public function createOrder(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $course = Course::where('slug', $slug)->first();
        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Course not found'], 404);
        }
        if ($this->isAlreadyEnrolled($user->id, $course->id)) {
            return response()->json([
                'status' => 'error', 'message' => 'You are already enrolled in this course.',
            ], 409);
        }

        $price = $this->effectivePrice($course);
        if ($price <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This is a free course. Use the free-enroll endpoint.',
            ], 422);
        }

        // Optional coupon — same rules the web cart applies. Single-course flow:
        // the coupon's coach context is this course's owning coach (audit M2).
        $couponInput = trim((string) $request->input('coupon', ''));
        $coupon = null;
        if ($couponInput !== '') {
            [$coupon, $couponError] = $this->resolveCoupon($couponInput, $user->id, $price, (int) $course->instructor_id);
            if (!$coupon) {
                return response()->json([
                    'status' => 'error', 'message' => $couponError ?? 'Invalid coupon',
                ], 422);
            }
        }

        $discountAmount  = $coupon ? round($price * ((float) $coupon->offer_percentage) / 100, 2) : 0.0;
        $discountPercent = $coupon ? (int) round((float) $coupon->offer_percentage) : 0;
        $finalPrice      = max(0.0, round($price - $discountAmount, 2));

        // Coach-specific gateway resolution (2026-06-29). A single-course buy is
        // by definition single-coach (the course owner), so resolve directly
        // against it. Falls back to the platform default when the coach has no
        // active/usable config — behaviour then identical to before.
        $resolved = app(\App\Services\Payment\PaymentGatewayResolverService::class)
            ->resolve((int) $course->instructor_id, 'razorpay');
        $creds = $this->rzpCreds($resolved);
        if (empty($creds['key']) || empty($creds['secret'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Razorpay is not configured. Contact support.',
            ], 503);
        }

        $commissionRate = (int) (Cache::get('setting')?->commission_rate ?? 0);
        $currency       = Cache::get('setting')?->currency_code ?? 'INR';

        // Create the pending Order + OrderItem first so the row exists
        // before we hand off to Razorpay. We then patch the row with the
        // Razorpay order id once the SDK call returns.
        try {
            $order = DB::transaction(function () use (
                $user, $course, $price, $finalPrice, $commissionRate, $currency,
                $coupon, $discountPercent, $discountAmount, $resolved
            ) {
                $order = Order::create([
                    'invoice_id'              => Str::random(10),
                    'buyer_id'                => $user->id,
                    'seller_id'               => $course->user_id,
                    'status'                  => 'pending',
                    'has_coupon'              => $coupon ? 1 : 0,
                    'coupon_code'             => $coupon?->coupon_code,
                    'coupon_discount_percent' => $coupon ? $discountPercent : null,
                    'coupon_discount_amount'  => $coupon ? $discountAmount : null,
                    'payment_method'          => 'Razorpay',
                    'payment_status'          => 'pending',
                    'payable_amount'          => $finalPrice,
                    'gateway_charge'          => 0,
                    'payable_with_charge'     => $finalPrice,
                    'paid_amount'             => $finalPrice,
                    'conversion_rate'         => 1,
                    'payable_currency'        => $currency,
                    'commission_rate'         => $commissionRate,
                    'order_type'              => 'course',
                    // Gateway provenance for tenant-aware verification (2026-06-29).
                    'gateway_owner_type'      => $resolved->ownerType,
                    'gateway_config_id'       => $resolved->configId,
                    'gateway_coach_id'        => $resolved->coachId,
                ]);

                OrderItem::create([
                    'order_id'        => $order->id,
                    'qty'             => 1,
                    'price'           => $price,
                    'item_type'       => 'course',
                    'course_id'       => $course->id,
                    'commission_rate' => $commissionRate,
                ]);

                return $order;
            });
        } catch (\Throwable $e) {
            \Log::error('API course buy: order create failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error', 'message' => 'Could not start checkout.',
            ], 500);
        }

        // Now create the Razorpay order. If this fails we leave the pending
        // Order row in place — admin can clean it up; the user can retry
        // safely because we only create a new Order on each retry-create
        // call (idempotency is not a goal at the order-creation layer).
        //
        // Use `$finalPrice` (post-coupon) for the SDK so the user sees the
        // discounted amount in the Razorpay dialog.
        $amountInSubunits = (int) round($finalPrice * 100);
        try {
            $api = new Api($creds['key'], $creds['secret']);
            $rzpOrder = $api->order->create([
                'receipt'  => 'crs-' . $order->id,
                'amount'   => $amountInSubunits,
                'currency' => $currency,
                'notes'    => [
                    'order_id'  => (string) $order->id,
                    'course_id' => (string) $course->id,
                    'user_id'   => (string) $user->id,
                    'source'    => 'android',
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('API course buy: Razorpay order create failed: ' . $e->getMessage());
            $order->update(['status' => 'declined', 'payment_status' => 'failed']);
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not initiate payment. Try again later.',
            ], 502);
        }

        // Stash the Razorpay order id on transaction_id for matching at
        // verify time. PaymentFulfilmentService::markPaid will replace
        // this with the captured payment id on success.
        $order->transaction_id = $rzpOrder['id'];
        $order->save();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'order_id'           => $order->id,
                'razorpay_key'       => $creds['key'],
                'razorpay_order_id'  => $rzpOrder['id'],
                'amount_in_subunits' => $amountInSubunits,
                'currency'           => $currency,
                'course_title'       => $course->title,
                'prefill' => [
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'contact' => (string) ($user->phone ?? ''),
                ],
            ],
        ]);
    }

    public function verify(Request $request, int $orderId): JsonResponse
    {
        $order = Order::where('buyer_id', $request->user()->id)->find($orderId);
        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
        }

        $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id'   => ['required', 'string'],
            'razorpay_signature'  => ['required', 'string'],
        ]);

        // Replay / cross-order guard — the inbound rzp order_id must match
        // what we stashed during createOrder.
        if ($request->razorpay_order_id !== $order->transaction_id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order mismatch.',
            ], 422);
        }

        // Re-resolve the EXACT gateway the order was created with (coach-owned
        // or platform default) from the stored metadata — never session state —
        // so the signature is verified against the same credentials.
        $resolved = app(\App\Services\Payment\PaymentGatewayResolverService::class)
            ->resolveForOrder($order);
        $creds = $this->rzpCreds($resolved);
        try {
            $api = new Api($creds['key'], $creds['secret']);

            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ]);

            $payment = $api->payment->fetch($request->razorpay_payment_id);
            if ($payment->status !== 'captured') {
                $payment->capture(['amount' => $payment->amount]);
            }

            // Sync-callback verification succeeded — stamp it for audit.
            $order->payment_verified_at = now();
            $order->save();

            // Hand off to the shared idempotent fulfilment path. This
            // creates Enrollment rows, credits instructor wallets, and
            // records coupon usage — same as the web success URL.
            $this->fulfilment->markPaid(
                sessionOrder: $order,
                transactionId: $payment->id,
                paymentDetails: [
                    'gateway'    => 'razorpay',
                    'payment_id' => $payment->id,
                    'order_id'   => $payment->order_id ?? null,
                    'amount'     => $payment->amount,
                    'currency'   => $payment->currency,
                    'method'     => $payment->method ?? null,
                    'status'     => $payment->status,
                ],
            );

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'order_id'    => $order->id,
                    'course_id'   => $order->orderItems()->first()?->course_id,
                    'invoice_id'  => $order->invoice_id,
                ],
                'message' => 'Payment successful — you have been enrolled.',
            ]);

        } catch (\Throwable $e) {
            \Log::error("API course buy verify failed for order #{$order->id}: " . $e->getMessage());
            $order->update(['payment_status' => 'failed']);
            return response()->json([
                'status'  => 'error',
                'message' => 'Payment verification failed. Contact support if you were charged.',
            ], 422);
        }
    }

    /* ─── Helpers ─────────────────────────────────────────────────────── */

    private function effectivePrice(Course $course): float
    {
        $price    = (float) ($course->price ?? 0);
        $discount = (float) ($course->discount ?? 0);
        return ($discount > 0 && $discount < $price) ? $discount : $price;
    }

    private function isAlreadyEnrolled(int $userId, int $courseId): bool
    {
        return DB::table('enrollments')
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('has_access', 1)
            ->exists();
    }

    /** Extract the Razorpay key/secret from a resolved (coach or default) gateway. */
    private function rzpCreds(\App\Services\Payment\ResolvedGateway $g): array
    {
        return [
            'key'    => (string) $g->credential('razorpay_key', ''),
            'secret' => (string) $g->credential('razorpay_secret', ''),
        ];
    }

    /**
     * Look up + validate a coupon code. Returns [coupon, null] on success,
     * [null, "human reason"] on failure. Mirrors the web cart's
     * applyCoupon rules so behaviour matches across surfaces.
     *
     *   - status must be 'active'
     *   - expired_date must be today or future
     *   - cart total must be >= min_price
     *   - usage_count must be < usage_limit (if set)
     *   - per-user uses must be < per_user_limit (if set)
     */
    private function resolveCoupon(string $code, int $userId, float $cartTotal, int $tenantCoachId = 0): array
    {
        $coupon = DB::table('coupons')
            ->where('coupon_code', $code)
            ->where('status', 'active')
            ->first();
        if (!$coupon) return [null, 'This coupon code is not valid.'];

        // Per-coach coupon scope (audit M2) — a coach-private coupon is valid
        // only for that coach; generic message so it isn't enumerable.
        $couponCoach = (int) ($coupon->coach_id ?? 0);
        if ($couponCoach !== 0 && $couponCoach !== $tenantCoachId) {
            return [null, 'This coupon code is not valid.'];
        }

        if ($coupon->expired_date && strtotime($coupon->expired_date) < strtotime(date('Y-m-d'))) {
            return [null, 'This coupon has expired.'];
        }
        if (!empty($coupon->min_price) && $cartTotal < (float) $coupon->min_price) {
            return [null, 'Minimum order total ' . $coupon->min_price . ' required for this coupon.'];
        }
        if ($coupon->usage_limit !== null && (int) $coupon->usage_count >= (int) $coupon->usage_limit) {
            return [null, 'This coupon has reached its global usage limit.'];
        }
        if ($coupon->per_user_limit !== null) {
            $used = DB::table('coupon_uses')
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();
            if ($used >= (int) $coupon->per_user_limit) {
                return [null, 'You\'ve already used this coupon the maximum number of times.'];
            }
        }
        return [$coupon, null];
    }
}
