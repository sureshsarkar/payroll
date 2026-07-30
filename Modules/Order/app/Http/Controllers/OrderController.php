<?php

namespace Modules\Order\app\Http\Controllers;

use App\Models\User;
use Exception;
use App\Models\Course;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\OrderItem;
use Modules\Order\app\Traits\GiftOrderTraits;
use Modules\BasicPayment\app\Services\PaymentMethodService;

class OrderController extends Controller {
    use GiftOrderTraits;
    public function index(Request $request) {
        checkAdminHasPermissionAndThrowException('order.management');

        $query = Order::query();
        $query->when($request->keyword, fn($q) => $q->where('invoice_id', 'like', "%{$request->keyword}%"));
        $query->when($request->order_status, fn($q) => $q->where('status', $request->order_status));
        $query->when($request->payment_status, fn($q) => $q->where('payment_status', $request->payment_status));
        $orderBy = $request->order_by == 1 ? 'asc' : 'desc';
        $orders = $request->get('par-page') == 'all' ?
        $query->orderBy('id', $orderBy)->get() :
        $query->orderBy('id', $orderBy)->paginate($request->get('par-page') ?? null)->withQueryString();

        $title = __('Order History');

        return view('order::index', ['orders' => $orders, 'title' => $title]);
    }

    public function pending_order() {

        checkAdminHasPermissionAndThrowException('order.management');

        $orders = Order::with('user')->where('payment_status', 'pending')->latest()->paginate();
        $title = __('Pending Order');

        return view('order::pending-orders', ['orders' => $orders, 'title' => $title]);
    }


    
    public function create() { 
        checkAdminHasPermissionAndThrowException('order.management');
 
        $stidents = User::where('role','student')->where('verification_token', "!=",null)->get();
        $courses = Course::where('status','active')->where('is_approved', "approved")->get();

        return view('order::create',['stidents'=>$stidents,'courses'=>$courses]);
    }



    public function show(string $id) {
        checkAdminHasPermissionAndThrowException('order.management');

        $order = Order::where('id', $id)->firstOrFail();

        return view('order::show', ['order' => $order]);
    }

// ------------------------------------------------------------------------------- 

    public function store(Request $request) {
        checkAdminHasPermissionAndThrowException('order.management');

        $validated = $request->validate([
            'user_id'   => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $setting = Cache::get('setting');
        $commissionRate = $setting?->commission_rate ?? 0;
        // 2026-06-12 — effective sale price ($course->discount ?: price), not MRP,
        // so a discounted course bills + invoices the price the student actually sees.
        $payableAmount = $course->effective_price;
        // 2026-06-13 — apply the coach's tax (opt-in; zero when off).
        $tax = app(\App\Services\Tax\TaxService::class)
            ->computeForOrder((int) $course->instructor_id, (float) $payableAmount, $course->tax_rate_id);

        try {
            DB::beginTransaction();

            $order = Order::create([
                'invoice_id'              => Str::random(10),
                'buyer_id'                => $validated['user_id'],
                'has_coupon'              => 0,
                'coupon_code'             => "",
                'coupon_discount_percent' => "",
                'coupon_discount_amount'  => 0,
                'payment_method'          => 'offline',
                'payment_status'          => 'paid',
                'status'                  => 'completed',
                // payable_amount = pre-tax revenue base (commission base); the
                // student pays charge_total (taxable + tax).
                'payable_amount'          => $tax['taxable_amount'],
                'tax_amount'              => $tax['tax_amount'],
                'taxable_amount'          => $tax['taxable_amount'],
                'tax_rate_applied'        => $tax['rate'],
                'tax_mode'                => $tax['has_tax'] ? $tax['mode'] : null,
                'tax_label'               => $tax['has_tax'] ? $tax['label'] : null,
                'tax_registration'        => $tax['has_tax'] ? $tax['registration'] : null,
                'tax_components'          => $tax['has_tax'] ? $tax['components'] : null,
                'gateway_charge'          => 0,
                'payable_with_charge'     => $tax['charge_total'],
                'paid_amount'             => $tax['charge_total'],
                'payable_currency'        => "INR",
                'conversion_rate'         => Session::get('currency_rate', 1),
                'commission_rate'         => $commissionRate,
                'order_type'              => 'course',
                'order_details'           => null,
            ]);

            $orderItem = OrderItem::create([
                'order_id'         => $order->id,
                'price'            => $tax['taxable_amount'], // pre-tax line (commission base)
                'tax_amount'       => $tax['tax_amount'],
                'tax_rate_applied' => $tax['has_tax'] ? $tax['rate'] : null,
                'course_id'        => $validated['course_id'],
                'commission_rate'  => $commissionRate,
            ]);

            // insert instructor commission to his wallet (2026-06-01 audit H9
            // — via the shared per-item OrderItem::coachPayout() helper so
            // the credit here is symmetric with the destroy/refund debits).
            if ($course->instructor) {
                $course->instructor->increment('wallet_balance', $orderItem->coachPayout((float) $commissionRate));
            }

            DB::commit();

            return redirect()->route('admin.orders')->with("success", "Added Successfully");
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Admin order creation failed', [
                'error'     => $e->getMessage(),
                'course_id' => $validated['course_id'] ?? null,
                'user_id'   => $validated['user_id'] ?? null,
            ]);
            return redirect()->back()->with(['messege' => __('Order creation failed'), 'alert-type' => 'error']);
        }
    }
 
// ------------------------------------------------------------------------------- 









    function updateOrder(Request $request, $id) {
        checkAdminHasPermissionAndThrowException('order.management');

        $request->validate([
            'order_status'   => 'required|in:pending,processing,completed,declined',
            'payment_status' => 'required|in:pending,paid,unpaid,cancelled,refunded',
        ]);

        [$order, $previousStatus, $previousPaymentStatus] = \DB::transaction(function () use ($request, $id) {
            $order = Order::lockForUpdate()->findOrFail($id);
            $previousStatus = $order->status;
            $previousPaymentStatus = $order->payment_status;

            $order->status = $request->order_status;
            $order->payment_status = $request->payment_status;
            $order->save();

            $flippedToPaid = $previousPaymentStatus !== 'paid' && $request->payment_status === 'paid';
            $flippedFromPaid = $previousPaymentStatus === 'paid' && $request->payment_status !== 'paid';

            if ($flippedToPaid) {
                // 2026-06-03 (Referral A+) — mint the referral commission via the
                // shared service so the admin path behaves IDENTICALLY to the
                // online-gateway / coach paths (create 'eligible', idempotent).
                app(\App\Services\ReferralCommissionService::class)->onOrderPaid($order);

                // F15 (audit 2026-06-26) — wallet credit + coupon usage fire ONCE
                // per order. The marker is cleared on reversal, so a genuine
                // cancel-then-repay still nets correctly, but a repeated
                // paid→paid toggle no longer re-credits the coach wallet or
                // double-counts the coupon.
                $alreadySettled = $order->wallet_settled_at !== null;

                if ($order->isGiftOrder()) {
                    $this->giftOrderDetailsUpdate($order);
                } else {
                    foreach ($order->orderItems as $item) {
                        Enrollment::firstOrCreate(
                            ['user_id' => $order->buyer_id, 'course_id' => $item->course_id],
                            ['order_id' => $order->id, 'has_access' => 1]
                        );

                        // 2026-06-01 (audit H9) — per-item payout via shared helper.
                        if (! $alreadySettled) {
                            $course = Course::withTrashed()->find($item->course_id);
                            if ($course && $course->instructor) {
                                $course->instructor->increment('wallet_balance', $item->coachPayout((float) $order->commission_rate));
                            }
                        }
                    }
                }

                if (! $alreadySettled && !empty($order->coupon_code)) {
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

                // Stamp the idempotency marker once the credit has been applied.
                if (! $alreadySettled) {
                    $order->wallet_settled_at = now();
                    $order->save();
                }
            } elseif ($flippedFromPaid) {
                // 2026-06-03 (Referral A+) — refund/cancel must reverse any
                // referral commission: claw back if credited, else reject.
                app(\App\Services\ReferralCommissionService::class)
                    ->onOrderReversed($order, 'Admin set payment to ' . $order->payment_status);

                foreach ($order->orderItems as $item) {
                    // F14 (audit 2026-06-26) — scope by order_id so a refund only
                    // removes the enrollment THIS order granted. The old (user,course)
                    // first() could delete the WRONG batch enrollment (or one from a
                    // different, still-paid order) on multi-batch courses.
                    $enrollment = Enrollment::where('user_id', $order->buyer_id)
                        ->where('course_id', $item->course_id)
                        ->where('order_id', $order->id)
                        ->first();
                    if ($enrollment) {
                        $enrollment->delete();
                    }
                    // 2026-06-01 (audit H9) — per-item refund debit via shared
                    // helper; reads the SAME captured rate the charge used.
                    // F15 — only claw back if the wallet was actually credited for
                    // this order (marker set); prevents an over-debit if reversal
                    // ever runs twice.
                    if ($order->wallet_settled_at !== null) {
                        $course = Course::withTrashed()->find($item->course_id);
                        if ($course && $course->instructor) {
                            $course->instructor->decrement('wallet_balance', $item->coachPayout((float) $order->commission_rate));
                        }
                    }
                }
                // Clear the marker so a later genuine re-payment credits again.
                if ($order->wallet_settled_at !== null) {
                    $order->wallet_settled_at = null;
                    $order->save();
                }
            }

            return [$order, $previousStatus, $previousPaymentStatus];
        });

        // Enterprise H-A — audit the admin order status / payment change.
        if ($previousStatus !== $order->status || $previousPaymentStatus !== $order->payment_status) {
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::STATUS_CHANGED,
                'order',
                $order,
                ['status' => $previousStatus, 'payment_status' => $previousPaymentStatus],
                ['status' => $order->status, 'payment_status' => $order->payment_status],
                'Admin updated order #' . ($order->invoice_id ?? $order->id) . ' status'
            );
        }

        $flippedToPaid = $previousPaymentStatus !== 'paid' && $order->payment_status === 'paid';

        if ($flippedToPaid) {
            try {
                foreach ($order->orderItems as $item) {
                    $course = Course::withTrashed()->find($item->course_id);
                    if ($course && $course->instructor) {
                        $item->load('order.user:id,name');
                        $course->instructor->notify(new \App\Notifications\CourseSoldToCoach($item, $course));
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Notify coach of sale failed: ' . $e->getMessage());
            }
        }

        // Notify the buyer (student) of the status change.
        try {
            $buyer = \App\Models\User::find($order->buyer_id);
            if ($buyer) {
                $buyer->notify(new \App\Notifications\OrderStatusChangedToStudent(
                    $order,
                    $previousStatus,
                    $previousPaymentStatus
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('Notify student of order status change failed: ' . $e->getMessage());
        }

        // Notify all admins when an order flips to paid (fresh paid event).
        if ($previousPaymentStatus !== 'paid' && $order->payment_status === 'paid') {
            try {
                $order->load('user:id,name,email');
                \Illuminate\Support\Facades\Notification::send(
                    \App\Models\Admin::where('status', 'active')->get(),
                    new \App\Notifications\NewOrderToAdmin($order)
                );
            } catch (\Throwable $e) {
                \Log::warning('Notify admins of new order failed: ' . $e->getMessage());
            }

            // Notify the coach for each course in the paid order.
            try {
                $student = \App\Models\User::find($order->buyer_id);
                $items = \Modules\Order\app\Models\OrderItem::where('order_id', $order->id)->get();
                foreach ($items as $item) {
                    $course = \App\Models\Course::find($item->course_id);
                    if (!$course || !$student) continue;
                    $coachId = $course->instructor_id ?: $course->added_by;
                    $coach = $coachId ? \App\Models\User::find($coachId) : null;
                    if ($coach) {
                        $coach->notify(new \App\Notifications\NewEnrollmentToCoach($course, $student));
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Notify coach of new enrollment failed: ' . $e->getMessage());
            }
        }

        $notification = __('order status updated successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->back()->with($notification);
    }

    function printInvoice(Request $request, $id) {
        // FT-IDOR-30 fix (2026-05-28) — was missing the
        // `order.management` gate that every other method on this
        // controller (index, pending_order, create, show, store,
        // updateOrder, destroy, download_payment_receipt,
        // reSendGiftClaimMail) already enforces. The invoice
        // contains the buyer's name + email + address + course
        // purchased + payment method + amount — full PII. A
        // sub-admin without order.management could enumerate every
        // order_id and print invoices to scrape the customer list.
        checkAdminHasPermissionAndThrowException('order.management');

        $order = Order::where('id', $id)->firstOrFail();
        return view('order::invoice', ['order' => $order]);
    }

    public function destroy($id) {
        checkAdminHasPermissionAndThrowException('order.management');

        // delete order and order items order enrollments and instructor commission
        $order = Order::findOrFail($id);
        if ($order?->payment_status == 'paid') {
            if ($order->isGiftOrder()) {
                $details = $order?->order_details;
                $enrollment = Enrollment::where('user_id', $details?->user_id)->where('course_id', $details?->course_id)->first();
                if ($enrollment) {
                    $enrollment->delete();
                }
            }
            foreach ($order?->orderItems as $item) {
                // delete enrollment — F14: scope by order_id so we never revoke a
                // different order's (paid) enrollment for the same course/batch.
                $enrollment = Enrollment::where('user_id', $order?->buyer_id)
                    ->where('course_id', $item?->course_id)
                    ->where('order_id', $order?->id)
                    ->first();
                if ($enrollment) {
                    $enrollment->delete();
                }

                // decrement instructor commission from his wallet (2026-06-01
                // audit H9 — per-item via shared helper, symmetric with the
                // credit paths and reading the same captured rate).
                $instructor = Course::find($item?->course_id)?->instructor;
                $instructor?->decrement('wallet_balance', $item->coachPayout((float) ($order?->commission_rate ?? 0)));
            }
        }
        $order->orderItems()->delete();
        if ($order?->payment_method == PaymentMethodService::OFFLINE_PAYMENT && ! empty($order?->payment_details)) {
            // V1 fix — delete the receipt across private + legacy locations.
            \App\Support\PrivateMedia::delete($order->payment_details);
        }
        $order->delete();

        $notification = __('Order deleted successfully');
        $notification = ['messege' => $notification, 'alert-type' => 'success'];

        return redirect()->route('admin.orders')->with($notification);
    }
    public function download_payment_receipt($id) {
        checkAdminHasPermissionAndThrowException('order.management');

        $order = Order::findOrFail($id);

        if ($order?->payment_method != PaymentMethodService::OFFLINE_PAYMENT || empty($order?->payment_details)) {
            abort(404);
        }

        // V1 fix — receipts now live on the PRIVATE disk; PrivateMedia::download
        // resolves private first, then legacy public locations. Admin-gated above.
        $extension   = pathinfo($order->payment_details, PATHINFO_EXTENSION) ?: 'bin';
        $newFileName = 'payment_receipt_' . $order->invoice_id . '.' . $extension;
        return \App\Support\PrivateMedia::download($order->payment_details, $newFileName);
    }
    public function giftVerification($invoice_id, $verification_token) {
        $order = Order::whereInvoiceId($invoice_id)->firstOrFail();
        $details = $order?->order_details;

        abort_if(empty($details->verification_token) || $details->verification_token !== $verification_token, 404);
        try {
            DB::beginTransaction();
            Enrollment::firstOrCreate([
                'user_id'   => userAuth()->id,
                'course_id' => $details->course_id,
            ], [
                'order_id'   => $order->id,
                'has_access' => 1,
            ]);

            $order_details = (array) $details;
            unset($order_details['verification_token']);
            $order->order_details = (object) $order_details;
            $order->save();

            DB::commit();

            $notification = __('Congratulations! You have successfully enrolled in the course.') . '🎉';
            $notification = ['messege' => $notification, 'alert-type' => 'success'];
            return redirect()->route('student.enrolled-courses')->with($notification);
        } catch (Exception $e) {
            DB::rollback();
            info($e->getMessage());
            $notification = __('Invalid token, please try again');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];
            return redirect()->route('home')->with($notification);
        }

    }
    public function reSendGiftClaimMail($invoice_id) {
        checkAdminHasPermissionAndThrowException('order.management');
        $order = Order::whereInvoiceId($invoice_id)->firstOrFail();

        abort_if(empty($order?->order_details?->verification_token), 404);
        try {
            $order->order_details = (object) array_merge((array) $order->order_details, [
                "verification_token" => Str::random(100),
            ]);
            $order->save();

            try {
                $course = Course::find($order?->order_details?->course_id);
                $this->sendingGiftCourseMail([
                    'email'        => $order?->order_details?->recipient_email,
                    'name'         => $order?->order_details?->recipient_name,
                    'sender_name'  => $order?->user?->name,
                    'sender_email' => $order?->user?->email,
                    'course_link'  => route('course.show', $course->slug),
                    'course_name'  => $course->title,
                    'message'      => $order?->order_details?->message,
                    'link'         => route('gift-course-verification', ['invoice_id' => $order?->invoice_id, 'verification_token' => $order?->order_details?->verification_token]),
                ]);
            } catch (Exception $e) {
                info($e->getMessage());
            }
            $notification = __('Mail Send Successfully.');
            $notification = ['messege' => $notification, 'alert-type' => 'success'];
            return redirect()->back()->with($notification);
        } catch (Exception $e) {
            info($e->getMessage());
            $notification = __('Something went wrong');
            $notification = ['messege' => $notification, 'alert-type' => 'error'];
            return redirect()->route('home')->with($notification);
        }

    }
}