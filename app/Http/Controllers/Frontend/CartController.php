<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Traits\RedirectHelperTrait;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Modules\Coupon\app\Models\Coupon;

class CartController extends Controller {
    use RedirectHelperTrait;

    public function index() {
        // Defensive: cart.blade.php uses in_array() against these session
        // keys. They're normally populated by setEnrollmentIdsInSession()
        // at the top of the master layout, but if the layout helpers
        // haven't fired yet (e.g. very first hit after login) the keys
        // can be null → in_array() TypeError. Populate them here so the
        // cart page can never crash on a null haystack.
        if (function_exists('setEnrollmentIdsInSession')) {
            setEnrollmentIdsInSession();
        }
        if (function_exists('setInstructorCourseIdsInSession')) {
            setInstructorCourseIdsInSession();
        }

        if (auth()->check()) {
            $user = userAuth();
            $cart_count = $user->cart_count;
            if ($cart_count == 0) {
                $this->destroyCouponSession();
            }
            $products = $user->carts()->with('course:id,title,slug,price,discount,thumbnail')->get(['id', 'user_id', 'course_id']);
        }else{
            $cart_count = Cart::content()->count();
            if (Cart::content()->count() == 0) {
                $this->destroyCouponSession();
            }
            $products = Cart::content();
        }
        // echo $products;die;
        $cartTotal = $this->cartTotal();
        $discountPercent = Session::has('offer_percentage') ? Session::get('offer_percentage') : 0;
        $discountAmount = ($cartTotal * $discountPercent) / 100;
        $total = currency($cartTotal - $discountAmount);
        $coupon = Session::has('coupon_code') ? Session::get('coupon_code') : '';
        return view('frontend.pages.cart', compact('products', 'cart_count','total', 'discountAmount', 'discountPercent', 'coupon'));
    }


    
    public function getBatch(Request $request,string $id) {
        $batch = CourseBatch::where('course_id',$id)->where('end_date', '>=', date('Y-m-d'))->where('status','active')->get();
        $response = ['status'     => 'success','message'    => 'Added to cart successfully!','data'=>$batch];

        return response($response);
        
    }


     public function addToCartWithBatch(Request $request) {
          $id = $request->course_id;
            // FT-VAL-4 fix (2026-05-28) — tighten rules + cross-check
            // batch belongs to course.
            //
            // Pre-fix: both fields were just `required` with no type/
            // existence check, and no relation between them was
            // verified. A buyer could POST course_id=A, batch_id=999
            // (batch from a different course or a deleted/inactive
            // batch) and the cart row would carry that orphan
            // batch_id straight into checkout — landing the buyer in
            // a batch they didn't intend to join, or in a batch
            // belonging to a course they never paid for once
            // enrollment fires.
            //
            // Tighten to integer|exists checks, then verify the batch
            // is FOR this course AND is active AND not expired (same
            // gate getBatch() above uses to populate the dropdown).
            $request->validate([
                'course_id' => 'required|integer|exists:courses,id',
                'batch_id'  => 'required|integer|exists:course_batches,id',
            ]);


         $course = Course::active()->find($request->course_id);


        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }

        // Verify the batch belongs to this course AND is still
        // joinable (active + end_date >= today). Mirrors the
        // getBatch() filter so the cart can never carry a stale or
        // orphan batch reference.
        $batchOk = CourseBatch::where('id', $request->batch_id)
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->where('end_date', '>=', date('Y-m-d'))
            ->exists();
        if (! $batchOk) {
            return response()->json([
                'status'  => 'error',
                'message' => __('The selected batch is not available for this course.'),
            ], 422);
        }

        if (auth()->check()) {
            $user = userAuth();
            if (isOwnCourse($user, $course)) {
                return response()->json(['status' => 'error', 'message' => 'You can not add to cart your own course!'], 200);
            }
            if (hasCourseInPurchased($user, $course)) {
                return response()->json(['status' => 'error', 'message' => 'Already purchased'], 200);
            }
            if (hasCourseInCart($user, $course)) {
                return response()->json(['status' => 'error', 'message' => 'Already added to cart'], 200);
            }
            $user->carts()->create(['course_id' => $course->id,'batch_id'=>$request->batch_id]);
            // redirect_to uses route('cart') which resolves on the CURRENT host —
            // platform → mbsguru.com/cart, coach domain → that coach's cart
            // (rendered in place by RedirectCustomDomainToScoped). White-label, no hardcode.
            $response = ['status'     => 'success','message'    => 'Added to cart successfully!','cart_count' => $user->cartCount,'redirect_to' => route('cart')];
        } else {
            if ($this->checkItemExist($id)) {
                return response(['status' => 'error', 'message' => 'Already added to cart']);
            }
            $cartData = $this->prepareCartData($course,$request->batch_id);
    
            Cart::add($cartData);

            $response = ['status' => 'success','message' => 'Added to cart successfully!','cart_count' => Cart::content()->count(),'redirect_to' => route('cart')];
        }
        $this->handleGoogleTagManager($course, $response);
        $this->updateCouponDiscountAmount();

        return response($response);
    }




    public function addToCart(Request $request,string $id) {
        $course = Course::active()->find($id);

        if (!$course) {
            return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
        }

        // 2026-06-11 — Batch validation by course type. Live/Batch courses
        // ('live' | 'hybrid') MUST be added via the batch picker
        // (addToCartWithBatch) so a batch is chosen; only RECORDED courses
        // ('course' | 'recorded') may be added directly without a batch.
        // Defense-in-depth: a live course can never enter the cart batch-less
        // through this endpoint, even if the frontend is bypassed.
        if (in_array($course->type, ['live', 'hybrid'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Please select a batch for this course.'),
            ], 422);
        }

        if (auth()->check()) {
            $user = userAuth();
            if (isOwnCourse($user, $course)) {
                return response()->json(['status' => 'error', 'message' => 'You can not add to cart your own course!'], 200);
            }
            if (hasCourseInPurchased($user, $course)) {
                return response()->json(['status' => 'error', 'message' => 'Already purchased'], 200);
            }
            if (hasCourseInCart($user, $course)) {
                return response()->json(['status' => 'error', 'message' => 'Already added to cart'], 200);
            }
            $cartRow = $user->carts()->create(['course_id' => $course->id]);
            // cart_row_id + slug let the coach cart drawer inject a working
            // remove (×) button for the just-added item (2026-06-11).
            $response = ['status' => 'success','message' => 'Added to cart successfully!','cart_count' => $user->cartCount,'cart_row_id' => $cartRow->id,'slug' => $course->slug];
        } else {
            if ($this->checkItemExist($id)) {
                return response(['status' => 'error', 'message' => 'Already added to cart']);
            }
            $cartData = $this->prepareCartData($course);
            $added = Cart::add($cartData);
            $response = ['status' => 'success','message' => 'Added to cart successfully!','cart_count' => Cart::content()->count(),'cart_row_id' => $added->rowId,'slug' => $course->slug];
        }
        $this->handleGoogleTagManager($course, $response);
        $this->updateCouponDiscountAmount();

        return response($response);
    }

    private function prepareCartData($course,$batch_id="") {
        $price = $course->discount > 0 ? $course->discount : $course->price;
        return [
            'id'      => $course->id,
            'name'    => $course->title,
            'qty'     => 1,
            'price'   => $price,
            'weight'  => 0,
            'options' => [
                'batch_id'=> $batch_id,
                'image'          => $course->thumbnail,
                'slug'           => $course->slug,
                'real_price'     => $course->price,
                'discount_price' => $course->discount,
            ],
        ];
    }

    private function handleGoogleTagManager($course, &$response) {
        $settings = cache()->get('setting');
        $marketingSettings = cache()->get('marketing_setting');

        if ($settings->google_tagmanager_status == 'active' && $marketingSettings->add_to_cart) {
            $cartData = $this->prepareCartData($course);
            $cartData['price'] = currency($course->price);
            $cartData['options']['real_price'] = currency($course->price);
            $cartData['options']['image'] = asset($course->thumbnail);
            $cartData['options']['slug'] = route('course.show', $course->slug);
            unset($cartData['id']);
            $cartData['user'] = auth('web')->check() ? [
                'name'  => auth('web')->user()->name,
                'email' => auth('web')->user()->email,
            ] : 'guest';

            $response['dataLayer'] = $cartData;
        }
    }

    public function removeCartItem($cartId,string $rowId=null) {
        $request = request();
        $cartId = base64_decode($cartId);
        // $user = userAuth();
        //     $products = $user->carts()->get(['id', 'user_id', 'course_id']);

        // echo $cartId;die;
        if (auth()->check()) {
            $user = userAuth();
            $course = Course::select('id')->whereSlug($rowId)->first();
            if (!$course || !$user->carts()->where('course_id', $course->id)->exists()) {
                // 2026-06-11 — AJAX (coach cart drawer / page) gets JSON so it
                // can update in place; classic requests still redirect back.
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['status' => 'error', 'message' => __('Not Found!')], 404);
                }
                $notification = [
                    'messege'    => __('Not Found!'),
                    'alert-type' => 'error',
                ];
                return redirect()->back()->with($notification);
            }
            $user->carts()->where('id', $cartId)->delete();
        }else{
            // Guest (session cart). 2026-06-11 — Cart::get()/remove() THROW
            // InvalidRowIDException when the rowId isn't in the cart (stale
            // remove link, already-removed, double-click, regenerated session),
            // which surfaced as a 500 on /remove-cart-item. Guard with has():
            // if the item is already gone the desired end-state is reached, so
            // we fall through to the normal "removed" response instead of
            // crashing — the remove can never 500 again.
            if (Cart::content()->has($cartId)) {
                $cartItem = Cart::get($cartId)?->toArray();
                if ($cartItem) {
                    unset($cartItem['rowId'], $cartItem['id']);
                    $cartItem['price'] = currency($cartItem['price']);
                    $cartItem['options']['real_price'] = currency($cartItem['options']['real_price']);
                    $cartItem['options']['image'] = asset($cartItem['options']['image']);
                    $cartItem['options']['slug'] = route('course.show', $cartItem['options']['slug']);
                    $cartItem['user'] = auth('web')->check() ? [
                        'name'  => auth('web')->user()->name,
                        'email' => auth('web')->user()->email,
                    ] : 'guest';

                    $settings = cache()->get('setting');
                    $marketingSettings = cache()->get('marketing_setting');
                    if (($settings->google_tagmanager_status ?? null) == 'active' && ($marketingSettings->remove_from_cart ?? false)) {
                        session()->put('removeFromCart', $cartItem);
                    }
                }
                Cart::remove($cartId);
            }
        }
        $this->updateCouponDiscountAmount();

        if ($request->expectsJson() || $request->ajax()) {
            $count = auth()->check() ? (int) userAuth()->cart_count : Cart::content()->count();

            return response()->json([
                'status'     => 'success',
                'message'    => __('Item removed from cart!'),
                'cart_count' => $count,
                'total'      => currency($this->cartTotal()),
            ]);
        }

        $notification = [
            'messege'    => __('Item removed from cart!'),
            'alert-type' => 'success',
        ];

        return redirect()->back()->with($notification);
    }

    public function cartTotal() {
        $cartTotal = 0;
        if (auth()->check()) {
            $cartTotal = userAuth()->cart_total;
        }else{
            $cartItems = Cart::content();
        foreach ($cartItems as $key => $cartItem) {
            $cartTotal += $cartItem->price;
        }
        }
        return $cartTotal;
    }

    public function checkItemExist(string $id) {
        $cartItems = Cart::content();
        foreach ($cartItems as $key => $cartItem) {
            if ($cartItem->id == $id) {
                return true;
            }
        }
        return false;
    }

    public function applyCoupon(Request $request) {
        // FT-VAL-5 fix (2026-05-28) — tighten input typing.
        // Pre-fix the rule was `coupon => required` only. An attacker
        // could submit coupon[] (an array) — the resulting `Coupon::where(...)
        // ->first()` would silently return null (no enumeration) and the
        // attacker would just see "Invalid coupon", but pathological
        // submissions (huge payloads, non-string types) wasted DB cycles
        // and made log noise harder to triage. Pinning to string + length
        // cap matches the actual coupon_code column width (varchar
        // ~20-30 chars in MariaDB) so anything longer is rejected before
        // the DB roundtrip.
        $rules = [
            'coupon' => 'required|string|max:32',
        ];
        $customMessages = [
            'coupon.required' => __('Coupon is required'),
            'coupon.string'   => __('Invalid coupon'),
            'coupon.max'      => __('Invalid coupon'),
        ];

        $request->validate($rules, $customMessages);

        $coupon = Coupon::where(['coupon_code' => $request->coupon, 'status' => 'active'])->first();

        if (!$coupon) {
            $notification = __('Invalid coupon');

            return response()->json(['message' => $notification], 403);
        }

        // Per-coach coupon scope (audit M2) — a coach-private coupon may only be
        // redeemed on that coach's white-label surface. Use the SAME generic
        // "Invalid coupon" message so another coach's code isn't enumerable.
        $tenantCoachId = \App\Support\TenantAccess::checkoutCoachId($request);
        if (! $coupon->isUsableOnCoachSurface($tenantCoachId)) {
            return response()->json(['message' => __('Invalid coupon')], 403);
        }

        if ($coupon->expired_date < date('Y-m-d')) {
            $notification = __('Coupon already expired');

            return response()->json(['message' => $notification], 403);
        }

        if ($this->cartTotal() < $coupon->min_price) {
            $notification = __('Minimum order amount should be :amount', ['amount' => currency($coupon->min_price)]);

            return response()->json(['message' => $notification], 403);
        }
        if ($this->cartTotal() <= 0) {
            $notification = __('Cart amount should be greater than 0');

            return response()->json(['message' => $notification], 403);
        }

        // Total-usage limit (NULL = unlimited).
        if (!is_null($coupon->usage_limit) && (int) $coupon->usage_count >= (int) $coupon->usage_limit) {
            return response()->json(['message' => __('This coupon has reached its usage limit')], 403);
        }

        // Per-user limit (NULL = unlimited; requires authenticated user).
        if (!is_null($coupon->per_user_limit) && auth()->check()) {
            $userUses = \DB::table('coupon_uses')
                ->where('coupon_id', $coupon->id)
                ->where('user_id', auth()->id())
                ->count();
            if ($userUses >= (int) $coupon->per_user_limit) {
                return response()->json(['message' => __('You have already used this coupon the maximum number of times')], 403);
            }
        }

        $discountAmount = currency(($this->cartTotal() * $coupon->offer_percentage) / 100);
        $total = currency($this->cartTotal() - ($this->cartTotal() * $coupon->offer_percentage) / 100);

        /** when coupon will be handle for particular seller or author , above condition will be used  */
        Session::put('coupon_code', $coupon->coupon_code);
        Session::put('offer_percentage', $coupon->offer_percentage);
        Session::put('coupon_discount_amount', ($this->cartTotal() * $coupon->offer_percentage) / 100);

        $notification = __('Coupon applied successful');

        return response()->json(['message' => $notification, 'coupon_code' => $coupon->coupon_code, 'offer_percentage' => $coupon->offer_percentage, 'discount_amount' => $discountAmount, 'total' => $total]);
    }

    public function updateCouponDiscountAmount() {
        if (!Session::has('coupon_code')) {
            return;
        }

        // FT-LOGIC-1 fix (2026-05-27) — null-deref bug.
        // Before: lookup returned the still-active coupon row OR null;
        // the next line then did `$coupon->offer_percentage` which
        // throws "Trying to get property 'offer_percentage' of
        // non-object" if the admin deactivated or deleted the coupon
        // between the buyer applying it (in session) and clicking
        // "Update Cart". The buyer sees a 500 instead of the cart
        // gracefully dropping the stale discount.
        // Treat "coupon disappeared from DB" exactly like "coupon was
        // removed" — flush session keys via destroyCouponSession() so
        // the cart re-prices from full price.
        $coupon = Coupon::where(['coupon_code' => Session::get('coupon_code'), 'status' => 'active'])->first();
        // Drop the discount if the coupon vanished OR is a coach-private coupon
        // no longer valid for the current surface (audit M2).
        $tenantCoachId = \App\Support\TenantAccess::checkoutCoachId(request());
        if (!$coupon || ! $coupon->isUsableOnCoachSurface($tenantCoachId)) {
            $this->destroyCouponSession();
            return;
        }
        Session::put('coupon_discount_amount', ($this->cartTotal() * $coupon->offer_percentage) / 100);
    }

    public function removeCoupon() {

        $this->destroyCouponSession();

        $notification = [
            'messege'    => __('Coupon removed successfully!'),
            'alert-type' => 'success',
        ];
        return redirect()->back()->with($notification);
    }

    public function destroyCouponSession() {
        Session::forget('coupon_code');
        Session::forget('offer_percentage');
        Session::forget('coupon_discount_amount');
    }
}
