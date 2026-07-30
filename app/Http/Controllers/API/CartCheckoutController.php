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
 * Multi-course cart checkout via Razorpay.
 *
 * Mirrors Api\CoursePurchaseController but spans every item in the
 * user's cart. One Order row, N OrderItem rows (one per course), one
 * Razorpay order. Verify hands off to PaymentFulfilmentService::markPaid
 * which atomically creates an Enrollment per OrderItem + credits each
 * instructor's wallet.
 *
 * Routes (auth:sanctum):
 *   POST /api/cart/checkout/create-order  — body: { coupon?: string }
 *   POST /api/cart/checkout/verify/{order}
 */
class CartCheckoutController extends Controller
{
    use GetGlobalInformationTrait;

    public function __construct(private PaymentFulfilmentService $fulfilment) {}

    /**
     * Preview-only quote — same filter + math as createOrder, but
     * doesn't create an Order row or hit Razorpay. The mobile cart
     * uses this on load (and on coupon apply/remove) so the user
     * sees the real payable amount before tapping Pay.
     *
     * Body:  { coupon?: string }
     * Returns: { items[], owned_total, raw_total, payable_total,
     *            coupon, discount_amount, final_total, currency }
     */
    public function quote(Request $request): JsonResponse
    {
        $user = $request->user();

        $cartCourses = Course::select('id', 'slug', 'title', 'price', 'discount')
            ->active()
            ->whereHas('carts', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $alreadyEnrolledIds = DB::table('enrollments')
            ->where('user_id', $user->id)
            ->where('has_access', 1)
            ->pluck('course_id')
            ->all();

        $items        = [];
        $rawTotal     = 0.0;
        $payableTotal = 0.0;
        $ownedTotal   = 0.0;
        foreach ($cartCourses as $c) {
            $price = $this->effectivePrice($c);
            $owned = in_array($c->id, $alreadyEnrolledIds, true);
            $rawTotal += $price;
            if ($owned) {
                $ownedTotal += $price;
            } elseif ($price > 0) {
                $payableTotal += $price;
            }
            $items[] = [
                'course_id' => $c->id,
                'slug'      => $c->slug,
                'title'     => $c->title,
                'price'     => $price,
                'owned'     => $owned,
                'free'      => $price <= 0,
            ];
        }

        $couponInput = trim((string) $request->input('coupon', ''));
        $coupon = null;
        $couponError = null;
        if ($couponInput !== '' && $payableTotal > 0) {
            [$coupon, $couponError] = $this->resolveCoupon($couponInput, $user->id, $payableTotal);
        }
        $discountAmount  = $coupon ? round($payableTotal * ((float) $coupon->offer_percentage) / 100, 2) : 0.0;
        $discountPercent = $coupon ? (int) round((float) $coupon->offer_percentage) : 0;
        $finalTotal      = max(0.0, round($payableTotal - $discountAmount, 2));

        return response()->json([
            'status' => 'success',
            'data'   => [
                'currency'         => Cache::get('setting')?->currency_code ?? 'INR',
                'items'            => $items,
                'raw_total'        => round($rawTotal, 2),
                'owned_total'      => round($ownedTotal, 2),
                'payable_total'    => round($payableTotal, 2),
                'coupon'           => $coupon ? [
                    'code'             => $coupon->coupon_code,
                    'offer_percentage' => (float) $coupon->offer_percentage,
                    'discount_amount'  => $discountAmount,
                ] : null,
                'coupon_error'     => $couponError,
                'discount_percent' => $discountPercent,
                'discount_amount'  => $discountAmount,
                'final_total'      => $finalTotal,
            ],
        ]);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $user = $request->user();

        // Load the cart's courses with the same shape CartController uses.
        $cartCourses = Course::select('id', 'slug', 'title', 'instructor_id', 'price', 'discount', 'user_id')
            ->active()
            ->whereHas('carts', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        if ($cartCourses->isEmpty()) {
            return response()->json([
                'status' => 'error', 'message' => 'Your cart is empty.',
            ], 422);
        }

        // Filter out anything the user already owns (defensive — they
        // shouldn't end up paying twice for the same course because of a
        // stale cart row). Free courses are also skipped — they don't
        // belong in a paid checkout flow.
        $alreadyEnrolledIds = DB::table('enrollments')
            ->where('user_id', $user->id)
            ->where('has_access', 1)
            ->pluck('course_id')
            ->all();

        $items = [];
        $rawTotal = 0.0;
        foreach ($cartCourses as $c) {
            if (in_array($c->id, $alreadyEnrolledIds, true)) continue;
            $price = $this->effectivePrice($c);
            if ($price <= 0) continue;
            $items[] = ['course' => $c, 'price' => $price];
            $rawTotal += $price;
        }

        if (empty($items) || $rawTotal <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No paid courses to check out. Remove free/already-owned items from your cart.',
            ], 422);
        }

        // TENANT HARD-BLOCK (audit M4) — if this API request is served on a coach
        // white-label host, every course in the order must belong to that coach.
        // No-op on the platform/api host (apiSurfaceCoachId 0).
        $apiCoachId = \App\Support\TenantAccess::apiSurfaceCoachId($request);
        if ($apiCoachId > 0) {
            foreach ($items as $it) {
                if ((int) ($it['course']->instructor_id ?? 0) !== $apiCoachId) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Your cart has courses from another instructor.',
                    ], 422);
                }
            }
        }

        // Optional coupon — same rules as the single-course flow. Coach-private
        // coupons are scoped to the surface coach (audit M2).
        $couponInput = trim((string) $request->input('coupon', ''));
        $coupon = null;
        if ($couponInput !== '') {
            [$coupon, $couponError] = $this->resolveCoupon($couponInput, $user->id, $rawTotal, $apiCoachId);
            if (!$coupon) {
                return response()->json([
                    'status' => 'error', 'message' => $couponError ?? 'Invalid coupon',
                ], 422);
            }
        }

        $discountAmount  = $coupon ? round($rawTotal * ((float) $coupon->offer_percentage) / 100, 2) : 0.0;
        $discountPercent = $coupon ? (int) round((float) $coupon->offer_percentage) : 0;
        $finalTotal      = max(0.0, round($rawTotal - $discountAmount, 2));

        // Coach-specific gateway resolution (2026-06-29). A coach gateway is used
        // ONLY when every item belongs to the same coach; a mixed multi-coach cart
        // falls back to the platform default (one charge can't split across
        // merchant accounts).
        $ownerIds = array_map(static fn ($i) => (int) ($i['course']->instructor_id ?? 0), $items);
        $resolved = app(\App\Services\Payment\CheckoutGatewayResolver::class)
            ->resolveForOwners($ownerIds, 'razorpay');
        $creds = $this->rzpCreds($resolved);
        if (empty($creds['key']) || empty($creds['secret'])) {
            return response()->json([
                'status' => 'error', 'message' => 'Razorpay is not configured. Contact support.',
            ], 503);
        }

        $commissionRate = (int) (Cache::get('setting')?->commission_rate ?? 0);
        $currency       = Cache::get('setting')?->currency_code ?? 'INR';

        try {
            $order = DB::transaction(function () use (
                $user, $items, $rawTotal, $finalTotal, $commissionRate, $currency,
                $coupon, $discountPercent, $discountAmount, $resolved
            ) {
                // seller_id is the first instructor on the cart — Order
                // is single-seller in the schema; bundle semantics aren't
                // a thing here. Per-item commission is still tracked on
                // OrderItem so multi-instructor carts settle correctly.
                $firstItem = $items[0];
                $order = Order::create([
                    'invoice_id'              => Str::random(10),
                    'buyer_id'                => $user->id,
                    'seller_id'               => $firstItem['course']->user_id,
                    'status'                  => 'pending',
                    'has_coupon'              => $coupon ? 1 : 0,
                    'coupon_code'             => $coupon?->coupon_code,
                    'coupon_discount_percent' => $coupon ? $discountPercent : null,
                    'coupon_discount_amount'  => $coupon ? $discountAmount : null,
                    'payment_method'          => 'Razorpay',
                    'payment_status'          => 'pending',
                    'payable_amount'          => $finalTotal,
                    'gateway_charge'          => 0,
                    'payable_with_charge'     => $finalTotal,
                    'paid_amount'             => $finalTotal,
                    'conversion_rate'         => 1,
                    'payable_currency'        => $currency,
                    'commission_rate'         => $commissionRate,
                    'order_type'              => 'course',
                    // Gateway provenance for tenant-aware verification (2026-06-29).
                    'gateway_owner_type'      => $resolved->ownerType,
                    'gateway_config_id'       => $resolved->configId,
                    'gateway_coach_id'        => $resolved->coachId,
                ]);

                foreach ($items as $i) {
                    OrderItem::create([
                        'order_id'        => $order->id,
                        'qty'             => 1,
                        'price'           => $i['price'],
                        'item_type'       => 'course',
                        'course_id'       => $i['course']->id,
                        'commission_rate' => $commissionRate,
                    ]);
                }

                return $order;
            });
        } catch (\Throwable $e) {
            \Log::error('API cart checkout: order create failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error', 'message' => 'Could not start checkout.',
            ], 500);
        }

        $amountInSubunits = (int) round($finalTotal * 100);
        try {
            $api = new Api($creds['key'], $creds['secret']);
            $rzpOrder = $api->order->create([
                'receipt'  => 'cart-' . $order->id,
                'amount'   => $amountInSubunits,
                'currency' => $currency,
                'notes'    => [
                    'order_id'    => (string) $order->id,
                    'user_id'     => (string) $user->id,
                    'item_count'  => (string) count($items),
                    'source'      => 'android-cart',
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('API cart checkout: Razorpay order create failed: ' . $e->getMessage());
            $order->update(['status' => 'declined', 'payment_status' => 'failed']);
            return response()->json([
                'status' => 'error', 'message' => 'Could not initiate payment. Try again later.',
            ], 502);
        }

        $order->transaction_id = $rzpOrder['id'];
        $order->save();

        $itemCount = count($items);
        return response()->json([
            'status' => 'success',
            'data'   => [
                'order_id'           => $order->id,
                'razorpay_key'       => $creds['key'],
                'razorpay_order_id'  => $rzpOrder['id'],
                'amount_in_subunits' => $amountInSubunits,
                'currency'           => $currency,
                'course_title'       => $itemCount === 1
                    ? $items[0]['course']->title
                    : "$itemCount courses",
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

        if ($request->razorpay_order_id !== $order->transaction_id) {
            return response()->json([
                'status' => 'error', 'message' => 'Order mismatch.',
            ], 422);
        }

        // Re-resolve the exact gateway the order was created with from stored
        // metadata (never session), so verification uses the same credentials.
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

            $order->payment_verified_at = now();
            $order->save();

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

            // Clear the user's cart now that everything's enrolled.
            DB::table('carts')->where('user_id', $request->user()->id)
                ->whereIn('course_id', $order->orderItems()->pluck('course_id'))
                ->delete();

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'order_id'   => $order->id,
                    'invoice_id' => $order->invoice_id,
                ],
                'message' => 'Payment successful — you have been enrolled.',
            ]);

        } catch (\Throwable $e) {
            \Log::error("API cart checkout verify failed for order #{$order->id}: " . $e->getMessage());
            $order->update(['payment_status' => 'failed']);
            return response()->json([
                'status' => 'error', 'message' => 'Payment verification failed. Contact support if you were charged.',
            ], 422);
        }
    }

    /* ─── Helpers (duplicated from CoursePurchaseController for clarity) ─ */

    private function effectivePrice(Course $course): float
    {
        $price    = (float) ($course->price ?? 0);
        $discount = (float) ($course->discount ?? 0);
        return ($discount > 0 && $discount < $price) ? $discount : $price;
    }

    private function resolveCoupon(string $code, int $userId, float $cartTotal, int $tenantCoachId = 0): array
    {
        $coupon = DB::table('coupons')
            ->where('coupon_code', $code)
            ->where('status', 'active')
            ->first();
        if (!$coupon) return [null, 'This coupon code is not valid.'];

        // Per-coach coupon scope (audit M2) — a coach-private coupon (coach_id
        // set) is only valid on that coach's surface; same generic message so
        // another coach's code isn't enumerable.
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

    /** Extract the Razorpay key/secret from a resolved (coach or default) gateway. */
    private function rzpCreds(\App\Services\Payment\ResolvedGateway $g): array
    {
        return [
            'key'    => (string) $g->credential('razorpay_key', ''),
            'secret' => (string) $g->credential('razorpay_secret', ''),
        ];
    }
}
