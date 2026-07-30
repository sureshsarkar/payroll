<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\Coach\Traits\BuildsCoachSiteContext;
use App\Services\BrandResolver;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Coach-scoped cart page.
 *
 * Renders the cart contents inside the COACH's branded master layout so
 * a student adding to cart on /coach/{slug} stays on /coach/{slug}/cart
 * instead of being kicked over to the platform-branded /cart page.
 *
 * Business logic — totals, coupon, products list — is identical to the
 * platform CartController (we defer to it via the existing helpers).
 * Only the rendering layer changes.
 */
class CoachCartController extends Controller
{
    use BuildsCoachSiteContext;

    public function __construct(
        protected CartController $platformCart,
    ) {
    }

    public function index(Request $request, string $coachSlug)
    {
        // Tenant resolution already happened in middleware.
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            // No coach in URL? Fall back to the platform cart page so we
            // never leave the student stranded with a 404.
            return redirect()->route('cart');
        }

        // Defensive: populate session keys the cart partial reads (this
        // controller does not extend the platform master so the helpers
        // that normally fire from frontend.layouts.master don't fire).
        if (function_exists('setEnrollmentIdsInSession')) {
            setEnrollmentIdsInSession();
        }
        if (function_exists('setInstructorCourseIdsInSession')) {
            setInstructorCourseIdsInSession();
        }

        // 2026-06-09 — TENANT SCOPE: on a coach domain the cart must show ONLY
        // this coach's courses (a student's cart can hold items from several
        // coaches; coach B's page must not reveal coach A's items). Filter the
        // products AND recompute count + total from the filtered set so the
        // displayed total matches what's shown.
        $coachId = (int) $coach->id;
        if (auth()->check()) {
            $user = userAuth();
            $products = $user->carts()
                ->whereHas('course', fn ($q) => $q->where('instructor_id', $coachId))
                ->with('course:id,title,slug,price,discount,thumbnail,instructor_id')
                ->get(['id', 'user_id', 'course_id']);
            $cart_count = $products->count();
            // Per-item price mirrors CartController: discounted price if set, else full.
            $cartTotal = (float) $products->sum(fn ($c) =>
                $c->course ? ((float) $c->course->discount > 0 ? (float) $c->course->discount : (float) $c->course->price) : 0);
        } else {
            // Guest cart (session) — keep only this coach's items.
            $products = Cart::content()->filter(fn ($item) =>
                (int) optional(\App\Models\Course::find($item->id))->instructor_id === $coachId);
            $cart_count = $products->count();
            $cartTotal = (float) $products->sum(fn ($item) => $item->price);
        }

        $discountPercent = Session::has('offer_percentage') ? Session::get('offer_percentage') : 0;
        $discountAmount  = ($cartTotal * $discountPercent) / 100;
        $total           = currency($cartTotal - $discountAmount);
        $coupon          = Session::has('coupon_code') ? Session::get('coupon_code') : '';

        $brand = app(BrandResolver::class)->forCoach((int) $coach->id);

        return view('frontend.coach-site.pages.cart', [
            'coachSlug'        => $coachSlug,
            'coach'            => $coach,
            'brand'            => $brand,
            'page'             => $this->syntheticPage($coach, 'cart', __('Cart')),
            'siteNav'          => $this->siteNavFor($coach, $coachSlug),
            'hasFooterSection' => false,
            'bodyHtml'         => null,
            'products'         => $products,
            'cart_count'       => $cart_count,
            'cartTotal'        => $cartTotal,
            'total'            => $total,
            'discountAmount'   => $discountAmount,
            'discountPercent'  => $discountPercent,
            'coupon'           => $coupon,
        ]);
    }
}
