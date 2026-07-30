<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Frontend\Coach\Traits\BuildsCoachSiteContext;
use App\Services\BrandResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Coach-scoped checkout page.
 *
 * Same payload as the platform CheckOutController::index() — but the view
 * is rendered inside the coach's master layout so the URL/branding stays
 * /coach/{slug}/checkout instead of /checkout.
 *
 * If the student is not logged in, we redirect to the coach-scoped login
 * (NOT the platform login) so the entire journey stays branded.
 */
class CoachCheckoutController extends Controller
{
    use BuildsCoachSiteContext;

    public function index(Request $request, string $coachSlug)
    {
        $coach = $request->attributes->get('tenant_coach');
        if (! $coach) {
            return redirect()->route('checkout.index');
        }

        // Auth gate — coach-branded login, NOT the platform login.
        if (! auth()->check()) {
            return redirect()->route('coach.login', ['coachSlug' => $coachSlug])
                ->with([
                    'messege'    => __('Please log in to complete your purchase.'),
                    'alert-type' => 'info',
                ]);
        }

        $user = userAuth();
        $cart_count = $user->cart_count;
        if ($cart_count == 0) {
            // Empty cart? Send them back to the coach's marketing home —
            // never the platform /courses listing.
            return redirect()->to(coachCommerceUrl('cart', $coachSlug))
                ->with([
                    'messege'    => __('Please add some courses in your cart.'),
                    'alert-type' => 'error',
                ]);
        }

        // 2026-06-01 (audit [5]) — the cart is global per-user, so a student
        // who browsed several coach sites can accumulate cross-coach items.
        // The platform order engine charges the WHOLE cart, so letting a
        // coach-branded checkout proceed with another instructor's course
        // would (a) place a muddled mixed order under this coach's branding
        // and (b) charge the student for items this coach's checkout never
        // showed. Require a coach-pure cart here: if any item is NOT owned by
        // this coach (instructor_id / added_by), bounce to the coach cart with
        // a notice. Single-coach carts (the normal case) are unaffected; a
        // genuinely mixed cart can still be paid via the platform /checkout.
        $coachId = (int) $coach->id;
        $foreignCount = $user->carts()
            ->whereHas('course', function ($q) use ($coachId) {
                $q->where(function ($w) use ($coachId) {
                        $w->where('instructor_id', '!=', $coachId)->orWhereNull('instructor_id');
                    })
                  ->where(function ($w) use ($coachId) {
                        $w->where('added_by', '!=', $coachId)->orWhereNull('added_by');
                    });
            })
            ->count();
        if ($foreignCount > 0) {
            return redirect()->to(coachCommerceUrl('cart', $coachSlug))
                ->with([
                    'messege'    => __('Your cart has courses from another instructor. Please remove them to check out here, or use the main checkout.'),
                    'alert-type' => 'error',
                ]);
        }

        // Fetch the cart items so the order summary can list course titles +
        // per-item prices. Without this the summary only shows "Items: N"
        // which leaves the student wondering what they're paying for.
        $products = $user->carts()
            ->with('course:id,title,slug,price,discount,thumbnail,tax_rate_id')
            ->get(['id', 'user_id', 'course_id']);

        $cartTotal       = $user->cart_total;
        $discountPercent = Session::has('offer_percentage') ? Session::get('offer_percentage') : 0;
        $discountAmount  = ($cartTotal * $discountPercent) / 100;
        $coupon          = Session::has('coupon_code') ? Session::get('coupon_code') : '';

        $payable_amount = $cartTotal - $discountAmount;   // pre-tax base; placeOrder re-computes tax from this
        Session::put('payable_amount', $payable_amount);

        // 2026-06-16 (GST checkout fix) — the checkout page must SHOW the coach's
        // GST and a total that matches what the gateway will actually collect.
        // PaymentController::placeOrder already taxes payable_amount via the same
        // TaxService; we compute it here too so the displayed total === collected
        // total. Coaches with no/disabled tax → charge_total == payable_amount
        // (zero change). Per-course rate wins on single-item carts.
        $singleRateId = $products->count() === 1 ? ($products->first()->course->tax_rate_id ?? null) : null;
        $tax   = app(\App\Services\Tax\TaxService::class)->computeForOrder($coachId, (float) $payable_amount, $singleRateId);
        $total = currency($tax['charge_total']);

        // Also stash the coach context so the gateway-callback hop can
        // bounce the student back to /coach/{slug}/student/* on success.
        Session::put('tenant_coach_id', (int) $coach->id);
        // 2026-06-10 — after payment, return to the FULL coach-scoped student
        // panel (consistent with the coach login redirect), not the thin
        // /coach/{slug}/student surface.
        Session::put('coach_site_return_to', route('student.dashboard'));

        $paymentService = app(\Modules\BasicPayment\app\Services\PaymentMethodService::class);
        $activeGateways = $paymentService->getActiveGatewaysWithDetails();

        $brand = app(BrandResolver::class)->forCoach((int) $coach->id);

        return view('frontend.coach-site.pages.checkout', [
            'coachSlug'        => $coachSlug,
            'coach'            => $coach,
            'brand'            => $brand,
            'page'             => $this->syntheticPage($coach, 'checkout', __('Checkout')),
            'siteNav'          => $this->siteNavFor($coach, $coachSlug),
            'hasFooterSection' => false,
            'bodyHtml'         => null,
            'cart_count'       => $cart_count,
            'products'         => $products,
            'total'            => $total,
            'cartTotal'        => $cartTotal,
            'tax'              => $tax,
            'discountAmount'   => $discountAmount,
            'discountPercent'  => $discountPercent,
            'coupon'           => $coupon,
            'payable_amount'   => $payable_amount,
            'paymentService'   => $paymentService,
            'activeGateways'   => $activeGateways,
        ]);
    }
}
