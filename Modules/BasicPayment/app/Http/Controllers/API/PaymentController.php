<?php

namespace Modules\BasicPayment\app\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\BankInformationRequest;
use App\Jobs\DefaultMailJob;
use App\Mail\DefaultMail;
use App\Models\Cart;
use App\Models\Course;
use App\Traits\GetGlobalInformationTrait;
use App\Traits\MailSenderTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Modules\BasicPayment\app\Enums\BasicPaymentSupportedCurrencyListEnum;
use Modules\GlobalSetting\app\Models\EmailTemplate;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Modules\Order\app\Models\OrderItem;
use Mollie\Laravel\Facades\Mollie;
use Razorpay\Api\Api;

class PaymentController extends Controller {
    use GetGlobalInformationTrait, MailSenderTrait;
    private $paymentService;
    public function __construct() {
        $this->paymentService = app(\Modules\BasicPayment\app\Services\PaymentMethodService::class);
    }
    public function all_payment(): JsonResponse {
        $data = $this->paymentService->getActiveGatewaysWithDetails();
        if ($data) {
            return response()->json(['status' => 'success', 'data' => $data], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Not Found!'], 404);
    }
    public function pay_via_bank(BankInformationRequest $request) {
        $bankDetails = json_encode($request->only(['bank_name', 'account_number', 'routing_number', 'branch', 'transaction']));

        $allPayments = Order::whereNotNull('payment_details')->get();

        foreach ($allPayments as $payment) {
            $paymentDetailsJson = json_decode($payment?->payment_details, true);

            if (isset($paymentDetailsJson['account_number']) && $paymentDetailsJson['account_number'] == $request->account_number) {
                if (isset($paymentDetailsJson['transaction']) && $paymentDetailsJson['transaction'] == $request->transaction) {
                    $after_failed_url = route('payment-api.webview-failed-payment');
                    return redirect($after_failed_url);
                }
            }
        }
        Session::put('after_success_transaction', $request->transaction);
        Session::put('payment_details', $bankDetails);

        $after_success_url = route('payment-api.webview-success-payment', ['bearer_token' => request()->bearer_token]);
        return redirect($after_success_url);
    }
    public function pay_via_offline(Request $request) {
        $request->validate([
            // FT-UPLOAD-1 fix (2026-05-27) — dropped svg (XSS).
            'payment_receipt' => 'required|mimes:jpeg,jpg,png,gif,webp,pdf,docx|max:2048'
        ], [
            'payment_receipt.required' => __('Offline Payment Receipt is required'),
            'payment_receipt.mimes'    => __('The Offline Payment Receipt must be a file of type: jpeg, jpg, png, gif, webp, pdf, docx.'),
            'payment_receipt.max'      => __('The Offline Payment Receipt may not be greater than 2048 kilobytes.'),
        ]);
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }
        $order_id = $request?->order_id ?? null;
        $order = $user?->orders()->where('invoice_id', $order_id)->where('status', 'pending')->first();
        if (!$order) {
            abort(404);
        }
        if ($request->hasFile('payment_receipt')) {
            // V1 fix (2026-06-16) — store receipt on the PRIVATE disk (outside
            // web root); served only via the admin-gated download route.
            $file_name = \App\Support\PrivateMedia::store($request->payment_receipt, 'payment-receipts');
            Session::put('after_success_transaction', uniqid('offline_txn_', true));
            Session::put('payment_details', $file_name);
        }
        $after_success_url = route('payment-api.webview-success-payment', ['bearer_token' => request()->bearer_token]);
        return redirect($after_success_url);
    }
    public function placeOrder($paymentMethod) {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'UnAuthenticated'], 401);
        }

        // F16 (audit 2026-06-26) — re-validate the session coupon at order time
        // (mirror the web placeOrder). Without this, a coupon deactivated/expired
        // or for another coach's surface between cart render and pay is still
        // honoured on the mobile checkout.
        if (Session::has('coupon_code')) {
            $couponRow = \Modules\Coupon\app\Models\Coupon::where([
                'coupon_code' => Session::get('coupon_code'),
                'status'      => 'active',
            ])->first();
            $expired = $couponRow && $couponRow->expired_date && $couponRow->expired_date < date('Y-m-d');
            $tenantCoachId = \App\Support\TenantAccess::checkoutCoachId(request());
            $wrongSurface = $couponRow && ! $couponRow->isUsableOnCoachSurface($tenantCoachId);
            if (! $couponRow || $expired || $wrongSurface) {
                Session::forget('coupon_code');
                Session::forget('offer_percentage');
                Session::forget('coupon_discount_amount');
                Session::put('payable_amount', $user->cart_total);
            }
        }

        $activeGateways = array_keys($this->paymentService->getActiveGatewaysWithDetails());
        if (!in_array($paymentMethod, $activeGateways)) {
            return response()->json(['status' => 'error', 'message' => 'The selected payment method is now inactive.'], 400);
        }

        $payable_currency = strtoupper(request()->query('currency', 'USD'));

        if (!$this->paymentService->isCurrencySupported($paymentMethod, $payable_currency)) {
            $supportedCurrencies = $this->paymentService->getSupportedCurrencies($paymentMethod);
            return response()->json(['status' => 'error', 'message' => 'You are trying to use unsupported currency', 'supportCurrency' => sprintf(
                '%s %s: %s',
                strtoupper($paymentMethod),
                'supports only these types of currencies',
                implode(', ', $supportedCurrencies)
            )], 400);
        }

        if ($user->cart_count == 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please add some courses in your cart.',
            ], 404);

        }

        try {
            $payable_amount = $user->cart_total;
            // 2026-06-13 — tax (opt-in). Resolve the order's primary coach from
            // the cart; the gateway must collect taxable + tax. Zero when off.
            $firstCartCourse = $user->carts()->with('course:id,instructor_id,tax_rate_id')->first()?->course;
            $cartItemCount = $user->carts()->count();
            $taxSvc = app(\App\Services\Tax\TaxService::class);
            $tax = $taxSvc->computeForOrder(
                $firstCartCourse?->instructor_id,
                (float) $payable_amount,
                $cartItemCount === 1 ? $firstCartCourse?->tax_rate_id : null
            );
            $calculatePayableCharge = $this->paymentService->getPayableAmount($paymentMethod, $tax['charge_total'], $payable_currency);

            DB::beginTransaction();

            $paid_amount = $calculatePayableCharge?->payable_amount + $calculatePayableCharge?->gateway_charge;

            if (in_array($paymentMethod, ['Razorpay', 'Stripe'])) {
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
                'invoice_id'              => Str::random(10),
                'buyer_id'                => $user->id,
                'has_coupon'              => Session::has('coupon_code') ? 1 : 0,
                'coupon_code'             => Session::get('coupon_code'),
                'coupon_discount_percent' => Session::get('offer_percentage'),
                'coupon_discount_amount'  => Session::get('coupon_discount_amount'),
                'payment_method'          => $paymentMethod,
                'payment_status'          => 'pending',
                // payable_amount = pre-tax revenue base (commission base); the
                // buyer pays charge_total (taxable + tax).
                'payable_amount'          => $tax['taxable_amount'],
                'tax_amount'              => $tax['tax_amount'],
                'taxable_amount'          => $tax['taxable_amount'],
                'tax_rate_applied'        => $tax['rate'],
                'tax_mode'                => $tax['has_tax'] ? $tax['mode'] : null,
                'tax_label'               => $tax['has_tax'] ? $tax['label'] : null,
                'tax_registration'        => $tax['has_tax'] ? $tax['registration'] : null,
                'tax_components'          => $tax['has_tax'] ? $tax['components'] : null,
                'gateway_charge'          => $calculatePayableCharge?->gateway_charge,
                'payable_with_charge'     => $calculatePayableCharge?->payable_with_charge,
                'paid_amount'             => $paid_amount,
                'payable_currency'        => $calculatePayableCharge?->currency_code,
                'conversion_rate'         => $calculatePayableCharge?->currency_rate,
                'commission_rate'         => Cache::get('setting')->commission_rate,
            ]);

            // Coach-specific gateway provenance (2026-06-29). Stamp WHICH gateway
            // config will process this order so the callback/webhook re-resolves
            // the same credentials. Core wired gateways (Razorpay, Stripe, PayPal);
            // a mixed multi-coach cart resolves to the platform default (one charge
            // can't split across merchant accounts).
            if (\App\Services\Payment\GatewayFieldRegistry::isCore(strtolower((string) $paymentMethod))) {
                $gwOwnerIds = $user->carts()->with('course:id,instructor_id')->get()
                    ->map(fn ($c) => (int) ($c->course->instructor_id ?? 0))->all();
                $resolvedGw = app(\App\Services\Payment\CheckoutGatewayResolver::class)
                    ->resolveForOwners($gwOwnerIds, strtolower((string) $paymentMethod));
                $order->gateway_owner_type = $resolvedGw->ownerType;
                $order->gateway_config_id  = $resolvedGw->configId;
                $order->gateway_coach_id   = $resolvedGw->coachId;
                $order->save();
            }
            $data_layer_order_items = [];

            // Audit 2026-05-18 — include batch_id so the order_items + enrollments rows carry the chosen batch.
            $carts = $user->carts()->with('course:id,title,slug,price,discount')->get(['id', 'user_id', 'course_id', 'batch_id']);

            foreach ($carts as $item) {
                // Audit 2026-05-18 Req 3 — per-coach commission.
                // Resolve the rate from the item's course instructor; fall
                // back to global if the instructor row is missing.
                $itemInstructor = Course::find($item->course->id)?->instructor;
                $itemRate = $itemInstructor
                    ? $itemInstructor->effectiveCommissionRate()
                    : (float) (Cache::get('setting')->commission_rate ?? 0);

                // 2026-06-12 — line item carries the EFFECTIVE sale price
                // ($course->discount ?: price), matching what the buyer is
                // charged. Storing course->price showed the MRP on the invoice
                // while only the discounted price was paid (price mismatch).
                $order_item = [
                    'order_id'        => $order->id,
                    'price'           => $item->course->effective_price,
                    'course_id'       => $item->course->id,
                    'commission_rate' => $itemRate,
                ];
                // 2026-06-13 — per-line tax = this line's share of the order tax.
                $lineShare = $payable_amount > 0 ? ($item->course->effective_price / $payable_amount) : 0;
                $lineTax = round(($tax['tax_amount'] ?? 0) * $lineShare, 2);
                OrderItem::create([
                    'order_id'        => $order->id,
                    'price'           => $item->course->effective_price,
                    'tax_amount'       => $lineTax,
                    'tax_rate_applied' => $tax['has_tax'] ? $tax['rate'] : null,
                    'course_id'       => $item->course->id,
                    // Normalize 0/empty → NULL (FK to course_batches.id; 0 is not
                    // a valid batch and violates the FK). 2026-06-11.
                    'batch_id'        => $item->batch_id ?: null, // Audit 2026-05-18
                    'commission_rate' => $itemRate,
                ]);
                $data_layer_order_items[] = [
                    'course_name' => $item->course->title,
                    'price'       => currency($item->course->effective_price),
                    'url'         => route('course.show', $item->course->slug),
                ];

                // FT-PAY-11 fix (2026-05-28) — pre-fix credited the
                // coach's wallet here at placeOrder time, BEFORE the
                // buyer had completed payment. Two failure modes:
                //   1. Non-bank/offline: buyer abandons checkout after
                //      the gateway redirect — coach keeps the credit
                //      for an unpaid order.
                //   2. Bank/offline: payment_status starts 'pending';
                //      payment_success keeps it 'pending'; admin later
                //      approves via Order\OrderController::updateOrder
                //      which fires another credit on flip-to-paid →
                //      DOUBLE credit for every bank/offline sale.
                //
                // Mirror the web FT-PAY-10 fix: defer the credit to
                // payment_success (where it now runs inside the
                // !method_bank_or_offline branch — bank/offline still
                // gets its single credit via OrderController::updateOrder
                // when admin approves).
                //
                // Audit 2026-05-18 Req 3 retained — the per-item rate
                // (computed above as $itemRate) is now picked up by
                // payment_success via the persisted
                // order_items.commission_rate column.

            }
            DB::commit();
            $user->carts()->delete();

            $settings = cache()->get('setting');
            $marketingSettings = cache()->get('marketing_setting');
            if ($user && $settings->google_tagmanager_status == 'active' && $marketingSettings->order_success) {
                $order_success = [
                    'invoice_id'       => $order->invoice_id,
                    'transaction_id'   => $order->transaction_id,
                    'payment_method'   => $order->payment_method,
                    'payable_currency' => $order->payable_currency,
                    'paid_amount'      => $order->paid_amount,
                    'payment_status'   => $order->payment_status,
                    'order_items'      => $data_layer_order_items,
                    'student_info'     => [
                        'name'  => $user->name,
                        'email' => $user->email,
                    ],
                ];
                session()->put('enrollSuccess', $order_success);
            }
            // send mail
            $this->handleMailSending([
                'email'          => $user->email,
                'name'           => $user->name,
                'order_id'       => $order->invoice_id,
                'paid_amount'    => $order->paid_amount . ' ' . $order->payable_currency,
                'payment_method' => $order->payment_method,
            ]);

            $order_id = $order?->invoice_id;
            $newToken = $user->createToken('extra-token', ['extra'], now()->addWeek())->plainTextToken;

            return response()->json(['status' => 'success', 'url' => route('payment-api.payment', ['token' => $newToken, 'order_id' => $order_id])], 200);
        } catch (Exception $e) {
            DB::rollBack();
            $data_layer_order_items = [];
            foreach ($carts as $item) {
                $data_layer_order_items[] = [
                    'course_name' => $item->course->name,
                    'price'       => currency($item->course->effective_price),
                    'url'         => route('course.show', $item->course->slug),
                ];
            }

            $settings = cache()->get('setting');
            $marketingSettings = cache()->get('marketing_setting');
            if ($settings->google_tagmanager_status == 'active' && $marketingSettings->order_failed) {
                $user = userAuth();
                $order_failed = [
                    'payable_currency' => session('payable_currency', getSessionCurrency()),
                    'paid_amount'      => session('paid_amount', null),
                    'payment_status'   => 'Failed',
                    'order_items'      => $data_layer_order_items,
                    'student_info'     => [
                        'name'  => $user->name,
                        'email' => $user->email,
                    ],
                ];
                session()->put('enrollFailed', $order_failed);
            }
            info($e->getMessage());
            return to_route('payment-api.webview-failed-payment');
        }
    }
    public function pay_via_free_gateway() {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'UnAuthenticated'], 401);
        }
        $payable_amount = $user->cart_total;
        if ($payable_amount != 0) {
            return response()->json(['status' => 'error', 'message' => 'Payment failed, please try again'], 400);
        }
        try {
            DB::beginTransaction();
            $order = Order::create([
                'invoice_id'          => Str::random(10),
                'buyer_id'            => $user->id,
                'payment_method'      => 'Free',
                'status'              => 'completed',
                'payment_status'      => 'paid',
                'payable_amount'      => $payable_amount,
                'gateway_charge'      => 0,
                'payable_with_charge' => $payable_amount,
                'paid_amount'         => $payable_amount,
                'payable_currency'    => getSessionCurrency(),
                'transaction_id'      => Str::random(10),
            ]);
            // Audit 2026-05-18 — include batch_id so the order_items + enrollments rows carry the chosen batch.
            $carts = $user->carts()->with('course:id,title,slug,price,discount')->get(['id', 'user_id', 'course_id', 'batch_id']);

            foreach ($carts as $item) {
                // Audit 2026-05-18 — propagate batch_id from cart to order_item + enrollment
                // Normalize 0/empty → NULL — batch_id is a FK to course_batches.id;
                // a literal 0 (recorded / legacy cart rows) violates the FK. 2026-06-11.
                $normalizedBatchId = $item->batch_id ?: null;
                OrderItem::create([
                    'order_id'  => $order->id,
                    'price'     => $item->course->effective_price, // 2026-06-12 — effective sale price, not MRP
                    'course_id' => $item->course->id,
                    'batch_id'  => $normalizedBatchId,
                ]);
                Enrollment::create([
                    'order_id'   => $order->id,
                    'user_id'    => $user->id,
                    'course_id'  => $item->course->id,
                    'batch_id'   => $normalizedBatchId,
                    'has_access' => 1,
                ]);
            }

            DB::commit();
            $user->carts()->delete();

            return response()->json(['status' => 'success', 'message' => 'Your order has been placed'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Your order has been fail'], 400);
        }
    }
    public function payment(Request $request) {
        $token = $request?->token ?? null;
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $user = auth('sanctum')->user();
        if (!$user) {
            abort(401);
        }
        $order_id = $request?->order_id ?? null;
        $order = $user?->orders()->where('invoice_id', $order_id)->where('status', 'pending')->first();
        if (!$order) {
            abort(404);
        }
        $paymentMethod = $order->payment_method;
        if (!$this->paymentService->isActive($paymentMethod)) {
            return response()->json(['status' => 'error', 'message' => 'The selected payment method is now inactive.'], 400);
        }

        $calculatePayableCharge = $this->paymentService->getPayableAmount($paymentMethod, $order?->payable_amount, $order?->payable_currency);

        Session::put('order', $order);
        Session::put('payable_currency', $order?->payable_currency);
        Session::put('paid_amount', $calculatePayableCharge?->payable_with_charge);

        $paymentService = $this->paymentService;
        $view = $this->paymentService->getBladeView($paymentMethod);
        // Coach-specific gateway (2026-06-29) — resolve the client-side key from
        // the order's stamped gateway config (coach or platform default) so the
        // Razorpay checkout dialog opens under the SAME account the capture uses.
        $resolvedGateway = app(\App\Services\Payment\PaymentGatewayResolverService::class)->resolveForOrder($order);
        return view($view, compact('order', 'paymentService', 'paymentMethod', 'user', 'token', 'order_id', 'resolvedGateway'));
    }
    /**
     * Major-unit captured amount to reconcile against the order, for the wired
     * core gateways only. Subunit gateways (Razorpay/Stripe) are /100 to match
     * their webhooks; PayPal is already major units. Any other method returns
     * null (no reconciliation — unchanged behaviour).
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

    public function payment_success() {
        $order = session()->get('order');
        $after_success_transaction = session()->get('after_success_transaction', null);
        $payment_details = session()->get('payment_details', null);

        $method_bank_or_offline = in_array($order->payment_method,[$this->paymentService::BANK_PAYMENT, $this->paymentService::OFFLINE_PAYMENT]);

        // SECURITY (audit 2026-06-12) — payment-bypass guard, mirroring the web
        // payment_success (audit C3/C4). Online methods must carry a gateway-
        // VERIFIED transaction reference (written only after the gateway success
        // handler verifies the charge server-side). A missing reference means no
        // verified payment happened → refuse to fulfil. Bank/offline carry a
        // generated reference and are recorded PENDING, so they still pass.
        if (! $method_bank_or_offline && empty($after_success_transaction)) {
            \Log::warning('API payment_success blocked — no gateway-verified transaction reference', [
                'order_id'       => $order->id ?? null,
                'payment_method' => $order->payment_method ?? null,
            ]);
            $this->paymentService->removeSessions();
            $image = 'fail.png';
            $title = 'Your order has been fail';
            $sub_title = __('We could not verify your payment. If any amount was deducted it will be refunded automatically. Please try again.');
            return view('basicpayment::app_order_notification', compact('image', 'title', 'sub_title'));
        }

        // H1 (coach-gateway hardening 2026-06-29) — reconcile the gateway-captured
        // amount against the order for the wired core gateways, so a tampered/stale
        // session amount can't under-charge a coach's own merchant account.
        // markPaid()'s assertAmountSufficient throws on a clear underpayment, which
        // rolls back the transaction below (order is NOT fulfilled).
        $verifiedAmount = $this->reconcilableCapturedAmount($order, $payment_details);

        try {
            // Fulfil atomically + IDEMPOTENTLY through the SAME canonical service
            // the web redirect + webhooks use. The old inline loop created a
            // fresh Enrollment + wallet credit on EVERY hit (double-enroll /
            // double-credit on a refresh) and bypassed coach-student linking,
            // coupon usage and batch capacity. markPaid() does all of that once.
            \DB::transaction(function () use ($order, $after_success_transaction, $payment_details, $method_bank_or_offline, $verifiedAmount) {
                $locked = \Modules\Order\app\Models\Order::lockForUpdate()->findOrFail($order->id);
                if ($locked->payment_status === 'paid') {
                    return; // already fulfilled — no-op (idempotent)
                }

                if (! $method_bank_or_offline) {
                    app(\App\Services\PaymentFulfilmentService::class)
                        ->markPaid($locked, $after_success_transaction, $payment_details, $verifiedAmount);
                    return;
                }

                // Bank/offline → recorded PENDING; wallet + enrollment happen
                // later on admin approval (no wallet credit here).
                $locked->transaction_id  = $after_success_transaction;
                $locked->payment_status  = 'pending';
                $locked->status          = 'completed';
                $locked->payment_details = $payment_details;
                $locked->save();
            });

            // Refresh so the confirmation mail reflects the real saved status.
            $order = \Modules\Order\app\Models\Order::find($order->id) ?? $order;

            try {
                // Gateway callbacks may be unauthenticated — fall back to the
                // order's buyer so the receipt still sends.
                $user = auth()->user() ?? \App\Models\User::find($order->buyer_id);
                if ($user) {
                    $this->sendingPaymentStatusMail([
                        'email'          => $user->email,
                        'name'           => $user->name,
                        'order_id'       => $order->invoice_id,
                        'paid_amount'    => $order->paid_amount . ' ' . $order->payable_currency,
                        'payment_status' => $order->payment_status,
                    ]);
                }
            } catch (Exception $e) {
                info($e->getMessage());
            }

            $this->paymentService->removeSessions();

            $image = 'success.png';
            $title = 'Your order has been placed';
            $sub_title = __('For check more details you can go to your dashboard');
            return view('basicpayment::app_order_notification', compact('image', 'title', 'sub_title'));
        } catch (Exception $e) {
            info($e->getMessage());
            $image = 'fail.png';
            $title = 'Your order has been fail';
            $sub_title = __('Please try again for more details connect with us');
            return view('basicpayment::app_order_notification', compact('image', 'title', 'sub_title'));
        }
    }
    public function payment_failed() {
        $order = session()->get('order');
        if ($order) {
            $order->payment_status = 'cancelled';
            $order->save();
        }

        try {
            $user = auth()->user();
            $this->sendingPaymentStatusMail([
                'email'          => $user->email,
                'name'           => $user->name,
                'order_id'       => $order->invoice_id,
                'paid_amount'    => $order->paid_amount . ' ' . $order->payable_currency,
                'payment_status' => $order->payment_status,
            ]);
        } catch (Exception $e) {
            info($e->getMessage());
        }

        $this->paymentService->removeSessions();
        $image = 'fail.png';
        $title = 'Your order has been fail';
        $sub_title = __('Please try again for more details connect with us');
        return view('basicpayment::app_order_notification', compact('image', 'title', 'sub_title'));
    }
    /**
     * Re-resolve the gateway the session order was stamped with, fetching the
     * order FRESH from the DB (not the possibly-stale serialized session copy)
     * so the stored provenance is read. Falls back to the platform default.
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

    public function stripe_pay() {
        // Coach-specific gateway (2026-06-29) — use the coach's Stripe secret when
        // the order was stamped to a coach config, else the platform default.
        $resolvedGw = $this->resolveGatewayForSessionOrder('stripe');
        \Stripe\Stripe::setApiKey($resolvedGw->credential('stripe_secret'));

        $after_failed_url = route('payment-api.webview-failed-payment');
        session()->put('after_failed_url', $after_failed_url);

        $payable_currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        $allCurrencyCodes = $this->paymentService->getSupportedCurrencies($this->paymentService::STRIPE);

        if (in_array(Str::upper($payable_currency), $allCurrencyCodes['non_zero_currency_codes'])) {
            $payable_with_charge = $paid_amount;
        } elseif (in_array(Str::upper($payable_currency), $allCurrencyCodes['three_digit_currency_codes'])) {
            $convertedCharge = (string) $paid_amount . '0';
            $payable_with_charge = (int) $convertedCharge;
        } else {
            $payable_with_charge = (int) ($paid_amount * 100);
        }

        $sessionOrder = session()->get('order');
        $orderId = $sessionOrder?->id ? (string) $sessionOrder->id : null;

        $checkoutSession = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => [[
                'price_data' => [
                    'currency'     => $payable_currency,
                    'unit_amount'  => $payable_with_charge,
                    'product_data' => [
                        'name' => cache()->get('setting')->app_name,
                    ],
                ],
                'quantity'   => 1,
            ]],
            'mode'                 => 'payment',
            'success_url'          => url('/webview-success-payment') . '?session_id={CHECKOUT_SESSION_ID}&bearer_token=' . request()->bearer_token,
            'cancel_url'           => $after_failed_url,
            'metadata'             => $orderId ? ['order_id' => $orderId] : [],
        ]);
        // Redirect to the checkout session URL
        return redirect()->away($checkoutSession->url);
    }
    public function stripe_success(Request $request) {
        $after_success_url = route('payment-api.webview-success-payment', ['bearer_token' => request()->bearer_token]);

        // Resolve the SAME gateway the order was created with (coach or default).
        $resolvedGw = $this->resolveGatewayForSessionOrder('stripe');

        // Assuming the Checkout Session ID is passed as a query parameter
        $session_id = $request->query('session_id');
        if ($session_id) {
            \Stripe\Stripe::setApiKey($resolvedGw->credential('stripe_secret'));

            $session = \Stripe\Checkout\Session::retrieve($session_id);

            $paymentDetails = [
                'transaction_id' => $session->payment_intent,
                'amount'         => $session->amount_total,
                'currency'       => $session->currency,
                'payment_status' => $session->payment_status,
                'created'        => $session->created,
            ];
            session()->put('after_success_url', $after_success_url);
            session()->put('after_success_transaction', $session->payment_intent);
            session()->put('payment_details', $paymentDetails);

            return redirect($after_success_url);
        }

        $after_failed_url = session()->get('after_failed_url');
        return redirect($after_failed_url);
    }

    public function pay_via_mollie() {
        $payment_setting = $this->get_payment_gateway_info();

        $mollie_credentials = (object) [
            'mollie_key' => $payment_setting->mollie_key,
        ];

        $after_success_url = route('payment-api.webview-success-payment', ['bearer_token' => request()->bearer_token]);
        $after_failed_url = route('payment-api.webview-failed-payment');

        session()->put('after_success_url', $after_success_url);
        session()->put('after_failed_url', $after_failed_url);

        $payable_currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        try {
            Mollie::api()->setApiKey($mollie_credentials->mollie_key);
            $payment = Mollie::api()->payments()->create([
                'amount'      => [
                    'currency' => '' . strtoupper($payable_currency) . '',
                    'value'    => '' . $paid_amount . '',
                ],
                'description' => cache()->get('setting')->app_name,
                'redirectUrl' => route('payment-api.mollie-success', ['bearer_token' => request()->bearer_token]),
            ]);

            $payment = Mollie::api()->payments()->get($payment->id);

            session()->put('payment_id', $payment->id);
            session()->put('mollie_credentials', $mollie_credentials);

            return redirect($payment->getCheckoutUrl(), 303);

        } catch (Exception $ex) {
            info($ex->getMessage());
            $image = 'fail.png';
            $title = 'Your order has been fail';
            $sub_title = __('Please try again for more details connect with us');
            return view('basicpayment::app_order_notification', compact('image', 'title', 'sub_title'));
        }

    }
    public function mollie_success() {
        $mollie_credentials = Session::get('mollie_credentials');

        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey($mollie_credentials->mollie_key);
        $payment = $mollie->payments->get(session()->get('payment_id'));

        if ($payment->isPaid()) {
            $paymentDetails = [
                'transaction_id' => $payment->id,
                'amount'         => $payment->amount->value,
                'currency'       => $payment->amount->currency,
                'fee'            => $payment->settlementAmount->value . ' ' . $payment->settlementAmount->currency,
                'description'    => $payment->description,
                'payment_method' => $payment->method,
                'status'         => $payment->status,
                'paid_at'        => $payment->paidAt,
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
    public function pay_via_razorpay(Request $request) {
        // Coach-specific gateway (2026-06-29) — resolve the Razorpay credentials
        // the order was stamped with (coach config or platform default), instead
        // of always reading the global settings.
        $resolvedGw = $this->resolveGatewayForSessionOrder('razorpay');

        $after_success_url = route('payment-api.webview-success-payment', ['bearer_token' => request()->bearer_token]);
        $after_failed_url = route('payment-api.webview-failed-payment');

        $razorpay_credentials = (object) [
            'razorpay_key'    => $resolvedGw->credential('razorpay_key'),
            'razorpay_secret' => $resolvedGw->credential('razorpay_secret'),
        ];

        return $this->pay_with_razorpay($request, $razorpay_credentials, $after_success_url, $after_failed_url);

    }
    public function pay_with_razorpay(Request $request, $razorpay_credentials, $after_success_url, $after_failed_url) {
        $input = $request->all();
        $api = new Api($razorpay_credentials->razorpay_key, $razorpay_credentials->razorpay_secret);
        $payment = $api->payment->fetch($input['razorpay_payment_id']);
        if (count($input) && !empty($input['razorpay_payment_id'])) {
            try {
                $response = $api->payment->fetch($input['razorpay_payment_id'])->capture(['amount' => $payment['amount']]);

                $paymentDetails = [
                    'transaction_id' => $response->id,
                    'amount'         => $response->amount,
                    'currency'       => $response->currency,
                    'fee'            => $response->fee,
                    'description'    => $response->description,
                    'payment_method' => $response->method,
                    'status'         => $response->status,
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
    public function flutterwave_payment(Request $request) {
        $payment_setting = $this->get_payment_gateway_info();
        $curl = curl_init();
        $tnx_id = $request->tnx_id;
        $url = "https://api.flutterwave.com/v3/transactions/$tnx_id/verify";
        $token = $payment_setting?->flutterwave_secret_key;
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer $token",
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        $response = json_decode($response);
        if ($response->status == 'success') {
            $paymentDetails = [
                'status'            => $response->status,
                'trx_id'            => $tnx_id,
                'amount'            => $response?->data?->amount,
                'amount_settled'    => $response?->data?->amount_settled,
                'currency'          => $response?->data?->currency,
                'charged_amount'    => $response?->data?->charged_amount,
                'app_fee'           => $response?->data?->app_fee,
                'merchant_fee'      => $response?->data?->merchant_fee,
                'card_last_4digits' => $response?->data?->card?->last_4digits,
            ];

            Session::put('payment_details', $paymentDetails);
            Session::put('after_success_transaction', $tnx_id);

            $image = 'success.png';
            $title = 'Your order has been placed';
            $sub_title = __('For check more details you can go to your dashboard');
            return view('basicpayment::app_order_notification', compact('image', 'title', 'sub_title'));

        } else {

            $image = 'fail.png';
            $title = 'Your order has been fail';
            $sub_title = __('Please try again for more details connect with us');
            return view('basicpayment::app_order_notification', compact('image', 'title', 'sub_title'));
        }

    }
    public function paystack_payment(Request $request) {
        $payment_setting = $this->get_payment_gateway_info();

        $reference = $request->reference;
        $transaction = $request->tnx_id;
        $secret_key = $payment_setting?->paystack_secret_key;
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.paystack.co/transaction/verify/$reference",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
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
                'status'             => $final_data?->data?->status,
                'transaction_id'     => $transaction,
                'requested_amount'   => $final_data?->data->requested_amount,
                'amount'             => $final_data?->data?->amount,
                'currency'           => $final_data?->data?->currency,
                'gateway_response'   => $final_data?->data?->gateway_response,
                'paid_at'            => $final_data?->data?->paid_at,
                'card_last_4_digits' => $final_data?->data->authorization?->last4,
            ];

            Session::put('payment_details', $paymentDetails);
            Session::put('after_success_transaction', $transaction);

            return response()->json(['message' => 'Payment Success.']);
        } else {
            info('here');
            $notification = 'Payment faild, please try again';
            return response()->json(['message' => $notification], 403);
        }
    }
    public function pay_via_instamojo() {
        $after_success_url = route('payment-api.webview-success-payment', ['bearer_token' => request()->bearer_token]);
        $after_failed_url = route('payment-api.webview-failed-payment');

        session()->put('after_success_url', $after_success_url);
        session()->put('after_failed_url', $after_failed_url);

        $payment_setting = $this->get_payment_gateway_info();

        $instamojo_credentials = (object) [
            'instamojo_api_key'    => $payment_setting->instamojo_api_key,
            'instamojo_auth_token' => $payment_setting->instamojo_auth_token,
            'account_mode'         => $payment_setting->instamojo_account_mode,
        ];

        return $this->pay_with_instamojo($instamojo_credentials);
    }
    public function pay_with_instamojo($instamojo_credentials) {
        $payable_currency = session()->get('payable_currency');
        $paid_amount = session()->get('paid_amount');

        $environment = $instamojo_credentials->account_mode;
        $api_key = $instamojo_credentials->instamojo_api_key;
        $auth_token = $instamojo_credentials->instamojo_auth_token;

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

            curl_setopt($ch, CURLOPT_URL, $url . 'payment-requests/');
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER,
                ["X-Api-Key:$api_key",
                    "X-Auth-Token:$auth_token"]);
            $payload = [
                'purpose'                 => env('APP_NAME'),
                'amount'                  => $paid_amount,
                'phone'                   => '918160651749',
                'buyer_name'              => auth()->user()?->name,
                'redirect_url'            => route('payment-api.instamojo-success', ['bearer_token' => request()->bearer_token]),
                'send_email'              => true,
                'webhook'                 => 'http://www.example.com/webhook/',
                'send_sms'                => true,
                'email'                   => auth()->user()->email,
                'allow_repeated_payments' => false,
            ];
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            $response = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($response);
            session()->put('instamojo_credentials', $instamojo_credentials);

            if (!empty($response?->payment_request?->longurl)) {
                return redirect($response?->payment_request?->longurl);
            } else {
                $image = 'fail.png';
                $title = 'Your order has been fail';
                $sub_title = __('Please try again for more details connect with us');
                return view('basicpayment::app_order_notification', compact('image', 'title', 'sub_title'));
            }

        } catch (Exception $ex) {
            $after_failed_url = Session::get('after_failed_url');
            return redirect($after_failed_url);
        }

    }
    public function instamojo_success(Request $request) {

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
        curl_setopt($ch, CURLOPT_URL, $url . 'payments/' . $request->get('payment_id'));
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
    public function handleMailSending(array $mailData) {
        try {
            self::setMailConfig();

            // Get email template
            $template = EmailTemplate::where('name', 'order_completed')->firstOrFail();
            $mailData['subject'] = $template->subject;

            // White-label: brand the email as the order's coach (resolved from
            // the invoice id). Null/absent -> platform (backward compatible).
            if (!isset($mailData['coach_id']) && !empty($mailData['order_id'])) {
                $mailData['coach_id'] = \Modules\Order\app\Models\Order::where('invoice_id', $mailData['order_id'])->value('primary_coach_id');
            }

            // Prepare email content
            $message = str_replace('{{name}}', $mailData['name'], $template->message);
            $message = str_replace('{{order_id}}', $mailData['order_id'], $message);
            $message = str_replace('{{paid_amount}}', $mailData['paid_amount'], $message);
            $message = str_replace('{{payment_method}}', $mailData['payment_method'], $message);

            if (self::isQueable()) {
                DefaultMailJob::dispatch($mailData['email'], $mailData, $message);
            } else {
                Mail::to($mailData['email'])->send(new DefaultMail($mailData, $message));
            }
        } catch (Exception $e) {
            info($e->getMessage());
        }
    }
    public function sendingPaymentStatusMail(array $mailData) {
        try {
            self::setMailConfig();

            // Get email template
            $template = EmailTemplate::where('name', 'payment_status')->firstOrFail();
            $mailData['subject'] = $template->subject;

            // White-label: brand the email as the order's coach (resolved from
            // the invoice id). Null/absent -> platform (backward compatible).
            if (!isset($mailData['coach_id']) && !empty($mailData['order_id'])) {
                $mailData['coach_id'] = \Modules\Order\app\Models\Order::where('invoice_id', $mailData['order_id'])->value('primary_coach_id');
            }

            // Prepare email content
            $message = str_replace('{{name}}', $mailData['name'], $template->message);
            $message = str_replace('{{order_id}}', $mailData['order_id'], $message);
            $message = str_replace('{{paid_amount}}', $mailData['paid_amount'], $message);
            $message = str_replace('{{payment_status}}', $mailData['payment_status'], $message);

            if (self::isQueable()) {
                DefaultMailJob::dispatch($mailData['email'], $mailData, $message);
            } else {
                Mail::to($mailData['email'])->send(new DefaultMail($mailData, $message));
            }
        } catch (Exception $e) {
            info($e->getMessage());
        }
    }

}
