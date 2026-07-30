<?php

namespace Modules\BasicPayment\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\BankInformationRequest;
use App\Jobs\DefaultMailJob;
use App\Mail\DefaultMail;
use App\Models\Course;
use App\Traits\GetGlobalInformationTrait;
use App\Traits\MailSenderTrait;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Modules\BasicPayment\app\Enums\BasicPaymentSupportedCurrencyListEnum;
use Modules\CourseBundle\app\Models\CourseBundle;
use Modules\GiftCourse\app\Models\GiftCourse;
use Modules\GlobalSetting\app\Models\EmailTemplate;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Modules\Order\app\Traits\GiftOrderTraits;
use Nwidart\Modules\Facades\Module;
use Razorpay\Api\Api;

class PaymentController extends Controller
{
    use GetGlobalInformationTrait, GiftOrderTraits, MailSenderTrait;

    private $paymentService;

    public function __construct()
    {
        $this->paymentService = app(\Modules\BasicPayment\app\Services\PaymentMethodService::class);
        $this->middleware(function (Request $request, Closure $next) {
            if (session()->has('order') || Route::is('payment') || Route::is('place.order') || Route::is('pay-via-free-gateway')) {
                return $next($request);
            }

            return redirect()->back()->with(['messege' => __('Not Found!'), 'alert-type' => 'error']);
        });
    }

    public function placeOrder($method)
    {
        $user = userAuth();

        $activeGateways = array_keys($this->paymentService->getActiveGatewaysWithDetails());
        if (! in_array($method, $activeGateways)) {
            return response()->json(['status' => false, 'messege' => __('The selected payment method is now inactive.')]);
        }

        // FT-LOGIC-2 (follow-up, 2026-05-28) — re-validate the session
        // coupon ONE MORE TIME at order-creation entry. FT-LOGIC-1
        // hardened the cart-page update path, FT-LOGIC-2 hardened the
        // /checkout entry path, and this commit closes the remaining
        // race: buyer renders /checkout (coupon was valid), admin
        // deactivates the coupon while the buyer types billing info,
        // buyer clicks "Pay" — the session still carries the stale
        // coupon_code/offer_percentage/coupon_discount_amount, and the
        // Order::create call below writes them straight through.
        //
        // The window is small (typically seconds) but real, and the
        // money-side cost when it loses is exactly the discount
        // amount per order. Re-look up the coupon fresh; if the row
        // has gone missing, been deactivated, or has expired since
        // the buyer landed on /checkout, flush the session keys so
        // Order::create stores no coupon and payable_amount falls
        // back to the full cart total.
        if (Session::has('coupon_code')) {
            $couponRow = \Modules\Coupon\app\Models\Coupon::where([
                'coupon_code' => Session::get('coupon_code'),
                'status'      => 'active',
            ])->first();
            $expired = $couponRow && $couponRow->expired_date && $couponRow->expired_date < date('Y-m-d');
            // Per-coach coupon scope (audit M2) — a coach-private coupon stashed
            // in the session must not survive onto an order on a different surface.
            $tenantCoachId = \App\Support\TenantAccess::checkoutCoachId(request());
            $wrongSurface = $couponRow && ! $couponRow->isUsableOnCoachSurface($tenantCoachId);
            if (! $couponRow || $expired || $wrongSurface) {
                Session::forget('coupon_code');
                Session::forget('offer_percentage');
                Session::forget('coupon_discount_amount');
                // Recompute payable_amount sans discount so the
                // cart-type branch below picks up the cleaned value.
                // bundle/gift branches recompute payable_amount from
                // their own price/discount fields, so they're
                // unaffected.
                if ($user) {
                    Session::put('payable_amount', $user->cart_total);
                }
            }
        }

        if (! $this->paymentService->isCurrencySupported($method)) {
            $supportedCurrencies = $this->paymentService->getSupportedCurrencies($method);

            return response()->json(['status' => false, 'messege' => __('You are trying to use unsupported currency'), 'supportCurrency' => sprintf(
                '%s %s: %s',
                strtoupper($method),
                __('supports only these types of currencies'),
                implode(', ', $supportedCurrencies)
            )]);
        }

        if (Module::has('CourseBundle') && Module::isEnabled('CourseBundle') && request()->query('bundle', null)) {
            $bundle_slug = request()->query('bundle', null);

            $bundle = CourseBundle::with('course_bundle_items:course_bundle_id,course_id', 'course_bundle_items.course:id,slug,thumbnail,title,price,discount')->whereSlug($bundle_slug)->first();
            if (! $bundle) {
                return response()->json(['status' => false, 'messege' => __('Not Found!')]);
            }
            if ($bundle->instructor_id == $user->id) {
                return response()->json(['status' => false, 'messege' => __('You cannot purchase your own bundle.')]);
            }
            $bundle_course_ids = $bundle->course_bundle_items->pluck('course_id')->toArray();
            $enrolledCount = $user->enrollments()->whereIn('course_id', $bundle_course_ids)->count();
            if ($enrolledCount == count($bundle_course_ids)) {
                return response()->json(['status' => false, 'messege' => __('You are already enrolled in all courses in this bundle.')]);
            }

            $carts = $bundle->course_bundle_items;

            $price = $bundle->price ?? 0;
            $discount = $bundle->discount ?? 0;
            $payable_amount = ($price > 0 && $discount > 0) ? $discount : $price;

            $order_type = 'bundle';
            $order_details = (object) [
                'id' => $bundle->id,
                'title' => $bundle->title,
                'price' => $bundle->price,
                'discount' => $bundle->discount,
                'thumbnail' => $bundle->thumbnail,
                'course_ids' => $bundle_course_ids,
            ];
        } elseif (Module::has('GiftCourse') && Module::isEnabled('GiftCourse') && request()->query('gift', null)) {
            $gift_id = request()->query('gift', null);

            $gift = GiftCourse::with('course:id,slug,thumbnail,title,price,discount')->whereGiftId($gift_id)->firstOrFail();
            if (! $gift) {
                return response()->json(['status' => false, 'messege' => __('Not Found!')]);
            }

            $carts = [$gift];

            $price = $gift->course->price ?? 0;
            $discount = $gift->course->discount ?? 0;
            $payable_amount = ($price > 0 && $discount > 0) ? $discount : $price;
            $order_type = 'gift';
            $order_details = (object) [
                'gift_id' => $gift->gift_id,
                'user_id' => $gift->user_id,
                'course_id' => $gift->course_id,
                'recipient_name' => $gift->recipient_name,
                'recipient_email' => $gift->recipient_email,
                'message' => $gift->message,
            ];
        } else {
            $carts = $user->carts()->with('course:id,title,slug,price,discount,instructor_id,tax_rate_id')->get(['id', 'user_id', 'course_id', 'batch_id']);

            // TENANT HARD-BLOCK (audit M4) — on a coach surface, EVERY course in
            // the order must belong to that coach. This is the order-creation
            // backstop behind CoachCheckoutController's cart redirect: even a
            // crafted POST cannot mint a mixed-coach order on a coach domain.
            // No-op on the bare platform (tenant coach 0).
            $tenantCoachId = \App\Support\TenantAccess::checkoutCoachId(request());
            if ($tenantCoachId > 0) {
                $foreign = $carts->first(fn ($c) => (int) ($c->course->instructor_id ?? 0) !== $tenantCoachId);
                if ($foreign) {
                    return response()->json(['status' => false, 'messege' => __('Your cart has courses from another instructor. Please remove them to check out here.')]);
                }
            }

            $payable_amount = Session::get('payable_amount', $user->cart_total);
            $order_type = 'course';
            $order_details = null;
        }

        // ── Tax (Phase 1, 2026-06-13) ── per-coach, opt-in. Computed on the
        // post-coupon subtotal for the order's primary coach. No profile / tax
        // disabled → $tax is all-zero and the amount collected is unchanged, so
        // non-tax coaches behave exactly as before. Bundle/gift are out of MVP
        // scope (gated on order_type === 'course').
        $taxSvc = app(\App\Services\Tax\TaxService::class);
        $primaryCoachId = null; $singleRateId = null;
        if (($order_type ?? null) === 'course' && isset($carts) && count($carts)) {
            $firstCourse = $carts->first()->course ?? null;
            $primaryCoachId = $firstCourse?->instructor_id;
            $singleRateId = count($carts) === 1 ? ($firstCourse?->tax_rate_id) : null;
        }
        $tax = $taxSvc->computeForOrder($primaryCoachId, (float) $payable_amount, $singleRateId);
        // The gateway must collect taxable + tax (== charge_total). For no-tax
        // this equals $payable_amount, i.e. zero change.
        $amountToCollect = $tax['charge_total'];

        try {
            $calculatePayableCharge = $this->paymentService->getPayableAmount($method, $amountToCollect);

            DB::beginTransaction();

            $paid_amount = $calculatePayableCharge?->payable_amount + $calculatePayableCharge?->gateway_charge;

            if (in_array($method, ['Razorpay', 'Stripe'])) {
                $allCurrencyCodes = BasicPaymentSupportedCurrencyListEnum::getStripeSupportedCurrencies();

                if (in_array(Str::upper($calculatePayableCharge?->currency_code), $allCurrencyCodes['non_zero_currency_codes'])) {
                    $paid_amount = $paid_amount;
                } elseif (in_array(Str::upper($calculatePayableCharge?->currency_code), $allCurrencyCodes['three_digit_currency_codes'])) {
                    $paid_amount = (int) rtrim(strval($paid_amount), '0');
                } else {
                    $paid_amount = floatval($paid_amount / 100);
                }
            }

            $order = Order::create([
                'invoice_id' => Str::random(10),
                'buyer_id' => $user->id,
                'has_coupon' => Session::has('coupon_code') ? 1 : 0,
                'coupon_code' => Session::get('coupon_code'),
                'coupon_discount_percent' => Session::get('offer_percentage'),
                'coupon_discount_amount' => Session::get('coupon_discount_amount'),
                'payment_method' => $method,
                'payment_status' => 'pending',
                // payable_amount = the coach's pre-tax revenue base (== subtotal
                // when no tax) so commission stays correct; tax is a pass-through.
                'payable_amount' => $tax['taxable_amount'],
                'tax_amount' => $tax['tax_amount'],
                'taxable_amount' => $tax['taxable_amount'],
                'tax_rate_applied' => $tax['rate'],
                'tax_mode' => $tax['has_tax'] ? $tax['mode'] : null,
                'tax_label' => $tax['has_tax'] ? $tax['label'] : null,
                'tax_registration' => $tax['has_tax'] ? $tax['registration'] : null,
                'tax_components' => $tax['has_tax'] ? $tax['components'] : null,
                'gateway_charge' => $calculatePayableCharge?->gateway_charge,
                'payable_with_charge' => $calculatePayableCharge?->payable_with_charge,
                'paid_amount' => $paid_amount,
                'payable_currency' => $calculatePayableCharge?->currency_code,
                'conversion_rate' => Session::get('currency_rate', 1),
                'commission_rate' => Cache::get('setting')->commission_rate,
                'order_type' => $order_type,
                'order_details' => $order_details,
            ]);

            // Coach-specific gateway provenance (2026-06-29) — DESKTOP WEB checkout.
            // Stamp WHICH gateway config will process this order so the gateway
            // method + callback re-resolve the SAME credentials. Core gateways only
            // (Razorpay/Stripe/PayPal); a mixed multi-coach cart resolves to the
            // platform default (one charge can't split across merchant accounts).
            if (\App\Services\Payment\GatewayFieldRegistry::isCore(strtolower((string) $method))) {
                $gwOwnerIds = \App\Models\Course::whereIn('id', $carts->pluck('course.id')->filter()->all())
                    ->pluck('instructor_id')->all();
                $resolvedGw = app(\App\Services\Payment\CheckoutGatewayResolver::class)
                    ->resolveForOwners($gwOwnerIds, strtolower((string) $method));
                $order->gateway_owner_type = $resolvedGw->ownerType;
                $order->gateway_config_id  = $resolvedGw->configId;
                $order->gateway_coach_id   = $resolvedGw->coachId;
                $order->save();
            }

            $data_layer_order_items = [];

            foreach ($carts as $item) {
                // 2026-06-12 — the line item must carry the EFFECTIVE sale price
                // ($course->discount ?: price), NOT the struck-through MRP. The
                // student is charged effective_price (User::cart_total →
                // payable_amount); storing course->price here made the invoice
                // show 6,666 while only 555 was paid (price/invoice mismatch).
                $item_price = $order->isBundleOrder() ? ($order->payable_amount / max(1, $carts->count())) : $item->course->effective_price;
                // 2026-06-01 (audit H9) — capture the PER-COACH commission rate
                // on the line item (was the global setting rate), mirroring the
                // API checkout. effectiveCommissionRate() returns the coach's
                // override or the global default, so this is identical to the
                // old value until a coach override exists — then the coach's
                // negotiated rate is honoured end-to-end (credit + refund both
                // read this captured per-item rate via OrderItem::coachPayout).
                $itemInstructor = Course::find($item->course->id)?->instructor;
                $itemRate = $itemInstructor
                    ? $itemInstructor->effectiveCommissionRate()
                    : (float) (Cache::get('setting')->commission_rate ?? 0);
                // Per-line tax = this line's share of the order tax (proportional
                // to its effective price). Single-item carts get the full amount;
                // no-tax orders get 0.
                $lineShare = $payable_amount > 0 ? ($item->course->effective_price / $payable_amount) : 0;
                $lineTax = round(($tax['tax_amount'] ?? 0) * $lineShare, 2);
                OrderItem::create([
                    'order_id' => $order->id,
                    'price' => $item_price,
                    'course_id' => $item->course->id,
                    'tax_amount' => $lineTax,
                    'tax_rate_applied' => $tax['has_tax'] ? $tax['rate'] : null,
                    // Normalize 0/empty → NULL. order_items.batch_id is a FK to
                    // course_batches.id; a literal 0 (recorded courses / legacy
                    // cart rows) is not a valid batch id and violates the FK,
                    // crashing placeOrder → generic "Payment Failed". NULL is the
                    // correct batch-less value (FK is nullable, ON DELETE SET NULL).
                    'batch_id' => $item->batch_id ?: null,
                    'commission_rate' => $itemRate,
                ]);
                $data_layer_order_items[] = [
                    'course_name' => $item->course->title,
                    'price' => currency($item->course->effective_price),
                    'url' => route('course.show', $item->course->slug),
                ];

                // 2026-06-01 FINANCIAL FIX (audit C5/C6/H8) — do NOT credit
                // the coach wallet here. This block ran at ORDER CREATION
                // while payment_status is still 'pending' (before any money is
                // collected), which produced:
                //   (C5) a DOUBLE credit, because payment_success() credits
                //        AGAIN on confirmation (line ~1070); and
                //   (C6) an ORPHAN credit that survived abandoned / failed /
                //        never-completed checkouts (the pending order keeps the
                //        credit forever, inflating the withdrawable balance).
                // The wallet is now credited exactly ONCE, at confirmation —
                // payment_success() for online, OrderController::updateOrder()
                // flip-to-paid for bank/offline — exactly as the API checkout
                // controller already does. Single credit ⇒ the single-credit
                // refund reversal (H8) is now correct too.
            }

            DB::commit();
            if (! $order->isBundleOrder() && ! $order->isGiftOrder()) {
                $user->carts()->delete();
            }

            $settings = cache()->get('setting');
            $marketingSettings = cache()->get('marketing_setting');
            if ($user && $settings->google_tagmanager_status == 'active' && $marketingSettings->order_success) {
                $order_success = [
                    'invoice_id' => $order->invoice_id,
                    'transaction_id' => $order->transaction_id,
                    'payment_method' => $order->payment_method,
                    'payable_currency' => $order->payable_currency,
                    'paid_amount' => $order->paid_amount,
                    'payment_status' => $order->payment_status,
                    'order_items' => $data_layer_order_items,
                    'student_info' => [
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ];
                session()->put('enrollSuccess', $order_success);
            }
            // send mail (coach_id makes the receipt white-label for that coach)
            $this->handleMailSending([
                'email' => $user->email,
                'name' => $user->name,
                'order_id' => $order->invoice_id,
                'paid_amount' => formatMoney((float) $order->paid_amount, $order->payable_currency), // Phase 4.3: consistent ₹500.00 (was "500.00 INR")
                'payment_method' => $order->payment_method,
                'coach_id' => $order->primary_coach_id ?? null,
            ]);

            return response()->json(['success' => true, 'invoice_id' => $order?->invoice_id]);
        } catch (Exception $e) {
            DB::rollBack();
            $data_layer_order_items = [];
            info($carts);
            foreach ($carts as $item) {
                $data_layer_order_items[] = [
                    'course_name' => $item->course->title,
                    'price' => currency($item->course->effective_price),
                    'url' => route('course.show', $item->course->slug),
                ];
            }

            $settings = cache()->get('setting');
            $marketingSettings = cache()->get('marketing_setting');
            if ($settings->google_tagmanager_status == 'active' && $marketingSettings->order_failed) {
                $user = userAuth();
                $order_failed = [
                    'payable_currency' => session('payable_currency', getSessionCurrency()),
                    'paid_amount' => session('paid_amount', null),
                    'payment_status' => 'Failed',
                    'order_items' => $data_layer_order_items,
                    'student_info' => [
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ];
                session()->put('enrollFailed', $order_failed);
            }
            info($e->getMessage());

            return response()->json(['status' => false, 'messege' => __('Payment Failed')]);
        }

    }

    public function index()
    {
        $invoice_id = request('invoice_id', null);
        // echo $invoice_id;die;
        $user = userAuth();

        $order = $user?->orders()
            ->where('invoice_id', $invoice_id)
            ->where('status', 'pending')->first();

        if (! $order) {
            $notification = [
                'messege' => __('Not Found!'),
                'alert-type' => 'error',
            ];

            return redirect()->back()->with($notification);
        }
        $paymentMethod = $order->payment_method;
        if (! $this->paymentService->isActive($paymentMethod)) {
            // 2026-06-03 (Model B) — coach-created orders carry a placeholder
            // payment_method ("coach_manual"), and some older orders carry a
            // gateway that was later deactivated. Either way the stored method
            // is not a usable gateway. Rather than dead-end the student with
            // "payment method is now inactive", let them pay this invoice via
            // an active gateway: honour a ?method= choice if it's payable,
            // auto-pick when only one is available, else show a small picker.
            //
            // Only gateways this page can actually drive are offered — the
            // base gateways with a checkout view (stripe/paypal/bank/offline).
            // Additional gateways (crypto/bkash/...) have their own routes and
            // were never resumable through this page for ANY order, so this is
            // not a regression. The normal path (stored method still active)
            // skips this whole block and is untouched.
            $payableGateways = array_filter(
                $this->paymentService->getActiveGatewaysWithDetails(),
                fn ($details, $key) => $this->paymentService->getBladeView($key) !== null,
                ARRAY_FILTER_USE_BOTH
            );
            $payableKeys = array_keys($payableGateways);
            $requested = (string) request('method', '');

            if ($requested !== '' && in_array($requested, $payableKeys, true)) {
                $order->update(['payment_method' => $requested]);
                $paymentMethod = $requested;
            } elseif (count($payableKeys) === 1) {
                $order->update(['payment_method' => $payableKeys[0]]);
                $paymentMethod = $payableKeys[0];
            } elseif (count($payableKeys) > 1) {
                return view('frontend.pages.order-pay-select', [
                    'order'    => $order,
                    'gateways' => $payableGateways,
                ]);
            } else {
                return redirect()->back()->with([
                    'messege'    => __('No online payment method is available for this order right now. Please contact your coach.'),
                    'alert-type' => 'error',
                ]);
            }
        }

        // 2026-06-17 — the gateway must collect the TAX-INCLUSIVE total, not the
        // coach's pre-tax revenue base. placeOrder() deliberately stores
        // payable_amount = taxable_amount (so commission stays correct) and the
        // GST separately in tax_amount; both sum to the charge_total shown at
        // checkout (TaxService: charge_total == taxable_amount + tax_amount for
        // exclusive, inclusive AND no-tax modes). Re-add the tax here so what
        // Razorpay/Stripe charge === what the checkout page displayed. No-tax
        // orders have tax_amount = 0, so this is a no-op for every coach who
        // never enabled tax (fully global, no hardcoding).
        $gatewayBase = (float) ($order?->payable_amount ?? 0) + (float) ($order?->tax_amount ?? 0);
        $calculatePayableCharge = $this->paymentService->getPayableAmount($paymentMethod, $gatewayBase, $order?->payable_currency);

        Session::put('order', $order);
        Session::put('payable_currency', $order?->payable_currency);
        Session::put('paid_amount', $calculatePayableCharge?->payable_with_charge);

        $paymentService = $this->paymentService;
        $view = $this->paymentService->getBladeView($paymentMethod);

        // Coach-specific gateway (2026-06-29) — resolve the client-side key from the
        // order's stamped gateway config so the Razorpay dialog opens under the SAME
        // account the capture uses (the shared gateway-action views read $resolvedGateway).
        $resolvedGateway = app(\App\Services\Payment\PaymentGatewayResolverService::class)->resolveForOrder($order);

        return view($view, compact('order', 'paymentService', 'paymentMethod', 'resolvedGateway'));
    }

    /**
     * Re-resolve the gateway the session order was stamped with, fetching the
     * order FRESH from the DB so the stored provenance is read. Falls back to the
     * platform default for legacy/unstamped orders.
     */
    private function resolveGatewayForSessionOrder(string $gateway): \App\Services\Payment\ResolvedGateway
    {
        $sessionOrder = session()->get('order');
        $order = ($sessionOrder && isset($sessionOrder->id))
            ? \Modules\Order\app\Models\Order::find($sessionOrder->id)
            : null;
        if (! $order) {
            $order = new \Modules\Order\app\Models\Order(['payment_method' => $gateway]);
        }
        return app(\App\Services\Payment\PaymentGatewayResolverService::class)->resolveForOrder($order);
    }

    public function pay_via_bank(BankInformationRequest $request)
    {
        $bankDetails = json_encode($request->only(['bank_name', 'account_number', 'routing_number', 'branch', 'transaction']));

        $allPayments = Order::whereNotNull('payment_details')->get();

        foreach ($allPayments as $payment) {
            $paymentDetailsJson = json_decode($payment?->payment_details, true);

            if (isset($paymentDetailsJson['account_number']) && $paymentDetailsJson['account_number'] == $request->account_number) {
                if (isset($paymentDetailsJson['transaction']) && $paymentDetailsJson['transaction'] == $request->transaction) {
                    $notification = __('Payment failed, transaction already exist');
                    $notification = ['messege' => $notification, 'alert-type' => 'error'];

                    return redirect()->back()->with($notification);
                }
            }
        }
        Session::put('after_success_transaction', $request->transaction);
        Session::put('payment_details', $bankDetails);

        return $this->payment_success();
    }

    public function pay_via_offline(Request $request)
    {
        $request->validate([
            // FT-UPLOAD-1 fix (2026-05-27) — dropped svg from receipt
            // mimes. SVG is XML and can carry <script> / event handlers
            // / javascript: URIs; admins viewing the receipt in
            // /uploads/ would execute attacker JS in their session.
            // Allowed: raster images + pdf + docx (legitimate receipts).
            'payment_receipt' => 'required|mimes:jpeg,jpg,png,gif,webp,pdf,docx|max:2048',
        ], [
            'payment_receipt.required' => __('Offline Payment Receipt is required'),
            'payment_receipt.mimes' => __('The Offline Payment Receipt must be a file of type: jpeg, jpg, png, gif, webp, pdf, docx.'),
            'payment_receipt.max' => __('The Offline Payment Receipt may not be greater than 2048 kilobytes.'),
        ]);
        if ($request->hasFile('payment_receipt')) {
            // V1 fix (2026-06-16) — receipts are PRIVATE financial docs: store on
            // the private disk (outside web root), not the public custom-images
            // folder. Served only via the admin-gated download route.
            $file_name = \App\Support\PrivateMedia::store($request->payment_receipt, 'payment-receipts');
            Session::put('after_success_transaction', uniqid('offline_txn_', true));
            Session::put('payment_details', $file_name);
        }

        return $this->payment_success();
    }

    public function pay_via_free_gateway()
    {
        $user = userAuth();

        if (Module::has('CourseBundle') && Module::isEnabled('CourseBundle') && request()->query('bundle', null)) {
            $bundle_slug = request()->query('bundle', null);

            $bundle = CourseBundle::with('course_bundle_items:course_bundle_id,course_id', 'course_bundle_items.course:id,slug,thumbnail,title,price,discount')->whereNot('instructor_id', $user->id)->whereSlug($bundle_slug)->first();
            if (! $bundle) {
                return response()->json(['status' => false, 'messege' => __('Not Found!')]);
            }
            if ($bundle->price != 0) {
                $notification = __('Payment faild, please try again');
                $notification = ['messege' => $notification, 'alert-type' => 'error'];

                return redirect()->back()->with($notification);
            }

            $bundle_course_ids = $bundle->course_bundle_items->pluck('course_id')->toArray();
            $enrolledCount = $user->enrollments()->whereIn('course_id', $bundle_course_ids)->count();
            if ($enrolledCount == count($bundle_course_ids)) {
                return response()->json(['status' => false, 'messege' => __('You are already enrolled in all courses in this bundle.')]);
            }

            $carts = $bundle->course_bundle_items;

            $price = $bundle->price ?? 0;
            $discount = $bundle->discount ?? 0;
            $payable_amount = ($price > 0 && $discount > 0) ? $discount : $price;

            $order_type = 'bundle';
            $order_details = (object) [
                'id' => $bundle->id,
                'title' => $bundle->title,
                'price' => $bundle->price,
                'discount' => $bundle->discount,
                'thumbnail' => $bundle->thumbnail,
                'course_ids' => $bundle_course_ids,
            ];
        } elseif (Module::has('GiftCourse') && Module::isEnabled('GiftCourse') && request()->query('gift', null)) {
            $gift_id = request()->query('gift', null);

            $gift = GiftCourse::with('course:id,slug,thumbnail,title,price,discount')->whereGiftId($gift_id)->firstOrFail();
            if (! $gift) {
                return response()->json(['status' => false, 'messege' => __('Not Found!')]);
            }
            if ($gift->course->price != 0) {
                $notification = __('Payment faild, please try again');
                $notification = ['messege' => $notification, 'alert-type' => 'error'];

                return redirect()->back()->with($notification);
            }

            $price = $gift->course->price ?? 0;
            $discount = $gift->course->discount ?? 0;
            $payable_amount = ($price > 0 && $discount > 0) ? $discount : $price;

            $carts = [$gift];
            $order_type = 'gift';
            $order_details = (object) [
                'gift_id' => $gift->gift_id,
                'user_id' => $gift->user_id,
                'course_id' => $gift->course_id,
                'recipient_name' => $gift->recipient_name,
                'recipient_email' => $gift->recipient_email,
                'message' => $gift->message,
            ];
        } else {
            if ($user->cart_total != 0) {
                $notification = __('Payment faild, please try again');
                $notification = ['messege' => $notification, 'alert-type' => 'error'];

                return redirect()->back()->with($notification);
            }

            $carts = $user->carts()->with('course:id,title,slug,price,discount,instructor_id')->get(['id', 'user_id', 'course_id']);

            // F13 (audit 2026-06-26) — TENANT HARD-BLOCK on the free path too. The
            // paid path already blocks foreign-coach courses; without the same
            // guard here a crafted free (0-priced) checkout on a coach domain
            // could mint a foreign-coach order + enrollment. No-op on platform.
            $tenantCoachId = \App\Support\TenantAccess::checkoutCoachId(request());
            if ($tenantCoachId > 0) {
                $foreign = $carts->first(fn ($c) => (int) ($c->course->instructor_id ?? 0) !== $tenantCoachId);
                if ($foreign) {
                    return response()->json(['status' => false, 'messege' => __('Your cart has courses from another instructor. Please remove them to check out here.')]);
                }
            }

            $payable_amount = Session::get('payable_amount', $user->cart_total);
            $order_type = 'course';
            $order_details = null;
        }

        Session::put('after_success_transaction', Str::random(10));

        try {

            DB::beginTransaction();

            $order = Order::create([
                'invoice_id' => Str::random(10),
                'buyer_id' => $user->id,
                'payment_method' => 'Free',
                'status' => 'completed',
                'payment_status' => 'paid',
                'payable_amount' => $payable_amount,
                'gateway_charge' => 0,
                'payable_with_charge' => $payable_amount,
                'paid_amount' => $payable_amount,
                'payable_currency' => getSessionCurrency(),
                'transaction_id' => Str::random(10),
                'order_type' => $order_type,
                'order_details' => $order_details,
            ]);

            foreach ($carts as $item) {
                // 2026-06-12 — effective sale price on the line item (see online
                // checkout above). Free path too: a 100%-off / 0-charge order
                // should still record the course's actual sale price.
                $item_price = $order->isBundleOrder() ? ($order->payable_amount / max(1, $carts->count())) : $item->course->effective_price;
                OrderItem::create([
                    'order_id' => $order->id,
                    'price' => $item_price,
                    'course_id' => $item->course->id,
                ]);
                // 2026-06-06 — multi-batch: key on (user, course, batch). This
                // checkout has no batch picker, so the enrollment is course-wide
                // (batch_id null); keying on the batch keeps it from matching a
                // student's existing BATCHED enrollment for the same course.
                Enrollment::firstOrCreate([
                    'user_id' => $user->id,
                    'course_id' => $item->course->id,
                    'batch_id' => null,
                ], [
                    'order_id' => $order->id,
                    'has_access' => 1,
                ]);

            }

            DB::commit();
            // F12 (audit 2026-06-26) — clear the cart for a normal free-COURSE
            // order too. Previously only bundle orders cleared (and $bundle_slug
            // is undefined on the regular path → warning), so free course items
            // stayed in the cart and could be re-submitted = duplicate orders.
            if (in_array($order_type, ['course', 'bundle'], true)) {
                $user->carts()->delete();
            }

            $notification = trans('Payment Success.');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];

            return view('frontend.pages.order-success')->with($notification);
        } catch (Exception $e) {
            DB::rollBack();
            info($e->getMessage());
            $notification = __('Payment faild, please try again');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);

        }
    }

    public function pay_via_paypal()
    {
        $resolvedGw = $this->resolveGatewayForSessionOrder('paypal');
        $paypal_credentials = (object) [
            'paypal_client_id' => $resolvedGw->credential('paypal_client_id'),
            'paypal_secret_key' => $resolvedGw->credential('paypal_secret_key'),
            'paypal_app_id' => $resolvedGw->credential('paypal_app_id'),
            'paypal_account_mode' => $resolvedGw->credential('paypal_account_mode') ?: 'sandbox',
        ];

        $after_success_url = route('payment-success');
        $after_failed_url = route('payment-failed');

        $paypal_payment = new FrontPaymentController;

        return $paypal_payment->pay_with_paypal($paypal_credentials, $after_success_url, $after_failed_url);
    }

    public function pay_via_stripe()
    {
        // Coach-specific gateway (2026-06-29) — use the coach's Stripe secret when
        // the order was stamped to a coach config, else the platform default.
        $resolvedGw = $this->resolveGatewayForSessionOrder('stripe');
        \Stripe\Stripe::setApiKey($resolvedGw->credential('stripe_secret'));

        $after_failed_url = route('payment-failed');

        session()->put('after_failed_url', $after_failed_url);

        $payable_currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        $allCurrencyCodes = $this->paymentService->getSupportedCurrencies($this->paymentService::STRIPE);

        if (in_array(Str::upper($payable_currency), $allCurrencyCodes['non_zero_currency_codes'])) {
            $payable_with_charge = $paid_amount;
        } elseif (in_array(Str::upper($payable_currency), $allCurrencyCodes['three_digit_currency_codes'])) {
            $convertedCharge = (string) $paid_amount.'0';
            $payable_with_charge = (int) $convertedCharge;
        } else {
            $payable_with_charge = (int) ($paid_amount * 100);
        }

        // Order id needed by the webhook handler so it can locate the right
        // order without depending on the user's session.
        $sessionOrder = session()->get('order');
        $orderId = $sessionOrder?->id ? (string) $sessionOrder->id : null;

        $checkoutSession = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $payable_currency,
                    'unit_amount' => $payable_with_charge,
                    'product_data' => [
                        'name' => cache()->get('setting')->app_name,
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/pay-via-stripe').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $after_failed_url,
            'metadata' => $orderId ? ['order_id' => $orderId] : [],
        ]);

        // Redirect to the checkout session URL
        return redirect()->away($checkoutSession->url);

    }

    public function stripe_success(Request $request)
    {
        $after_success_url = route('payment-success');
        // Resolve the SAME gateway the order was created with (coach or default).
        $resolvedGw = $this->resolveGatewayForSessionOrder('stripe');

        // Assuming the Checkout Session ID is passed as a query parameter
        $session_id = $request->query('session_id');
        if ($session_id) {
            \Stripe\Stripe::setApiKey($resolvedGw->credential('stripe_secret'));

            $session = \Stripe\Checkout\Session::retrieve($session_id);

            // 2026-06-01 (audit C3/C4) — VERIFY the session actually paid
            // before treating it as success. Previously any retrievable
            // session_id was accepted, so an unpaid/expired/open session
            // (or one whose checkout was abandoned) still fulfilled the
            // order. Stripe marks a completed payment with
            // payment_status='paid' (and status='complete').
            $stripePaid = ($session->payment_status ?? null) === 'paid'
                || ($session->status ?? null) === 'complete';
            if (! $stripePaid) {
                \Log::warning('stripe_success rejected — session not paid', [
                    'session_id'     => $session_id,
                    'payment_status' => $session->payment_status ?? null,
                    'status'         => $session->status ?? null,
                ]);
                $after_failed_url = session()->get('after_failed_url') ?: route('payment-failed');

                return redirect($after_failed_url);
            }

            $paymentDetails = [
                'transaction_id' => $session->payment_intent,
                'amount' => $session->amount_total,
                'currency' => $session->currency,
                'payment_status' => $session->payment_status,
                'created' => $session->created,
            ];
            session()->put('after_success_url', $after_success_url);
            session()->put('after_success_transaction', $session->payment_intent);
            session()->put('payment_details', $paymentDetails);

            return redirect($after_success_url);
        }

        $after_failed_url = session()->get('after_failed_url');

        return redirect($after_failed_url);
    }

    public function pay_via_razorpay(Request $request)
    {
        // Coach-specific gateway (2026-06-29) — resolve the Razorpay creds the order
        // was stamped with (coach config or platform default).
        $resolvedGw = $this->resolveGatewayForSessionOrder('razorpay');

        $after_success_url = route('payment-success');
        $after_failed_url = route('payment-failed');

        $razorpay_credentials = (object) [
            'razorpay_key' => $resolvedGw->credential('razorpay_key'),
            'razorpay_secret' => $resolvedGw->credential('razorpay_secret'),
        ];

        return $this->pay_with_razorpay($request, $razorpay_credentials, $request->payable_amount, $after_success_url, $after_failed_url);

    }

    public function pay_with_razorpay(Request $request, $razorpay_credentials, $payable_amount, $after_success_url, $after_failed_url)
    {
        $input = $request->all();
        $api = new Api($razorpay_credentials->razorpay_key, $razorpay_credentials->razorpay_secret);
        $payment = $api->payment->fetch($input['razorpay_payment_id']);
        if (count($input) && ! empty($input['razorpay_payment_id'])) {
            try {
                $response = $api->payment->fetch($input['razorpay_payment_id'])->capture(['amount' => $payment['amount']]);

                $paymentDetails = [
                    'transaction_id' => $response->id,
                    'amount' => $response->amount,
                    'currency' => $response->currency,
                    'fee' => $response->fee,
                    'description' => $response->description,
                    'payment_method' => $response->method,
                    'status' => $response->status,
                ];

                Session::put('after_success_url', $after_success_url);
                Session::put('after_failed_url', $after_failed_url);
                Session::put('after_success_transaction', $response->id);
                Session::put('payment_details', $paymentDetails);

                return redirect($after_success_url);

            } catch (Exception $e) {
                info($e->getMessage());

                return redirect($after_failed_url);
            }
        } else {
            return redirect($after_failed_url);
        }

    }

    public function flutterwave_payment(Request $request)
    {
        $payment_setting = $this->get_payment_gateway_info();
        $curl = curl_init();
        $tnx_id = $request->tnx_id;
        $url = "https://api.flutterwave.com/v3/transactions/$tnx_id/verify";
        $token = $payment_setting?->flutterwave_secret_key;
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer $token",
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        $response = json_decode($response);
        if ($response->status == 'success') {
            $paymentDetails = [
                'status' => $response->status,
                'trx_id' => $tnx_id,
                'amount' => $response?->data?->amount,
                'amount_settled' => $response?->data?->amount_settled,
                'currency' => $response?->data?->currency,
                'charged_amount' => $response?->data?->charged_amount,
                'app_fee' => $response?->data?->app_fee,
                'merchant_fee' => $response?->data?->merchant_fee,
                'card_last_4digits' => $response?->data?->card?->last_4digits,
            ];
            Session::put('payment_details', $paymentDetails);
            Session::put('after_success_transaction', $tnx_id);

            return response()->json(['messege' => 'Payment Success.']);

        } else {
            $notification = __('Payment faild, please try again');

            return response()->json(['messege' => $notification], 403);
        }

    }

    public function paystack_payment(Request $request)
    {
        $payment_setting = $this->get_payment_gateway_info();

        $reference = $request->reference;
        $transaction = $request->tnx_id;
        $secret_key = $payment_setting?->paystack_secret_key;
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/$reference",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $secret_key",
                'Cache-Control: no-cache',
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $final_data = json_decode($response);
        if ($final_data->status == true) {
            $paymentDetails = [
                'status' => $final_data?->data?->status,
                'transaction_id' => $transaction,
                'requested_amount' => $final_data?->data->requested_amount,
                'amount' => $final_data?->data?->amount,
                'currency' => $final_data?->data?->currency,
                'gateway_response' => $final_data?->data?->gateway_response,
                'paid_at' => $final_data?->data?->paid_at,
                'card_last_4_digits' => $final_data?->data->authorization?->last4,
            ];
            Session::put('payment_details', $paymentDetails);
            Session::put('after_success_transaction', $transaction);

            return response()->json(['messege' => 'Payment Success.']);
        } else {
            $notification = __('Payment faild, please try again');

            return response()->json(['messege' => $notification], 403);
        }
    }

    public function pay_via_mollie()
    {
        $after_success_url = route('payment-success');
        $after_failed_url = route('payment-failed');

        session()->put('after_success_url', $after_success_url);
        session()->put('after_failed_url', $after_failed_url);

        $payment_setting = $this->get_payment_gateway_info();

        $mollie_credentials = (object) [
            'mollie_key' => $payment_setting->mollie_key,
        ];

        return $this->pay_with_mollie($mollie_credentials);
    }

    public function pay_with_mollie($mollie_credentials)
    {
        $payable_currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        try {
            $mollie = new \Mollie\Api\MollieApiClient;
            $mollie->setApiKey($mollie_credentials->mollie_key);

            $payment = $mollie->payments->create([
                'amount' => [
                    'currency' => "$payable_currency",
                    'value' => "$paid_amount",
                ],
                'description' => userAuth()?->name,
                'redirectUrl' => route('mollie-payment-success'),
            ]);
            $payment = $mollie->payments->get($payment->id);

            session()->put('payment_id', $payment->id);
            session()->put('mollie_credentials', $mollie_credentials);

            return redirect($payment->getCheckoutUrl(), 303);

        } catch (Exception $ex) {
            info($ex);
            info($ex->getMessage());
            $notification = __('Payment faild, please try again');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);
        }

    }

    public function mollie_payment_success()
    {
        $mollie_credentials = Session::get('mollie_credentials');

        $mollie = new \Mollie\Api\MollieApiClient;
        $mollie->setApiKey($mollie_credentials->mollie_key);
        $payment = $mollie->payments->get(session()->get('payment_id'));

        if ($payment->isPaid()) {
            $paymentDetails = [
                'transaction_id' => $payment->id,
                'amount' => $payment->amount->value,
                'currency' => $payment->amount->currency,
                'fee' => $payment->settlementAmount->value.' '.$payment->settlementAmount->currency,
                'description' => $payment->description,
                'payment_method' => $payment->method,
                'status' => $payment->status,
                'paid_at' => $payment->paidAt,
            ];

            Session::put('payment_details', $paymentDetails);
            Session::put('after_success_transaction', session()->get('payment_id'));

            $after_success_url = Session::get('after_success_url');

            return redirect($after_success_url);

        } else {
            $after_failed_url = Session::get('after_failed_url');

            return redirect($after_failed_url);
        }
    }

    public function pay_via_instamojo()
    {
        $after_success_url = route('payment-success');
        $after_failed_url = route('payment-failed');

        session()->put('after_success_url', $after_success_url);
        session()->put('after_failed_url', $after_failed_url);

        $payment_setting = $this->get_payment_gateway_info();

        $instamojo_credentials = (object) [
            'instamojo_api_key' => $payment_setting->instamojo_api_key,
            'instamojo_auth_token' => $payment_setting->instamojo_auth_token,
            'account_mode' => $payment_setting->instamojo_account_mode,
        ];

        return $this->pay_with_instamojo($instamojo_credentials);
    }

    public function pay_with_instamojo($instamojo_credentials)
    {
        $payable_currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        $environment = $instamojo_credentials->account_mode;
        $api_key = $instamojo_credentials->instamojo_api_key;
        $auth_token = $instamojo_credentials->instamojo_auth_token;

        if ($environment == 'Sandbox') {
            $url = 'https://test.instamojo.com/api/1.1/';
        } else {
            $url = 'https://www.instamojo.com/api/1.1/';
        }

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url.'payment-requests/');
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER,
                ["X-Api-Key:$api_key",
                    "X-Auth-Token:$auth_token"]);
            $payload = [
                'purpose' => env('APP_NAME'),
                'amount' => $paid_amount,
                'phone' => '918160651749',
                'buyer_name' => userAuth()?->name,
                'redirect_url' => route('instamojo-success'),
                'send_email' => true,
                'webhook' => 'http://www.example.com/webhook/',
                'send_sms' => true,
                'email' => userAuth()?->email,
                'allow_repeated_payments' => false,
            ];
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            $response = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($response);
            session()->put('instamojo_credentials', $instamojo_credentials);

            if (! empty($response?->payment_request?->longurl)) {
                return redirect($response?->payment_request?->longurl);
            } else {
                return redirect()->route('student.orders.index')->with(['messege' => __('Payment faild, please try again'), 'alert-type' => 'error']);
            }

        } catch (Exception $ex) {
            info($ex->getMessage());
            $notification = __('Payment faild, please try again');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->back()->with($notification);
        }

    }

    public function instamojo_success(Request $request)
    {

        $instamojo_credentials = Session::get('instamojo_credentials');

        $input = $request->all();
        $environment = $instamojo_credentials->account_mode;
        $api_key = $instamojo_credentials->instamojo_api_key;
        $auth_token = $instamojo_credentials->instamojo_auth_token;

        if ($environment == 'Sandbox') {
            $url = 'https://test.instamojo.com/api/1.1/';
        } else {
            $url = 'https://www.instamojo.com/api/1.1/';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url.'payments/'.$request->get('payment_id'));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ["X-Api-Key:$api_key",
                "X-Auth-Token:$auth_token"]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $after_failed_url = Session::get('after_failed_url');

            return redirect($after_failed_url);
        } else {
            $data = json_decode($response);
        }

        if ($data->success == true) {
            if ($data->payment->status == 'Credit') {
                Session::put('after_success_transaction', $request->get('payment_id'));
                Session::put('paid_amount', $data->payment->amount);
                $after_success_url = Session::get('after_success_url');

                return redirect($after_success_url);
            }
        } else {
            $after_failed_url = Session::get('after_failed_url');

            return redirect($after_failed_url);
        }
    }

    /**
     * Major-unit captured amount to reconcile against the order, core gateways
     * only. Razorpay/Stripe are subunits (/100); PayPal is major units. Other
     * methods → null (no reconciliation, unchanged behaviour).
     */
    private function reconcilableCapturedAmount($order, $payment_details): ?float
    {
        $amt = is_array($payment_details) ? ($payment_details['amount'] ?? null) : null;
        if ($amt === null || $amt === '') {
            return null;
        }
        return match (strtolower((string) ($order->payment_method ?? ''))) {
            'razorpay', 'stripe' => ((float) $amt) / 100,
            'paypal'             => (float) $amt,
            default              => null,
        };
    }

    public function payment_success()
    {
        $sessionOrder = session()->get('order');
        $after_success_transaction = session()->get('after_success_transaction', null);
        $payment_details = session()->get('payment_details', null);

        if (!$sessionOrder) {
            return redirect()->route('home');
        }

        $method_bank_or_offline = in_array($sessionOrder->payment_method, [$this->paymentService::BANK_PAYMENT, $this->paymentService::OFFLINE_PAYMENT]);

        // 2026-06-01 (audit C3/C4) — PAYMENT-BYPASS GUARD.
        //
        // payment_success previously marked the order paid + granted access
        // off nothing but session('order'). An attacker could (1) start
        // checkout (placeOrder creates a PENDING order + stores it in
        // session), (2) get redirected to the gateway, then (3) navigate
        // straight to /payment-success WITHOUT paying — and walk away
        // enrolled for free.
        //
        // Every gateway success handler (stripe_success, pay_with_razorpay,
        // flutterwave_payment, paystack_payment, mollie_payment_success,
        // bkash/coingate/…) writes `after_success_transaction` to the session
        // ONLY AFTER it has verified the charge SERVER-SIDE with the gateway.
        // So a missing reference means no verified payment happened — refuse
        // to fulfil. Bank/offline legitimately carry a generated reference and
        // are recorded as PENDING (fulfilled later on admin approval), so they
        // pass this guard too.
        if (! $method_bank_or_offline && empty($after_success_transaction)) {
            \Log::warning('payment_success blocked — no gateway-verified transaction reference', [
                'order_id'        => $sessionOrder->id ?? null,
                'payment_method'  => $sessionOrder->payment_method ?? null,
            ]);
            $this->orderSessionForget();

            return redirect()->route('payment-failed')->with([
                'messege'    => __('We could not verify your payment. If any amount was deducted it will be refunded automatically. Please try again.'),
                'alert-type' => 'error',
            ]);
        }

        // H1 (coach-gateway hardening 2026-06-29) — reconcile the gateway-captured
        // amount against the order for the wired core gateways, so a tampered/stale
        // session amount can't under-charge a coach's own merchant account.
        $verifiedAmount = $this->reconcilableCapturedAmount($sessionOrder, $payment_details);

        try {
            $alreadyProcessed = false;

            $order = \DB::transaction(function () use ($sessionOrder, $after_success_transaction, $payment_details, $method_bank_or_offline, $verifiedAmount, &$alreadyProcessed) {
                // Use the imported Order class (Modules\Order\...\Order — see
                // the `use` block at top of file). The non-modular App\\Models
                // namespace does not contain an Order class in this codebase;
                // referencing it crashed payment_success in incident 2026-05-26.
                $order = Order::lockForUpdate()->findOrFail($sessionOrder->id);

                if ($order->payment_status === 'paid') {
                    $alreadyProcessed = true;
                    return $order;
                }

                // 2026-06-01 (audit C3/C4 + C5) — REGULAR online orders are
                // fulfilled through the SAME canonical service the webhooks
                // use, so the redirect path can never drift from it. markPaid()
                // sets payment_status=paid, credits each coach PER ITEM
                // (OrderItem::coachPayout — audit H9), creates enrollments
                // WITH batch_id (the inline path used to drop it), auto-links
                // the buyer to each coach, records coupon usage, and enforces
                // batch capacity — all idempotently.
                if (! $method_bank_or_offline && ! $order->isGiftOrder()) {
                    app(\App\Services\PaymentFulfilmentService::class)
                        ->markPaid($order, $after_success_transaction, $payment_details, $verifiedAmount);

                    return $order->refresh();
                }

                // Bank/offline OR gift orders keep their bespoke handling.
                //   - bank/offline  → recorded PENDING; wallet + enrollment
                //     happen later when an admin approves the receipt
                //     (Order\OrderController::updateOrder). No wallet credit
                //     here (FT-PAY-10 double-credit fix).
                //   - gift          → paid now, coach credited per item, but
                //     the RECIPIENT (not the buyer) is enrolled via the gift
                //     claim flow, so markPaid (which enrols the buyer) is not
                //     used here.
                $order->transaction_id   = $after_success_transaction;
                $order->payment_status   = $method_bank_or_offline ? 'pending' : 'paid';
                $order->status           = 'completed';
                $order->payment_details  = $method_bank_or_offline ? $payment_details : json_encode($payment_details);
                $order->save();

                if (! $method_bank_or_offline && $order->isGiftOrder()) {
                    // gift: per-item coach payout (audit H9 shared helper)
                    foreach ($order->orderItems as $item) {
                        $course = Course::withTrashed()->find($item->course_id);
                        $instructor = $course?->instructor;
                        if ($instructor) {
                            $instructor->increment('wallet_balance', $item->coachPayout((float) $order->commission_rate));
                        }
                    }
                    $this->giftOrderDetailsUpdate($order);
                }

                // Coupon usage for the bespoke (gift + bank/offline) paths.
                // Regular orders had theirs recorded inside markPaid() above,
                // so this does NOT run for them — no double increment.
                if (!empty($order->coupon_code)) {
                    $coupon = \DB::table('coupons')->where('coupon_code', $order->coupon_code)->first();
                    if ($coupon) {
                        \DB::table('coupons')->where('id', $coupon->id)->increment('usage_count');
                        \DB::table('coupon_uses')->insert([
                            'coupon_id'  => $coupon->id,
                            'user_id'    => $order->buyer_id,
                            'order_id'   => $order->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                return $order;
            });

            if (!$alreadyProcessed) {
                $user = userAuth();
                $this->sendingPaymentStatusMail([
                    'email'          => $user->email,
                    'name'           => $user->name,
                    'order_id'       => $order->invoice_id,
                    'paid_amount'    => $order->paid_amount.' '.$order->payable_currency,
                    'payment_status' => $order->payment_status,
                ]);
            }

            // 2026-05-26 — honour the return_to that CaptureSiteAttribution
            // Hand the student BACK to the coach's branded site so the URL
            // stays coach-scoped through every step of the journey. Two
            // session keys can drive this:
            //
            //   coach_site_return_to  — explicit URL captured by the
            //                          CaptureSiteAttribution middleware
            //                          when the student clicked Proceed-
            //                          to-secure-checkout. Has priority.
            //
            //   tenant_coach_id       — coach the student was buying from
            //                          (stamped by CoachCheckoutController
            //                          and TenantContext middleware).
            //                          Used to build the coach-scoped
            //                          student dashboard URL when no
            //                          explicit return_to was set.
            //
            // Either way we end with ?paid=1 so the coach master shows a
            // green "Payment successful" banner with an "Open my courses"
            // button. Only ~1 second of platform chrome on the gateway
            // callback hop — industry-standard.
            $returnTo      = session()->pull('coach_site_return_to');
            $tenantCoachId = (int) session()->pull('tenant_coach_id', 0);

            $this->orderSessionForget();

            $notification = __('Payment Success.');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];

            // Priority 1: explicit return URL
            if (is_string($returnTo) && $returnTo !== '') {
                $sep = str_contains($returnTo, '?') ? '&' : '?';
                return redirect()->away($returnTo . $sep . 'paid=1')->with($notification);
            }

            // Priority 2: derive coach dashboard URL from tenant_coach_id
            if ($tenantCoachId > 0) {
                $landing = \App\Models\CoachLandingPage::query()
                    ->where('added_by', $tenantCoachId)
                    ->first();
                $coachSlug = $landing?->slug;
                if ($coachSlug) {
                    try {
                        $coachDashboard = route(
                            'coach.student.courses',
                            ['coachSlug' => $coachSlug]
                        ) . '?paid=1';
                        return redirect()->away($coachDashboard)->with($notification);
                    } catch (\Throwable $e) {
                        // Route not registered (route-cache not refreshed?)
                        // — fall through to the platform success view.
                        \Log::warning('coach-dashboard-redirect-failed', [
                            'coach_id' => $tenantCoachId,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            }

            return view('frontend.pages.order-success')->with($notification);
        } catch (Exception $e) {
            \Log::error('payment_success failed', ['order_id' => $sessionOrder->id ?? null, 'err' => $e->getMessage()]);
            $notification = trans('Payment faild, please try again');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];

            return redirect()->route('payment-failed')->with($notification);
        }
    }

    public function payment_failed()
    {
        // 2026-06-17 — guard against a missing session order. A direct hit on
        // /payment-failed (expired session, double-visit, back-button, crafted
        // URL) left $order null → "property on null" fatal 500. Degrade to a
        // friendly redirect instead.
        $order = session()->get('order');
        if (! $order) {
            $this->orderSessionForget();

            return redirect()->route('home')->with([
                'messege'    => __('No active order found.'),
                'alert-type' => 'error',
            ]);
        }
        $order->payment_status = 'cancelled';
        $order->save();

        $user = userAuth();
        // send mail
        $this->sendingPaymentStatusMail([
            'email' => $user->email,
            'name' => $user->name,
            'order_id' => $order->invoice_id,
            'paid_amount' => $order->paid_amount.' '.$order->payable_currency,
            'payment_status' => $order->payment_status,
        ]);

        $this->orderSessionForget();

        $notification = trans('Payment faild, please try again');
        $notification = ['messege' => $notification, 'alert-type' => 'error'];

        return view('frontend.pages.order-fail')->with($notification);
    }

    public function handleMailSending(array $mailData)
    {
        try {
            self::setMailConfig();

            // Get email template
            $template = EmailTemplate::where('name', 'order_completed')->firstOrFail();
            $mailData['subject'] = $template->subject;

            // White-label: brand the email as the order's coach (resolved from
            // the invoice id) so payment receipts/status mails carry the coach
            // brand. Null/absent -> platform (backward compatible).
            if (!isset($mailData['coach_id']) && !empty($mailData['order_id'])) {
                $mailData['coach_id'] = \Modules\Order\app\Models\Order::where('invoice_id', $mailData['order_id'])->value('primary_coach_id');
            }

            // Prepare email content
            $message = str_replace('{{name}}', $mailData['name'], $template->message);
            $message = str_replace('{{order_id}}', $mailData['order_id'], $message);
            $message = str_replace('{{paid_amount}}', $mailData['paid_amount'], $message);
            $message = str_replace('{{payment_method}}', $mailData['payment_method'], $message);
            $message = strip_unresolved_tokens($message); // Phase 1.7: no raw {{token}} leaks

            if (self::isQueable()) {
                DefaultMailJob::dispatch($mailData['email'], $mailData, $message);
            } else {
                Mail::to($mailData['email'])->send(new DefaultMail($mailData, $message));
            }
        } catch (Exception $e) {
            info($e->getMessage());
        }
    }

    public function sendingPaymentStatusMail(array $mailData)
    {
        try {
            self::setMailConfig();

            // Get email template
            $template = EmailTemplate::where('name', 'payment_status')->firstOrFail();
            $mailData['subject'] = $template->subject;

            // White-label: brand the email as the order's coach (resolved from
            // the invoice id) so payment receipts/status mails carry the coach
            // brand. Null/absent -> platform (backward compatible).
            if (!isset($mailData['coach_id']) && !empty($mailData['order_id'])) {
                $mailData['coach_id'] = \Modules\Order\app\Models\Order::where('invoice_id', $mailData['order_id'])->value('primary_coach_id');
            }

            // Prepare email content
            $message = str_replace('{{name}}', $mailData['name'], $template->message);
            $message = str_replace('{{order_id}}', $mailData['order_id'], $message);
            $message = str_replace('{{paid_amount}}', $mailData['paid_amount'], $message);
            $message = str_replace('{{payment_status}}', $mailData['payment_status'], $message);
            $message = strip_unresolved_tokens($message); // Phase 1.7: no raw {{token}} leaks

            if (self::isQueable()) {
                DefaultMailJob::dispatch($mailData['email'], $mailData, $message);
            } else {
                Mail::to($mailData['email'])->send(new DefaultMail($mailData, $message));
            }
        } catch (Exception $e) {
            info($e->getMessage());
        }
    }

    private function orderSessionForget()
    {
        session()->forget(
            [
                'after_success_url',
                'after_failed_url',
                'order',
                'payable_amount',
                'gateway_charge',
                'after_success_gateway',
                'after_success_transaction',
                'subscription_plan_id',
                'payable_with_charge',
                'payable_currency',
                'subscription_plan_id',
                'paid_amount',
                'payment_details',
                'cart',
                'coupon_code',
                'offer_percentage',
                'coupon_discount_amount',
                'gateway_charge_in_usd',
            ]);
    }
}
