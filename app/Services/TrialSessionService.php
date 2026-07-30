<?php

namespace App\Services;

use App\Models\CoachPaymentGateway;
use App\Models\CoachTrialEnquiry;
use App\Models\CoachTrialPayment;
use App\Models\CoachTrialSetting;
use App\Models\User;
use App\Notifications\TrialBookingToCoach;
use App\Notifications\TrialBookingToStudent;
use App\Notifications\TrialStudentWelcomeToStudent;
use App\Services\Payment\PaymentGatewayResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Razorpay\Api\Api;

/**
 * Trial-session booking + self-contained payment flow (2026-07-03).
 *
 * Kept deliberately separate from the course-order pipeline: a guest visitor can
 * book (and pay) without a login, and nothing here touches enrollments, batches
 * or the coach wallet. The charge amount is ALWAYS the coach's configured price
 * (server-side) — never trusted from the client. Razorpay is the v1 gateway.
 */
class TrialSessionService
{
    public function __construct(
        private PaymentGatewayResolverService $gwResolver,
        private TrialStudentProvisioner $provisioner
    ) {}

    /**
     * Create the enquiry and, when a price is configured, a pending payment +
     * Razorpay order. Returns a plain array describing the next step for the
     * popup JS: free-success, a Razorpay checkout payload, or an error.
     */
    public function book(int $coachId, CoachTrialSetting $settings, array $data, Request $request): array
    {
        // Duplicate-submission guard: same visitor (email+mobile) for this coach
        // within a short window → don't create a second lead.
        $recent = CoachTrialEnquiry::forCoach($coachId)
            ->where('email', $data['email'])
            ->where('mobile', $data['mobile'])
            ->where('created_at', '>=', now()->subSeconds(120))
            ->latest()
            ->first();

        $charges = $settings->chargesMoney();
        $price   = $charges ? round((float) $settings->price, 2) : 0.0;

        // If the same visitor just submitted and we already have a PENDING paid
        // enquiry with a live Razorpay order, resume it instead of duplicating.
        if ($recent) {
            if (! $charges) {
                return $this->freeResult($settings);
            }
            $existing = $recent->payment;
            if ($existing && $existing->status === CoachTrialPayment::STATUS_PENDING && $existing->gateway_order_id) {
                $resume = $this->checkoutPayloadFor($recent, $existing, $settings, $data);
                if ($resume) {
                    return $resume;
                }
            }
        }

        $enquiry = CoachTrialEnquiry::create([
            'coach_id'            => $coachId,
            'website_id'          => $this->resolveWebsiteId($coachId),
            'name'                => $data['name'],
            'email'               => $data['email'],
            'mobile'              => $data['mobile'],
            'gender'              => $data['gender'] ?? null,
            'height'              => $data['height'] ?? null,
            'weight'              => $data['weight'] ?? null,
            'plan_type'           => $data['plan_type'] ?? null,
            'course_type'         => $data['course_type'] ?? null,
            'slot_id'             => $data['slot_id'] ?? null,
            'time_slot'           => $data['time_slot'] ?? null,
            'reason'              => $data['reason'] ?? null,
            'problem_description' => $data['problem_description'] ?? null,
            'price'               => $price,
            'currency'            => $settings->currency ?: 'INR',
            'status'              => CoachTrialEnquiry::STATUS_PENDING,
            'payment_status'      => $charges ? CoachTrialEnquiry::PAY_UNPAID : CoachTrialEnquiry::PAY_FREE,
            'ip_address'          => $request->ip(),
            'user_agent'          => substr((string) $request->userAgent(), 0, 255),
            'source_page'         => substr((string) ($data['source_page'] ?? ''), 0, 190) ?: null,
        ]);

        if (! $charges) {
            // Free trial = a successful trial → provision the student now.
            $this->provisionStudent($enquiry->fresh());
            $this->notify($enquiry, false);
            return $this->freeResult($settings);
        }

        // Resolve the coach's Razorpay credentials (Enterprise self-managed →
        // admin-for-coach → platform default) and open a server-side order.
        $resolved = $this->gwResolver->resolve($coachId, 'razorpay');
        $key    = (string) $resolved->credential('razorpay_key');
        $secret = (string) $resolved->credential('razorpay_secret');
        if ($key === '' || $secret === '') {
            Log::warning('Trial payment: razorpay creds missing', ['coach_id' => $coachId]);
            return ['ok' => false, 'message' => __('Online payment is not available right now. Please contact us.')];
        }

        $amountPaise = (int) round($price * 100);
        try {
            $api      = new Api($key, $secret);
            $rzpOrder = $api->order->create([
                'amount'         => $amountPaise,
                'currency'       => 'INR',
                'receipt'        => 'trial_' . $enquiry->id,
                'payment_capture'=> 1,
                'notes'          => ['type' => 'trial_session', 'coach_id' => $coachId, 'enquiry_id' => $enquiry->id],
            ]);
        } catch (\Throwable $e) {
            Log::error('Trial payment: razorpay order create failed: ' . $e->getMessage(), ['coach_id' => $coachId]);
            return ['ok' => false, 'message' => __('Could not start the payment. Please try again.')];
        }

        $payment = CoachTrialPayment::create([
            'coach_id'           => $coachId,
            'enquiry_id'         => $enquiry->id,
            'gateway'            => 'razorpay',
            'gateway_order_id'   => (string) $rzpOrder['id'],
            'amount'             => $price,
            'currency'           => 'INR',
            'status'             => CoachTrialPayment::STATUS_PENDING,
            'gateway_owner_type' => $resolved->ownerType,
            'gateway_config_id'  => $resolved->configId,
        ]);

        return $this->checkoutPayloadFor($enquiry, $payment, $settings, $data, $resolved->credentials);
    }

    /**
     * Verify a Razorpay checkout callback, mark the payment paid (idempotent,
     * amount-reconciled) and fire the notifications. Signature verification is
     * the security gate: the amount came from the server-created order, so it
     * cannot be tampered with from the frontend.
     */
    public function verifyRazorpay(array $in): array
    {
        $payment = CoachTrialPayment::where('id', (int) ($in['payment_id'] ?? 0))
            ->where('gateway_order_id', (string) ($in['razorpay_order_id'] ?? ''))
            ->first();

        if (! $payment) {
            return ['ok' => false, 'message' => __('Payment record not found.')];
        }
        if ($payment->isPaid()) {
            return ['ok' => true, 'mode' => 'paid', 'message' => $this->successMessage($payment->coach_id)];
        }

        [$key, $secret] = $this->credsForPayment($payment);
        if (! $key || ! $secret) {
            return ['ok' => false, 'message' => __('Payment verification is unavailable.')];
        }

        try {
            $api = new Api($key, $secret);
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => (string) $in['razorpay_order_id'],
                'razorpay_payment_id' => (string) $in['razorpay_payment_id'],
                'razorpay_signature'  => (string) $in['razorpay_signature'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Trial payment: signature verify failed for payment ' . $payment->id . ': ' . $e->getMessage());
            $payment->update(['status' => CoachTrialPayment::STATUS_FAILED]);
            $payment->enquiry?->update(['payment_status' => CoachTrialEnquiry::PAY_FAILED]);
            return ['ok' => false, 'message' => __('Payment verification failed.')];
        }

        // Reconcile the captured amount, then mark paid under a row lock so a
        // double callback (or a webhook race) can only fulfil once.
        $capturedPaise = null;
        try {
            $fetched = $api->payment->fetch((string) $in['razorpay_payment_id']);
            $capturedPaise = (int) $fetched['amount'];
        } catch (\Throwable $e) {
            $fetched = null;
        }

        $fresh = false;
        DB::transaction(function () use ($payment, $in, $capturedPaise, $fetched, &$fresh) {
            $locked = CoachTrialPayment::lockForUpdate()->find($payment->id);
            if (! $locked || $locked->status === CoachTrialPayment::STATUS_PAID) {
                return;
            }
            $expectedPaise = (int) round((float) $locked->amount * 100);
            if ($capturedPaise !== null && $capturedPaise < $expectedPaise) {
                throw new \RuntimeException("Trial underpayment: captured {$capturedPaise} < expected {$expectedPaise} (payment #{$locked->id})");
            }
            $locked->update([
                'status'          => CoachTrialPayment::STATUS_PAID,
                'transaction_id'  => (string) $in['razorpay_payment_id'],
                'paid_at'         => now(),
                'payment_details' => json_encode($fetched ? $fetched->toArray() : []),
            ]);
            CoachTrialEnquiry::where('id', $locked->enquiry_id)->update(['payment_status' => CoachTrialEnquiry::PAY_PAID]);
            $fresh = true;
        });

        if ($fresh && $payment->enquiry) {
            // Payment succeeded → auto-create/link the student account, then notify.
            $this->provisionStudent($payment->enquiry->fresh());
            $this->notify($payment->enquiry->fresh(), true);
        }

        return ['ok' => true, 'mode' => 'paid', 'message' => $this->successMessage($payment->coach_id)];
    }

    /* ─────────────────────────────── helpers ───────────────────────────── */

    private function checkoutPayloadFor(CoachTrialEnquiry $enquiry, CoachTrialPayment $payment, CoachTrialSetting $settings, array $data, ?array $creds = null): array
    {
        if ($creds === null) {
            [$k, $s] = $this->credsForPayment($payment);
            $creds = ['razorpay_key' => $k];
        }
        return [
            'ok'          => true,
            'mode'        => 'payment',
            'gateway'     => 'razorpay',
            'key'         => (string) ($creds['razorpay_key'] ?? ''),
            'order_id'    => $payment->gateway_order_id,
            'amount'      => (int) round((float) $payment->amount * 100),
            'currency'    => 'INR',
            'name'        => $creds['razorpay_name'] ?? $settings->displayTitle(),
            'description' => $settings->displaySubtitle(),
            'theme'       => $creds['razorpay_theme_color'] ?? null,
            'payment_id'  => $payment->id,
            'prefill'     => [
                'name'    => $data['name'] ?? $enquiry->name,
                'email'   => $data['email'] ?? $enquiry->email,
                'contact' => $data['mobile'] ?? $enquiry->mobile,
            ],
            'verify_url'  => route('coach.trial-session.verify'),
        ];
    }

    /** @return array{0:?string,1:?string} [key, secret] for this payment's coach. */
    private function credsForPayment(CoachTrialPayment $payment): array
    {
        // Prefer the EXACT config row the order was created with.
        if ($payment->gateway_config_id) {
            $row = CoachPaymentGateway::find($payment->gateway_config_id);
            if ($row && is_array($row->credentials)) {
                return [$row->credentials['razorpay_key'] ?? null, $row->credentials['razorpay_secret'] ?? null];
            }
        }
        $resolved = $this->gwResolver->resolve((int) $payment->coach_id, 'razorpay');
        return [$resolved->credential('razorpay_key'), $resolved->credential('razorpay_secret')];
    }

    private function freeResult(CoachTrialSetting $settings): array
    {
        return ['ok' => true, 'mode' => 'free', 'message' => $this->successMessage($settings->coach_id, $settings)];
    }

    private function successMessage(?int $coachId, ?CoachTrialSetting $settings = null): string
    {
        $settings = $settings ?: ($coachId ? CoachTrialSetting::where('coach_id', $coachId)->first() : null);
        $msg = $settings?->success_message;
        return $msg ?: __('Thank you! Your trial session has been booked. We will contact you shortly.');
    }

    private function resolveWebsiteId(int $coachId): ?int
    {
        try {
            $id = \App\Models\CoachLandingPage::where('added_by', $coachId)->value('id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Provision (create or link) the student account for a completed trial and,
     * for a BRAND-NEW account, send the coach-branded welcome email carrying the
     * one-time password. Never lets a failure break the payment flow.
     */
    private function provisionStudent(CoachTrialEnquiry $enquiry): void
    {
        try {
            $result = $this->provisioner->provision($enquiry);

            if (($result['created'] ?? false) && ($result['user'] ?? null) && ! empty($result['plain'])) {
                $coach = User::find($enquiry->coach_id);
                if ($coach) {
                    $orgName = null;
                    try {
                        $orgName = app(\App\Services\BrandResolver::class)->forCoach((int) $coach->id)->name;
                    } catch (\Throwable $e) {
                    }
                    $orgName = $orgName ?: ($coach->name ?? config('app.name'));

                    $result['user']->notify(TrialStudentWelcomeToStudent::fromEnquiry(
                        $enquiry->fresh(), $coach, (string) $orgName, (string) $result['plain']
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Trial student provisioning failed: ' . $e->getMessage(), ['enquiry_id' => $enquiry->id]);
        }
    }

    /** Fire coach + visitor notifications; never let mail failures block the flow. */
    private function notify(CoachTrialEnquiry $enquiry, bool $paid): void
    {
        try {
            $coach = User::find($enquiry->coach_id);
            if ($coach) {
                $coach->notify(TrialBookingToCoach::fromEnquiry($enquiry));
            }
            if ($enquiry->email && filter_var($enquiry->email, FILTER_VALIDATE_EMAIL)) {
                $coachName = $coach?->name ?? config('app.name');
                Notification::route('mail', $enquiry->email)
                    ->notify(TrialBookingToStudent::fromEnquiry($enquiry, $coachName));
            }
        } catch (\Throwable $e) {
            Log::warning('Trial booking notification failed: ' . $e->getMessage(), ['enquiry_id' => $enquiry->id]);
        }
    }
}
