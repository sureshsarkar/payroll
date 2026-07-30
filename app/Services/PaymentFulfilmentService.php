<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;

/**
 * Shared, idempotent fulfilment for paid orders.
 *
 * Used by both:
 *  - the user-redirect success URL (`PaymentController::payment_success`)
 *  - server-side webhooks (Stripe / Razorpay / bKash / PayPal / MercadoPago)
 *
 * Idempotency is enforced by:
 *  1. `Order::lockForUpdate()` — serialises concurrent fulfilment attempts on the same order row
 *  2. early-return when `payment_status === 'paid'` — replayed events are no-ops
 *  3. `Enrollment::firstOrCreate(user_id, course_id)` — DB has a UNIQUE on this pair anyway
 *  4. `Order.transaction_id` is UNIQUE at the DB layer — duplicate rows can't exist
 */
class PaymentFulfilmentService
{
    /**
     * Refuse to fulfil an order when the gateway-captured amount is clearly less
     * than what the order owed. Underpayment throws (rolls back the txn so the
     * order stays unpaid + uncredited); overpayment is logged but allowed (not a
     * security issue). A null/zero verified amount is a no-op (caller opted out).
     * Tolerance absorbs rounding / minor gateway fees.
     */
    private function assertAmountSufficient(Order $order, ?float $verifiedAmount): void
    {
        if ($verifiedAmount === null || $verifiedAmount <= 0) {
            return;
        }
        $expected = (float) ($order->payable_with_charge ?: $order->paid_amount ?: $order->payable_amount ?: 0);
        if ($expected <= 0) {
            return;
        }
        $tolerance = max(1.0, $expected * 0.02); // 2% or 1 unit, whichever is larger
        if ($verifiedAmount + $tolerance < $expected) {
            Log::critical('Payment underpayment blocked — fulfilment refused', [
                'order_id' => $order->id, 'expected' => $expected, 'captured' => $verifiedAmount,
            ]);
            throw new \RuntimeException("Captured amount {$verifiedAmount} is less than order amount {$expected} (order #{$order->id})");
        }
        if (abs($verifiedAmount - $expected) > $tolerance) {
            Log::warning('Payment amount mismatch (overpayment) — allowed', [
                'order_id' => $order->id, 'expected' => $expected, 'captured' => $verifiedAmount,
            ]);
        }
    }

    /**
     * Mark an order paid and create enrollments + commission. Idempotent.
     *
     * @return bool  true if this call processed the order, false if already paid
     */
    public function markPaid(Order $sessionOrder, ?string $transactionId, mixed $paymentDetails, ?float $verifiedAmount = null): bool
    {
        // Coach course-sale emails are COLLECTED inside the transaction and sent
        // AFTER it commits (doc item 5, 2026-06-30) — see the dispatch block below.
        $coachSales = [];

        $result = DB::transaction(function () use ($sessionOrder, $transactionId, $paymentDetails, $verifiedAmount, &$coachSales) {
            $order = Order::lockForUpdate()->findOrFail($sessionOrder->id);

            if ($order->payment_status === 'paid') {
                return false;
            }

            // F3 (audit 2026-06-26) — amount reconciliation. A signed/authentic
            // gateway event proves WHO paid, not HOW MUCH. When the caller supplies
            // the gateway-captured amount, refuse to fulfil + over-credit the coach
            // on a clear UNDERPAYMENT. Backward compatible: callers that don't pass
            // a verified amount keep the previous behaviour (logged, not blocked).
            $this->assertAmountSufficient($order, $verifiedAmount);

            $prevPaymentStatus = $order->payment_status; // for the audit log (H-A)

            $order->transaction_id  = $transactionId;
            $order->payment_status  = 'paid';
            $order->status          = 'completed';
            $order->payment_details = is_string($paymentDetails) ? $paymentDetails : json_encode($paymentDetails);
            // F15 (audit 2026-06-26) — stamp the wallet-credit idempotency marker
            // here too, so if an online-paid order is later toggled by an admin it
            // won't be re-credited (the column may not exist pre-migration → guard).
            if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'wallet_settled_at')) {
                $order->wallet_settled_at = now();
            }
            $order->save();

            foreach ($order->orderItems as $item) {
                $course = Course::withTrashed()->find($item->course_id);
                $instructor = $course?->instructor;
                if ($instructor) {
                    // 2026-06-01 (audit H9) — PER-ITEM payout. Previously this
                    // computed amountAfterCommission from the WHOLE order's
                    // paid_amount ONCE and credited that full amount to EACH
                    // item's instructor — a multi-item / multi-coach order
                    // over-credited every coach (N items => N× the order
                    // total). Now each instructor is credited their own
                    // item's price minus its commission, via the shared
                    // OrderItem::coachPayout() helper that the refund paths
                    // also use (so charge and refund stay symmetric).
                    //
                    // 2026-06-24 (Phase 2) — when the coach is on a Super-Admin
                    // pricing plan that DEFINES a platform commission, that rate
                    // wins (incl. the Enterprise 0–1% band) and is snapshotted
                    // onto the item so payout + invoice agree. Coaches with NO
                    // plan-defined rate are UNCHANGED — we honour the rate
                    // snapshotted on the order at checkout (backward compatible).
                    $planRate = optional($instructor->activePlan())->effectiveCommissionRate();
                    if ($planRate !== null) {
                        $planRate = max(0.0, min(100.0, (float) $planRate));
                        if ((float) ($item->commission_rate ?? -1) !== $planRate) {
                            $item->commission_rate = $planRate;
                            $item->save();
                        }
                        $instructor->increment('wallet_balance', $item->coachPayout($planRate));
                    } else {
                        $instructor->increment('wallet_balance', $item->coachPayout((float) $order->commission_rate));
                    }
                }

                // Audit 2026-05-18 — propagate batch_id from order_items to
                // enrollments. The student picked a batch at checkout
                // (order_items.batch_id); previously this was lost. New
                // enrollments now carry the batch so batch-scoped
                // attendance + announcements work end-to-end.
                //
                // UNIQUE on (user_id, course_id) means an existing enrolment
                // for this pair will NOT be overwritten — first enrolment
                // wins, which matches the prior idempotency contract.

                // BATCH CAPACITY (audit 2026-05-22)
                //
                // Two modes, controlled by .env BATCH_CAPACITY_ENFORCE:
                //   0 (default, warn-mode): log breach, allow enrollment
                //   1 (hard-enforce):       throw, abort the transaction,
                //                           leave order_status = pending
                //                           so coach can refund manually
                //
                // Recommended rollout: run in warn-mode for 1-2 weeks,
                // grep `batch-capacity-breach` log entries, contact any
                // affected coaches to raise capacity, then flip to =1.
                // Resolve the batch this enrollment lands in. The student may
                // have picked one at checkout (order_items.batch_id). If not,
                // and the course has exactly ONE active batch, auto-assign it
                // (2026-06-02) — otherwise the enrollment is batch-less and
                // batch-scoped fees / announcements / attendance can never
                // reach the student. Ambiguous (multi-batch) courses stay NULL
                // and are handled by the coach "Assign to batch" UI.
                $resolvedBatchId = ! empty($item->batch_id)
                    ? (int) $item->batch_id
                    : \App\Models\CourseBatch::soleBatchIdForCourse((int) $item->course_id, true);

                if (! empty($resolvedBatchId)) {
                    $batch = \App\Models\CourseBatch::find($resolvedBatchId);
                    if ($batch && $batch->wouldExceedCapacity(1)) {
                        $batch->logCapacityBreach('razorpay_webhook', (int) $order->buyer_id, $order->id);

                        if (env('BATCH_CAPACITY_ENFORCE', 0)) {
                            // Hard-enforce: abort the whole DB::transaction
                            // wrapping markPaid() so order status remains
                            // 'pending' and no wallet credit / enrollment
                            // is committed. Coach can refund the Razorpay
                            // payment + raise capacity, then retry.
                            throw new \RuntimeException(
                                "Batch #{$batch->id} '{$batch->title}' is full "
                                . "({$batch->seatsUsed()}/{$batch->capacity}). "
                                . "Order #{$order->id} not fulfilled — "
                                . "increase capacity or assign student to another batch."
                            );
                        }
                    }
                }

                // 2026-06-06 — key on (user, course, BATCH) so a student can be
                // enrolled in multiple batches of the same course; a paid order
                // for a different batch creates its own enrollment row instead
                // of colliding on the old (user, course) unique.
                Enrollment::firstOrCreate(
                    [
                        'user_id'   => $order->buyer_id,
                        'course_id' => $item->course_id,
                        'batch_id'  => $resolvedBatchId ?: null,
                    ],
                    [
                        'order_id'   => $order->id,
                        'has_access' => 1,
                    ]
                );

                // 2026-05-21 — auto-link buyer to course's coach.
                // Per Q2 of the multi-coach spec: buying a course adds
                // the student to that coach's roster automatically.
                // CoachStudentLink::link() is idempotent — if the
                // student was already on this coach's roster (added
                // earlier or bought another of their courses) it's a
                // no-op. If they had self-removed, it reactivates.
                $coachId = (int) ($course?->instructor_id ?? 0);
                if ($coachId > 0 && $order->buyer_id) {
                    // PLAN STUDENT CAPACITY (2026-06-24, Phase 3). A coach may
                    // take up to plan.student_capacity students. Only a NEW
                    // student (not already on the roster) consumes a seat — a
                    // returning buyer never re-counts. Two modes via .env
                    // PLAN_STUDENT_CAPACITY_ENFORCE (mirrors batch capacity):
                    //   0 (default, warn): log breach, allow (safe rollout)
                    //   1 (hard):          throw → abort txn, order stays pending
                    $alreadyOnRoster = \App\Models\CoachStudentLink::where('coach_id', $coachId)
                        ->where('student_id', $order->buyer_id)->where('status', 'active')->exists();
                    if (! $alreadyOnRoster && $instructor && $instructor->coachAtStudentCapacity(1)) {
                        \Log::warning('plan-student-capacity-breach', [
                            'coach_id'   => $coachId,
                            'plan'       => optional($instructor->activePlan())->slug,
                            'capacity'   => optional($instructor->activePlan())->student_capacity,
                            'current'    => $instructor->coachStudentCount(),
                            'student_id' => (int) $order->buyer_id,
                            'order_id'   => $order->id,
                        ]);
                        if (env('PLAN_STUDENT_CAPACITY_ENFORCE', 0)) {
                            throw new \RuntimeException(
                                'Coach #' . $coachId . ' has reached the student capacity of their plan ('
                                . optional($instructor->activePlan())->student_capacity . '). '
                                . 'Order #' . $order->id . ' not fulfilled — upgrade the coach plan and retry.'
                            );
                        }
                    }

                    \App\Models\CoachStudentLink::link(
                        $coachId,
                        (int) $order->buyer_id,
                        'purchase'
                    );
                }

                // Coach course-sale email (doc item 5) — collect per item; the
                // email is dispatched AFTER commit (below) so a slow/failed mail
                // can never hold or break the fulfilment transaction. Per-item
                // keeps it tenant-safe: a coach only ever hears about THEIR course.
                if ($instructor) {
                    $coachSales[] = ['coach' => $instructor, 'course' => $course, 'item' => $item, 'order' => $order];
                }
            }

            // Record coupon usage atomically. We rely on the order's stored
            // coupon_code rather than session state because webhooks are
            // session-less. If the same order is fulfilled twice (replay), the
            // outer `payment_status === 'paid'` early-return prevents reaching
            // here a second time.
            if (!empty($order->coupon_code)) {
                $coupon = \DB::table('coupons')->where('coupon_code', $order->coupon_code)->first();
                if ($coupon) {
                    \DB::table('coupons')->where('id', $coupon->id)->increment('usage_count');
                    \DB::table('coupon_uses')->insert([
                        'coupon_id' => $coupon->id,
                        'user_id'   => $order->buyer_id,
                        'order_id'  => $order->id,
                        'created_at'=> now(),
                        'updated_at'=> now(),
                    ]);
                }
            }

            // 2026-06-03 (Referral A+) — if the buyer was referred, mint the
            // referral commission now that the order is genuinely PAID. This is
            // the central paid path (online gateway + coach-manual route through
            // here), so it covers most real payments. Idempotent.
            try {
                app(\App\Services\ReferralCommissionService::class)->onOrderPaid($order);
            } catch (\Throwable $e) {
                Log::warning('Referral commission on paid order failed: ' . $e->getMessage());
            }

            // Enterprise H-A — audit the payment fulfilment (money trail).
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::PAYMENT_STATUS_CHANGED,
                'order',
                $order,
                ['payment_status' => $prevPaymentStatus],
                ['payment_status' => 'paid', 'status' => 'completed', 'transaction_id' => $transactionId],
                'Order #' . ($order->invoice_id ?? $order->id) . ' fulfilled (paid)'
            );

            Log::info('Order fulfilled', ['order_id' => $order->id, 'trx_id' => $transactionId]);

            return true;
        });

        // ── Coach course-sale emails (doc item 5, 2026-06-30) ──────────────────
        // Fire AFTER the transaction commits, and ONLY when this call actually
        // fulfilled the order ($result === true; a replay returns false). Each
        // coach is notified of THEIR course's sale only (tenant-safe). Wrapped in
        // try/catch so an SMTP failure can never affect the committed fulfilment.
        // The email is coach-branded + per-coach SMTP via the InAppNotification
        // pipeline, with a fallback to the platform mailer + default template.
        if ($result === true && ! empty($coachSales)) {
            $student = \App\Models\User::find($sessionOrder->buyer_id);
            foreach ($coachSales as $sale) {
                try {
                    $sale['coach']->notify(new \App\Notifications\CourseSaleToCoach(
                        $sale['order'], $sale['item'], $sale['course'], $student
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Coach course-sale email failed: ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * Reverse a previously-paid order: symmetric inverse of markPaid(). Claws
     * back each item's instructor wallet credit (via the SAME OrderItem::coachPayout
     * helper markPaid uses, so charge and refund stay symmetric), reverses referral
     * commission, marks the order refunded/declined and revokes access. Idempotent —
     * guarded by the `wallet_reversed` flag in payment_details, the SAME flag
     * InstructorDashboardController::mySellsupdate sets, so the two entry points can
     * never double-reverse. Returns true if this call performed the reversal.
     *
     * 2026-07-11 (Offline Payment Phase 2c) — extracted so the offline-payment
     * cancel path fully reverses money, not just access.
     */
    public function markRefunded(Order $sessionOrder, ?int $actorId = null): bool
    {
        return DB::transaction(function () use ($sessionOrder, $actorId) {
            $order = Order::lockForUpdate()->with('orderItems')->findOrFail($sessionOrder->id);

            if (str_contains((string) $order->payment_details, '"wallet_reversed":true')) {
                return false; // already reversed
            }

            // Referral claw-back (already a service; idempotent on live rows).
            try {
                app(\App\Services\ReferralCommissionService::class)
                    ->onOrderReversed($order, 'Offline payment cancelled');
            } catch (\Throwable $e) {
                Log::warning('Referral reversal (offline cancel) failed: ' . $e->getMessage());
            }

            // Per-item wallet claw-back, mirroring markPaid's credit exactly.
            foreach ($order->orderItems as $item) {
                $course = Course::withTrashed()->find($item->course_id);
                $instructor = $course?->instructor;
                if (! $instructor) {
                    continue;
                }
                $planRate = optional($instructor->activePlan())->effectiveCommissionRate();
                $rate = ($planRate !== null)
                    ? max(0.0, min(100.0, (float) $planRate))
                    : (float) $order->commission_rate;
                $instructor->decrement('wallet_balance', $item->coachPayout($rate));
            }

            $details = json_decode((string) $order->payment_details, true) ?: [];
            $details['wallet_reversed'] = true;
            $details['wallet_reversed_at'] = now()->toIso8601String();
            $details['wallet_reversed_by'] = $actorId;
            $order->payment_details = json_encode($details);
            $order->payment_status = 'refunded';
            $order->status = 'declined';
            $order->save();

            foreach ($order->orderItems as $item) {
                \Modules\Order\app\Models\Enrollment::where('user_id', $order->buyer_id)
                    ->where('course_id', $item->course_id)
                    ->when($item->batch_id, fn ($q) => $q->where('batch_id', $item->batch_id))
                    ->update(['has_access' => 0]);
            }

            return true;
        });
    }

    /**
     * Look up an existing order by transaction_id. Used by webhook handlers
     * that don't have the user's session.
     */
    public function findByTransactionId(string $transactionId): ?Order
    {
        return Order::where('transaction_id', $transactionId)->first();
    }
}
