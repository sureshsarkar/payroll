<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Traits\GetGlobalInformationTrait;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Session;

class CheckOutController extends Controller
{
    use GetGlobalInformationTrait;
    function index()
    {
        $user = userAuth();
        $cart_count = $user->cart_count;

        if($cart_count == 0){
            return redirect()->route('courses')->with(['messege' => __('Please add some courses in your cart.'), 'alert-type' => 'error']);
        }

        // FT-LOGIC-2 fix (2026-05-28) — coupon re-validation at checkout.
        //
        // Pre-fix: this method reads offer_percentage/coupon_code from
        // session directly. The buyer applies a coupon (session populated),
        // an admin deactivates it before the buyer pays, and the buyer
        // hits /checkout. payable_amount is then computed from the STALE
        // session value, the payment gateway charges the discounted
        // amount, and the Order row is later created with the now-invalid
        // coupon attached.
        //
        // FT-LOGIC-1 (earlier in this branch) added the same re-check on
        // CartController::updateCouponDiscountAmount() for the cart-page
        // update path. The checkout entry was the other unguarded path.
        // Re-validate by looking up the coupon row fresh; if it's missing
        // or inactive, flush every session key the payment flow consumes
        // so the buyer pays full price.
        if (Session::has('coupon_code')) {
            $coupon = \Modules\Coupon\app\Models\Coupon::where([
                'coupon_code' => Session::get('coupon_code'),
                'status'      => 'active',
            ])->first();
            $expired = $coupon && $coupon->expired_date && $coupon->expired_date < date('Y-m-d');
            if (! $coupon || $expired) {
                Session::forget('coupon_code');
                Session::forget('offer_percentage');
                Session::forget('coupon_discount_amount');
            }
        }

        $cartTotal = $user->cart_total;
        $discountPercent = Session::has('offer_percentage') ? Session::get('offer_percentage') : 0;
        $discountAmount = ($cartTotal * $discountPercent) / 100;
        $total = currency($cartTotal - $discountAmount);
        $coupon = Session::has('coupon_code') ? Session::get('coupon_code') : '';

        $payable_amount = $cartTotal - $discountAmount;
        Session::put('payable_amount', $payable_amount);

        $paymentService = app(\Modules\BasicPayment\app\Services\PaymentMethodService::class);
        $activeGateways = $paymentService->getActiveGatewaysWithDetails();


        return view('frontend.pages.checkout')->with([
            'cart_count' => $cart_count,
            'total' => $total,
            'discountAmount' => $discountAmount,
            'discountPercent' => $discountPercent,
            'coupon' => $coupon,
            'payable_amount' => $payable_amount,
            'paymentService' => $paymentService,
            'activeGateways' => $activeGateways,
        ]);
    }
}
