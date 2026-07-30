<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Support\SecretSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\app\Models\Enrollment;

/**
 * Student-side fee payment — Phase 4C (Razorpay collection).
 *
 * Pairs with the coach-side FeeManagementController. Surfaces every
 * unpaid demand the student owes (because they're enrolled in the
 * demand's batch) and walks them through Razorpay checkout.
 *
 * Flow:
 *   1. GET  /student/fees                 — list dues (paid + unpaid)
 *   2. POST /student/fees/{demand}/checkout
 *        Creates a Razorpay Order via the official SDK,
 *        creates a fee_payment row (status='initiated'),
 *        returns { razorpay_order_id, razorpay_key, amount, currency }
 *        for the FE Razorpay JS checkout.
 *   3. POST /student/fees/checkout/verify  — JS-side callback
 *        Verifies HMAC(order_id|payment_id) against secret,
 *        flips fee_payment to 'paid' + stamps paid_at.
 *
 * The /webhooks/razorpay endpoint is a server-side safety net that
 * confirms the same fee_payment row in case the FE callback never
 * fires (browser closed, network hiccup). See RazorpayWebhookController.
 */
class StudentFeePaymentController extends Controller
{
    /**
     * GET /student/fees — list all fee demands the student owes.
     */
    public function index()
    {
        $user = userAuth();
        abort_unless($user, 401);

        // Find every batch the student is enrolled in.
        // 2026-06-02 (fee-visibility fix) — do NOT require has_access=1 here.
        // A fee demand exists precisely to COLLECT payment from batch members
        // who haven't paid yet, and such members commonly have has_access=0
        // (assigned to the batch, access granted only after the fee is paid).
        // Requiring has_access=1 hid every demand from exactly the students who
        // owe it. Batch membership for fees = an enrollment row with that
        // batch_id; has_access gates course CONTENT, not fee visibility.
        // 2026-06-02 — a student owes a fee demand when it targets either
        //   (a) a BATCH they're enrolled in, or
        //   (b) the whole COURSE they're enrolled in (course-wide demand:
        //       course_id set, batch_id NULL — reaches batch-less members too).
        // 2026-06-09 — TENANT SCOPE: on a coach domain, only this coach's
        // courses/batches drive the dues list (so a student never sees another
        // coach's fee demands here). Null/0 on the platform → all.
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        $enrollQuery = Enrollment::where('user_id', $user->id);
        if ($tenantCoachId > 0) {
            $enrollQuery->whereIn('course_id',
                \App\Models\Course::where('instructor_id', $tenantCoachId)->select('id'));
        }
        $enrollments = $enrollQuery->get(['course_id', 'batch_id']);
        $batchIds  = $enrollments->pluck('batch_id')->filter()->unique()->values()->all();
        $courseIds = $enrollments->pluck('course_id')->filter()->unique()->values()->all();

        if (empty($batchIds) && empty($courseIds)) {
            // Empty paginator so the view doesn't have to branch on
            // null — getCollection()/links() still work the same way.
            $demands = new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]), 0, 20,
                \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage(),
                ['path' => request()->url(), 'pageName' => 'page']
            );
        } else {
            $demands = FeeDemand::query()
                ->where('status', 'published')
                ->where(function ($q) use ($batchIds, $courseIds) {
                    if (!empty($batchIds)) {
                        $q->whereIn('batch_id', $batchIds);
                    }
                    if (!empty($courseIds)) {
                        $q->orWhere(function ($cw) use ($courseIds) {
                            $cw->whereNull('batch_id')->whereIn('course_id', $courseIds);
                        });
                    }
                })
                ->with(['batch:id,course_id,title', 'batch.course:id,title', 'course:id,title'])
                ->withSum([
                    'payments as paid_by_me' => function ($q) use ($user) {
                        $q->where('student_id', $user->id)->where('status', 'paid');
                    },
                ], 'amount')
                ->orderByDesc('id')
                ->paginate(20);
        }

        // Pre-derive a UI status per row.
        $demands->getCollection()->transform(function ($d) {
            $paid = (float) ($d->paid_by_me ?? 0);
            $owed = max(0.0, (float) $d->amount - $paid);

            if ($paid >= (float) $d->amount && $owed == 0) {
                $d->ui_status = 'paid';
            } elseif ($d->due_date && $d->due_date->isPast()) {
                $d->ui_status = 'overdue';
            } else {
                $d->ui_status = 'due';
            }
            $d->ui_owed = $owed;
            return $d;
        });

        return view('frontend.student-dashboard.fees.index', compact('demands'));
    }

    /**
     * POST /student/fees/{demand}/checkout
     *
     * Creates a Razorpay Order and a corresponding fee_payment row
     * with status='initiated'. Returns the data the FE Razorpay JS
     * widget needs to launch checkout.
     */
    public function checkout(Request $request, int $demandId)
    {
        $user = userAuth();
        abort_unless($user, 401);

        // 2026-06-25 (tenant-isolation audit) — on a coach domain, a fee demand
        // can only be checked out on its OWN coach's surface. Pre-fix the demand
        // was loaded globally, so a student belonging to multiple coaches could
        // initiate Coach A's demand while on Coach B's domain. The enrollment
        // check below alone did not bind the demand to the resolved tenant.
        $tenantCoachId = (int) $request->attributes->get('resolved_coach_id');
        $demand = FeeDemand::with('batch')->where('status', 'published')
            ->when($tenantCoachId > 0, fn ($q) => $q->where('coach_id', $tenantCoachId))
            ->findOrFail($demandId);

        // Verify the student is actually a member of the demand's scope.
        // 2026-06-02 — membership is the enrollment row, NOT has_access=1
        // (unpaid members owe the fee and must be able to pay it). A course-wide
        // demand (batch_id NULL, course_id set) is satisfied by a course
        // enrollment; a batch demand by a batch enrollment.
        if ($demand->isCourseWide()) {
            $enrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $demand->course_id)
                ->exists();
            $error = __('You are not enrolled in this course.');
        } else {
            $enrolled = Enrollment::where('user_id', $user->id)
                ->where('batch_id', $demand->batch_id)
                ->exists();
            $error = __('You are not enrolled in this batch.');
        }
        if (!$enrolled) {
            return response()->json(['error' => $error], 403);
        }

        // Resolve Razorpay credentials (decrypted via SecretSettings).
        [$keyId, $keySecret] = $this->razorpayCredentials();
        if (!$keyId || !$keySecret) {
            return response()->json([
                'error' => __('Razorpay is not configured for this site. Please contact your coach.'),
            ], 503);
        }

        // Amount in paise (Razorpay's smallest unit). All demands are
        // INR for now — multi-currency lives in the gateway upgrade.
        $amountPaise = (int) round(((float) $demand->amount) * 100);
        if ($amountPaise <= 0) {
            return response()->json([
                'error' => __('Invalid amount.'),
            ], 422);
        }

        try {
            $api = new \Razorpay\Api\Api($keyId, $keySecret);
            $rzpOrder = $api->order->create([
                'receipt'  => 'fee_' . $demand->id . '_' . $user->id . '_' . now()->format('YmdHis'),
                'amount'   => $amountPaise,
                'currency' => 'INR',
                'notes'    => [
                    // These notes echo back on the webhook so the
                    // RazorpayWebhookController knows which row to mark paid.
                    'fee_demand_id' => (string) $demand->id,
                    'student_id'    => (string) $user->id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Razorpay order create failed for fee_demand', [
                'demand_id' => $demand->id, 'student_id' => $user->id,
                'err'       => $e->getMessage(),
            ]);
            return response()->json([
                'error' => __('Could not start payment. Please try again in a minute.'),
            ], 502);
        }

        // Persist an 'initiated' fee_payment row. The verify() / webhook
        // path will flip it to 'paid' on success.
        $payment = FeePayment::create([
            'fee_demand_id'  => $demand->id,
            'student_id'     => $user->id,
            'receipt_no'     => FeePayment::generateReceiptNo(),
            'amount'         => $demand->amount,
            'gateway'        => 'razorpay',
            'gateway_txn_id' => $rzpOrder['id'] ?? null,
            'status'         => 'initiated',
            'recorded_by'    => $user->id,
        ]);

        return response()->json([
            'razorpay_order_id' => $rzpOrder['id'],
            'razorpay_key'      => $keyId,
            'amount'            => $amountPaise,
            'currency'          => 'INR',
            'name'              => cache()->get('setting')?->app_name ?? config('app.name'),
            'description'       => $demand->title,
            'prefill'           => [
                'name'    => $user->name,
                'email'   => $user->email,
            ],
            'fee_payment_id'    => $payment->id,
        ]);
    }

    /**
     * POST /student/fees/checkout/verify
     *
     * FE Razorpay JS calls this after the checkout widget reports
     * success. We verify the HMAC signature, then flip our payment
     * row from 'initiated' to 'paid'. Idempotent: re-running on an
     * already-paid row is a no-op.
     */
    public function verify(Request $request)
    {
        $user = userAuth();
        abort_unless($user, 401);

        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        [$keyId, $keySecret] = $this->razorpayCredentials();
        if (!$keySecret) {
            return response()->json(['error' => 'Razorpay secret not configured'], 500);
        }

        $expected = hash_hmac('sha256',
            $request->razorpay_order_id . '|' . $request->razorpay_payment_id,
            $keySecret
        );
        if (!hash_equals($expected, $request->razorpay_signature)) {
            Log::warning('Razorpay verify signature mismatch', [
                'student_id' => $user->id,
                'order_id'   => $request->razorpay_order_id,
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Locate our pending row by Razorpay order id (saved at create()).
        $payment = FeePayment::where('gateway', 'razorpay')
            ->where('gateway_txn_id', $request->razorpay_order_id)
            ->where('student_id', $user->id)
            ->first();
        if (!$payment) {
            return response()->json(['error' => 'Payment row not found'], 404);
        }

        // Already paid (e.g. webhook fired first) — no-op.
        if ($payment->status === 'paid') {
            return response()->json([
                'status'     => 'ok',
                'receipt_no' => $payment->receipt_no,
            ]);
        }

        $prevStatus = $payment->status;
        $payment->update([
            'status'         => 'paid',
            'paid_at'        => now(),
            // Swap gateway_txn_id from order_id → payment_id for ops
            // (Razorpay dashboard searches by pay_xxx).
            'gateway_txn_id' => $request->razorpay_payment_id,
        ]);

        // Audit the fee collection (money trail) — mirrors the course-order
        // path in PaymentFulfilmentService::markPaid. The already-paid guard
        // above makes this fire exactly once per payment (idempotent audit:
        // if the webhook got there first, we returned early before this).
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::PAYMENT_STATUS_CHANGED,
            'fee',
            $payment,
            ['status' => $prevStatus],
            [
                'status'         => 'paid',
                'gateway'        => 'razorpay',
                'gateway_txn_id' => $request->razorpay_payment_id,
                'amount'         => $payment->amount,
            ],
            'Fee payment ' . $payment->receipt_no . ' (demand #' . $payment->fee_demand_id . ') marked paid'
        );

        // Receipt email — best-effort, never fail the API on mail.
        try {
            $payment->load('demand', 'student');
            if ($payment->student) {
                $payment->student->notify(new \App\Notifications\FeePaymentReceiptToStudent($payment));
            }
        } catch (\Throwable $e) {
            Log::warning('Fee receipt notify failed', [
                'fee_payment_id' => $payment->id, 'err' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status'     => 'ok',
            'receipt_no' => $payment->receipt_no,
        ]);
    }

    /**
     * Resolve Razorpay credentials from the payment_gateways table,
     * honouring SecretSettings encryption. Returns [$keyId, $secret]
     * or [null, null] if either piece is missing.
     */
    private function razorpayCredentials(): array
    {
        try {
            $kv = DB::table('payment_gateways')
                ->whereIn('key', ['razorpay_key', 'razorpay_secret'])
                ->pluck('value', 'key')
                ->all();
            $decrypted = SecretSettings::decryptForTable('payment_gateways', $kv);
            $key    = $decrypted['razorpay_key']    ?? null;
            $secret = $decrypted['razorpay_secret'] ?? null;

            if (!$key || !$secret || $key === 'razorpay_key' || $secret === 'razorpay_secret') {
                return [null, null];
            }
            return [$key, $secret];
        } catch (\Throwable $e) {
            Log::warning('Razorpay credential lookup failed: ' . $e->getMessage());
            return [null, null];
        }
    }
}
