<?php

namespace App\Services;

use App\Models\CoachBrandSetting;
use App\Models\FeePayment;
use App\Models\OfflinePayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;

/**
 * Offline Payment — the single record-only engine (Phase 1).
 *
 * Every offline-payment entry point (course purchase, fee installment, and —
 * later — trials / mark-order-paid) funnels through here, so behaviour, proof
 * handling, approval gating, tenant scoping and the audit trail are identical
 * everywhere. NO online gateway is ever touched.
 *
 * White-label: the caller passes the resolved coach id; every write carries it
 * and proof files live under a per-coach private folder. Nothing is specific to
 * any one coach.
 */
class OfflinePaymentService
{
    /** Private disk folder root (non-web, gated download only). */
    private const PROOF_DIR = 'offline-payment-proofs';

    /**
     * Record an offline payment.
     *
     * @param  int  $coachId  tenant
     * @param  array  $data  validated: student_id, source_type, source_id?, course_id?,
     *                       batch_id?, amount, method, reference_no?, paid_at?, notes?,
     *                       status?, currency?, order_id? (for source_type=order)
     */
    public function record(int $coachId, array $data, ?UploadedFile $proof = null): OfflinePayment
    {
        $op = DB::transaction(function () use ($coachId, $data, $proof) {
            $needsApproval = (bool) optional(CoachBrandSetting::firstOrCreateForCoach($coachId))
                ->offline_payment_needs_approval;

            [$proofPath, $proofName] = $this->storeProof($coachId, $proof);

            $paidAt = ! empty($data['paid_at'])
                ? \Illuminate\Support\Carbon::parse($data['paid_at'])
                : now();
            if ($paidAt->isFuture()) {
                $paidAt = now();  // money can't be received in the future
            }

            // Tax on the receipt — the entered amount is the GROSS received; split
            // it into base + tax using the coach's tax profile (inclusive). No tax
            // profile → all null and the receipt shows the amount as-is.
            $tax = app(\App\Services\Tax\TaxService::class)
                ->computeInclusive($coachId, (float) $data['amount'], $data['course_tax_rate_id'] ?? null);
            $hasTax = ! empty($tax['tax_amount']) && $tax['tax_amount'] > 0;

            $op = OfflinePayment::create([
                'coach_id'        => $coachId,
                'student_id'      => (int) $data['student_id'],
                'source_type'     => $data['source_type'] ?? OfflinePayment::SOURCE_ORDER,
                'source_id'       => $data['source_id'] ?? null,
                'course_id'       => $data['course_id'] ?? null,
                'batch_id'        => $data['batch_id'] ?? null,
                'amount'          => (float) $data['amount'],
                'base_amount'     => $hasTax ? $tax['taxable_amount'] : null,
                'tax_amount'      => $hasTax ? $tax['tax_amount'] : null,
                'tax_rate'        => $hasTax ? $tax['rate'] : null,
                'tax_label'       => $hasTax ? $tax['label'] : null,
                'currency'        => $data['currency'] ?? null,
                'method'          => $data['method'] ?? 'cash',
                'reference_no'    => $data['reference_no'] ?? null,
                'paid_at'         => $paidAt,
                'proof_path'      => $proofPath,
                'proof_name'      => $proofName,
                'notes'           => $data['notes'] ?? null,
                'status'          => $data['status'] ?? OfflinePayment::STATUS_PAID,
                'approval_status' => $needsApproval ? OfflinePayment::APPROVAL_PENDING : OfflinePayment::APPROVAL_AUTO,
                'recorded_by'     => (int) userAuth()->id,
                'receipt_no'      => OfflinePayment::generateReceiptNo($coachId),
                'order_id'        => $data['order_id'] ?? null,
            ]);

            // Apply the domain effect immediately only when auto-approved.
            if ($op->approval_status === OfflinePayment::APPROVAL_AUTO) {
                $this->applyEffect($op);
            }

            $this->audit($op, 'offline_payment_recorded',
                "Offline payment {$op->receipt_no} recorded ({$op->methodLabel()}, {$op->amount})"
                . ($needsApproval ? ' — awaiting approval' : ''));

            return $op->refresh();
        });

        // Email the branded receipt AFTER the transaction commits (only for a
        // persisted, effective payment) — never inside the txn.
        $this->maybeEmailReceipt($op);

        return $op;
    }

    /** Approve a pending payment → apply its effect. Idempotent. */
    public function approve(OfflinePayment $op): OfflinePayment
    {
        if ($op->approval_status !== OfflinePayment::APPROVAL_PENDING) {
            return $op;
        }
        $op = DB::transaction(function () use ($op) {
            $op->forceFill([
                'approval_status' => OfflinePayment::APPROVAL_APPROVED,
                'approved_by'     => (int) userAuth()->id,
                'approved_at'     => now(),
            ])->save();

            $this->applyEffect($op);
            $this->audit($op, 'offline_payment_approved', "Offline payment {$op->receipt_no} approved");

            return $op->refresh();
        });

        $this->maybeEmailReceipt($op); // now effective → email the branded receipt

        return $op;
    }

    /** Reject a pending payment (no effect was applied). */
    public function reject(OfflinePayment $op, ?string $reason = null): OfflinePayment
    {
        if ($op->approval_status !== OfflinePayment::APPROVAL_PENDING) {
            return $op;
        }
        $op->forceFill([
            'approval_status' => OfflinePayment::APPROVAL_REJECTED,
            'approved_by'     => (int) userAuth()->id,
            'approved_at'     => now(),
            'cancel_reason'   => $reason,
        ])->save();

        $this->audit($op, 'offline_payment_rejected', "Offline payment {$op->receipt_no} rejected");

        return $op;
    }

    /** Cancel a payment. Reverses the effect if it was applied. */
    public function cancel(OfflinePayment $op, ?string $reason = null): OfflinePayment
    {
        if ($op->cancelled_at) {
            return $op;
        }
        return DB::transaction(function () use ($op, $reason) {
            if ($op->isEffective()) {
                $this->reverseEffect($op);
            }
            $op->forceFill([
                'cancelled_by'  => (int) userAuth()->id,
                'cancelled_at'  => now(),
                'cancel_reason' => $reason,
            ])->save();

            $this->audit($op, 'offline_payment_cancelled',
                "Offline payment {$op->receipt_no} cancelled" . ($reason ? ": {$reason}" : ''));

            return $op->refresh();
        });
    }

    /* ───────── domain effects ───────── */

    /**
     * Apply the payment to its domain object. Only a fully-PAID payment grants
     * access; partial/pending payments are recorded but do not enroll.
     */
    protected function applyEffect(OfflinePayment $op): void
    {
        if ($op->status !== OfflinePayment::STATUS_PAID) {
            return;
        }

        if ($op->source_type === OfflinePayment::SOURCE_ORDER && $op->order_id) {
            // The order was created up-front (pending). Mark it paid → the proven
            // fulfilment path creates enrollments + credits commission, idempotently.
            $order = Order::find($op->order_id);
            if ($order) {
                app(PaymentFulfilmentService::class)->markPaid(
                    $order,
                    'OFFLINE-' . $op->receipt_no,
                    ['offline' => true, 'method' => $op->method, 'reference' => $op->reference_no],
                    (float) $op->amount
                );
            }
            return;
        }

        if ($op->source_type === OfflinePayment::SOURCE_FEE && $op->source_id) {
            // Settle the fee demand by writing a paid fee_payments row (same shape
            // the existing v219 flow uses) and link it back.
            $fp = FeePayment::create([
                'fee_demand_id' => (int) $op->source_id,
                'student_id'    => $op->student_id,
                'receipt_no'    => FeePayment::generateReceiptNo(),
                'amount'        => $op->amount,
                'gateway'       => $op->method === 'other' ? 'manual' : $op->method,
                'reference_no'  => $op->reference_no,
                'status'        => 'paid',
                'paid_at'       => $op->paid_at,
                'note'          => $op->notes,
                'recorded_by'   => $op->recorded_by,
            ]);
            $op->forceFill(['fee_payment_id' => $fp->id])->save();

            // Preserve the existing fee-module money-trail audit (subject = the
            // FeePayment) so the Fees history/reporting that queries module='fee'
            // is unbroken; the unified offline_payment audit is written separately.
            ActivityLogger::log(
                \App\Models\ActivityLog::PAYMENT_STATUS_CHANGED,
                'fee',
                $fp,
                null,
                ['status' => 'paid', 'gateway' => $fp->gateway, 'amount' => $fp->amount],
                'Manual fee payment ' . $fp->receipt_no . ' (demand #' . $fp->fee_demand_id . ') recorded'
            );
            return;
        }

        if ($op->source_type === OfflinePayment::SOURCE_TRIAL && $op->source_id) {
            // Settle the trial: mark its payment + the enquiry paid. The student
            // was already provisioned by the caller (so $op->student_id is set).
            $enquiry = \App\Models\CoachTrialEnquiry::find($op->source_id);
            if ($enquiry) {
                $vals = [
                    'status'         => \App\Models\CoachTrialPayment::STATUS_PAID,
                    'paid_at'        => $op->paid_at,
                    'transaction_id' => 'OFFLINE-' . $op->receipt_no,
                    'gateway'        => 'offline_' . $op->method,
                ];
                if ($payment = $enquiry->payment) {
                    $payment->forceFill($vals)->save();
                } else {
                    \App\Models\CoachTrialPayment::create(array_merge($vals, [
                        'coach_id'   => $enquiry->coach_id,
                        'enquiry_id' => $enquiry->id,
                        'amount'     => $op->amount,
                        'currency'   => $op->currency ?: $enquiry->currency,
                    ]));
                }
                $enquiry->forceFill(['payment_status' => \App\Models\CoachTrialEnquiry::PAY_PAID])->save();
            }
        }
    }

    /**
     * Reverse a previously-applied effect on cancel.
     * FEE: void the fee_payments row (refunded). ORDER: mark the order refunded +
     * revoke the student's access for its items. (Deeper commission/wallet
     * reconciliation for cancelled offline orders is handled on the order screen;
     * this keeps access + order state correct.)
     */
    protected function reverseEffect(OfflinePayment $op): void
    {
        if ($op->source_type === OfflinePayment::SOURCE_FEE && $op->fee_payment_id) {
            FeePayment::where('id', $op->fee_payment_id)->update([
                'status'      => 'refunded',
                'refunded_at' => now(),
            ]);
            return;
        }

        if ($op->source_type === OfflinePayment::SOURCE_ORDER && $op->order_id) {
            // 2026-07-11 (Phase 2c) — FULL symmetric reversal: instructor wallet
            // claw-back + referral reversal + order refunded/declined + access
            // revoked, idempotent via the shared wallet_reversed flag. Replaces
            // the earlier access-only reversal.
            $order = Order::find($op->order_id);
            if ($order) {
                app(PaymentFulfilmentService::class)->markRefunded($order, (int) userAuth()->id);
            }
        }
    }

    /* ───────── proof storage (private, per-coach) ───────── */

    /** @return array{0: ?string, 1: ?string} [path, originalName] */
    private function storeProof(int $coachId, ?UploadedFile $proof): array
    {
        if (! $proof) {
            return [null, null];
        }
        // Per-coach folder on the PRIVATE (non-web) disk. Served only through the
        // gated download route, which re-checks tenant ownership.
        $path = $proof->store(self::PROOF_DIR . '/' . $coachId, 'private');
        return [$path, mb_substr((string) $proof->getClientOriginalName(), 0, 255)];
    }

    /* ───────── branded receipt email to the student ───────── */

    /**
     * Email the coach-branded receipt to the student. Only for an EFFECTIVE
     * payment, only when the coach hasn't disabled it, and only when the student
     * has a valid email. Sends through the coach's own SMTP (else platform) via
     * CoachMailer. Best-effort — a mail failure never breaks the record flow.
     */
    private function maybeEmailReceipt(OfflinePayment $op): void
    {
        if (! $op->isEffective()) {
            return;
        }
        $brand = CoachBrandSetting::firstOrCreateForCoach($op->coach_id);
        // Default ON: a freshly-created settings row reports null in memory (DB
        // default not hydrated), so only skip when the coach EXPLICITLY disabled it.
        $emailEnabled = $brand->offline_payment_email_receipt === null
            ? true
            : (bool) $brand->offline_payment_email_receipt;
        if (! $emailEnabled) {
            return; // coach turned it off
        }
        $student = \App\Models\User::find($op->student_id);
        $email = $student?->email;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $b = app(\App\Services\BrandResolver::class)->forCoach($op->coach_id);
            $html = view('frontend.instructor-dashboard.offline-payments.receipt', [
                'op'    => $op->load(['student', 'course']),
                'brand' => $b,
            ])->render();
            $subject = ($b->name ?? config('app.name')) . ' — ' . __('Payment receipt') . ' ' . $op->receipt_no;

            $ok = app(\App\Services\CoachMailer::class)->send($op->coach_id, $email, $subject, $html);
            if ($ok) {
                $this->audit($op, 'offline_payment_receipt_emailed', "Receipt {$op->receipt_no} emailed to {$email}");
            }
        } catch (\Throwable $e) {
            Log::warning('offline-receipt-email-failed: ' . $e->getMessage());
        }
    }

    /* ───────── audit ───────── */

    private function audit(OfflinePayment $op, string $action, string $description): void
    {
        ActivityLogger::log(
            $action,
            'offline_payment',
            $op,
            null,
            [
                'coach_id'        => $op->coach_id,
                'student_id'      => $op->student_id,
                'source_type'     => $op->source_type,
                'amount'          => $op->amount,
                'method'          => $op->method,
                'status'          => $op->status,
                'approval_status' => $op->approval_status,
                'receipt_no'      => $op->receipt_no,
            ],
            $description
        );
    }
}
