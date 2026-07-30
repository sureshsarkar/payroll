<?php

namespace App\Services;

use App\Models\CoachLandingPage;
use App\Models\CoachPageSection;
use App\Models\CoachPaymentGateway;
use App\Models\CoachPricingEnquiry;
use App\Models\CoachPricingPayment;
use App\Models\CoachTrainer;
use App\Models\TrainerSessionPackage;
use App\Services\Payment\PaymentGatewayResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

/**
 * Pricing & Plans booking payment (2026-07-13).
 *
 * A guest visitor selects a plan on a coach's public site and — when that coach
 * has opted in (coach_brand_settings.pricing_plan_collect_payment) and the plan
 * resolves to a numeric price > 0 — is charged via Razorpay.
 *
 * SECURITY INVARIANTS (mirror the trial flow):
 *   - The amount is ALWAYS re-derived server-side from the coach's stored
 *     pricing_plans_v1 section content (resolvePlanAmount) — the posted price
 *     string is ignored entirely.
 *   - The section is verified to belong to the resolved coach (tenant gate)
 *     before its price is trusted.
 *   - A payment is never marked 'paid' without Razorpay signature verification
 *     (verifyRazorpay) or the HMAC-verified webhook.
 *   - Gateway provenance (owner_type + config_id) is stamped so verification /
 *     webhook re-resolve the SAME merchant credentials.
 */
class PricingPaymentService
{
    public function __construct(
        private PaymentGatewayResolverService $gwResolver
    ) {}

    /**
     * Re-derive the authoritative plan amount from the coach's stored section
     * content. Never trusts the posted price. Returns:
     *   ['found'=>bool, 'amount'=>float, 'currency'=>string,
     *    'website_id'=>?int, 'section_id'=>?int]
     * found is true only when a matching card+period yields a numeric price > 0.
     */
    public function resolvePlanAmount(int $coachId, ?int $sectionId, string $category, ?string $courseType, ?string $timePeriod): array
    {
        $out = ['found' => false, 'amount' => 0.0, 'currency' => 'INR', 'website_id' => null, 'section_id' => null];

        $section = null;
        if ($sectionId) {
            $section = CoachPageSection::where('section_type', 'pricing_plans_v1')->find($sectionId);
            // Tenant gate — the section MUST belong to the resolved coach.
            if ($section && (int) optional($section->page)->coach_id !== (int) $coachId) {
                $section = null;
            }
        }

        // Fallback: first visible pricing section on any of the coach's pages.
        if (! $section) {
            $section = CoachPageSection::where('section_type', 'pricing_plans_v1')
                ->whereHas('page', fn ($q) => $q->where('coach_id', $coachId))
                ->orderBy('id')
                ->first();
        }

        if (! $section) {
            $out['website_id'] = $this->fallbackWebsiteId($coachId);
            return $out;
        }

        $out['section_id']  = (int) $section->id;
        $out['website_id']  = (int) (optional($section->page)->site_id ?: 0) ?: $this->fallbackWebsiteId($coachId);

        $content    = (array) ($section->content_json ?? []);
        $categories = (array) ($content['categories'] ?? []);
        $wantCat    = $this->norm($category);
        $wantPeriod = $this->norm($timePeriod);
        $couple     = strtolower(trim((string) $courseType)) === 'couple';

        foreach ($categories as $cat) {
            if ($this->norm($cat['name'] ?? '') !== $wantCat) {
                continue;
            }
            $periods = (array) ($cat[$couple ? 'couple_periods' : 'individual_periods'] ?? []);
            foreach ($periods as $p) {
                if ($this->norm($p['label'] ?? '') === $wantPeriod) {
                    $amount = $this->parsePrice($p['price'] ?? '');
                    $out['amount'] = $amount;
                    $out['found']  = $amount > 0;
                    return $out;
                }
            }
            // Category matched but no period matched → stop (avoid cross-category bleed).
            break;
        }

        return $out;
    }

    /**
     * Re-derive the authoritative amount for a Classes & Schedules "Book a Session"
     * booking from the coach's stored schedule_v1 section content. Price is driven
     * SOLELY by the selected time period (flat `periods` list of {label, price}) —
     * never trusts the posted price. Same tenant gate + return shape as
     * resolvePlanAmount. Used by storeScheduleBooking.
     */
    public function resolveScheduleAmount(int $coachId, ?int $sectionId, ?string $timePeriod): array
    {
        $out = ['found' => false, 'amount' => 0.0, 'currency' => 'INR', 'website_id' => null, 'section_id' => null];

        $section = null;
        if ($sectionId) {
            $section = CoachPageSection::where('section_type', 'schedule_v1')->find($sectionId);
            if ($section && (int) optional($section->page)->coach_id !== (int) $coachId) {
                $section = null;   // tenant gate
            }
        }
        if (! $section) {
            $section = CoachPageSection::where('section_type', 'schedule_v1')
                ->whereHas('page', fn ($q) => $q->where('coach_id', $coachId))
                ->orderBy('id')
                ->first();
        }
        if (! $section) {
            $out['website_id'] = $this->fallbackWebsiteId($coachId);
            return $out;
        }

        $out['section_id'] = (int) $section->id;
        $out['website_id'] = (int) (optional($section->page)->site_id ?: 0) ?: $this->fallbackWebsiteId($coachId);

        $content    = (array) ($section->content_json ?? []);
        $wantPeriod = $this->norm($timePeriod);
        foreach ((array) ($content['periods'] ?? []) as $p) {
            if ($this->norm($p['label'] ?? '') === $wantPeriod) {
                $amount = $this->parsePrice($p['price'] ?? '');
                $out['amount'] = $amount;
                $out['found']  = $amount > 0;
                return $out;
            }
        }

        return $out;
    }

    /**
     * Re-derive the authoritative price of a trainer session-package from the DB
     * (2026-07-15). NEVER trusts the posted price. The package is verified to
     * belong to BOTH the resolved coach AND the given trainer (double tenant gate)
     * and to be active before its price is trusted. Returns:
     *   ['found'=>bool, 'amount'=>float, 'currency'=>string, 'website_id'=>?int,
     *    'trainer'=>?CoachTrainer, 'package'=>?TrainerSessionPackage]
     */
    public function resolveTrainerPackageAmount(int $coachId, int $trainerId, int $packageId): array
    {
        $out = ['found' => false, 'amount' => 0.0, 'currency' => 'INR', 'website_id' => null, 'trainer' => null, 'package' => null];

        $trainer = CoachTrainer::forCoach($coachId)->active()->find($trainerId);
        if (! $trainer) {
            $out['website_id'] = $this->fallbackWebsiteId($coachId);
            return $out;
        }

        // Package must belong to this coach AND this trainer AND be active.
        $package = TrainerSessionPackage::forCoach($coachId)->active()
            ->where('trainer_id', $trainer->id)
            ->find($packageId);

        $out['trainer']    = $trainer;
        $out['website_id'] = (int) $trainer->website_id ?: $this->fallbackWebsiteId($coachId);

        if (! $package) {
            return $out;
        }

        $amount = round((float) $package->price, 2);
        $out['package']  = $package;
        $out['currency'] = $package->currency ?: 'INR';
        $out['amount']   = $amount;
        $out['found']    = $amount > 0;

        return $out;
    }

    /**
     * Server-authoritative amount for a "Trainer Booking" WEBSITE-BUILDER section
     * (trainer_booking_v1) — the coach configures the packages + prices INSIDE the
     * section, so no Coach-Panel trainer entity is required. The price is re-read
     * from the section's content_json by (section_id + tenant gate); the posted
     * price is never trusted. Mirrors resolveScheduleAmount / resolvePlanAmount.
     *
     * @param int|string $packageIndex position in the section's `packages` list
     */
    public function resolveTrainerSectionAmount(int $coachId, ?int $sectionId, $packageIndex): array
    {
        $out = ['found' => false, 'amount' => 0.0, 'currency' => 'INR', 'website_id' => null,
                'section_id' => null, 'trainer_name' => null, 'package_label' => null];

        $section = null;
        if ($sectionId) {
            $section = CoachPageSection::where('section_type', 'trainer_booking_v1')->find($sectionId);
            if ($section && (int) optional($section->page)->coach_id !== (int) $coachId) {
                $section = null;   // tenant gate
            }
        }
        if (! $section) {
            $out['website_id'] = $this->fallbackWebsiteId($coachId);
            return $out;
        }

        $out['section_id'] = (int) $section->id;
        $out['website_id'] = (int) (optional($section->page)->site_id ?: 0) ?: $this->fallbackWebsiteId($coachId);

        $content = (array) ($section->content_json ?? []);
        $out['trainer_name'] = trim((string) ($content['trainer_name'] ?? '')) ?: null;
        $out['currency']     = trim((string) ($content['currency'] ?? '')) ?: 'INR';

        $packages = array_values((array) ($content['packages'] ?? []));
        $idx = (int) $packageIndex;
        if (! array_key_exists($idx, $packages)) {
            return $out;
        }

        $pkg    = (array) $packages[$idx];
        $label  = trim((string) ($pkg['label'] ?? ''));
        $amount = round($this->parsePrice($pkg['price'] ?? ''), 2);

        $out['package_label'] = $label ?: null;
        $out['amount']        = $amount;
        $out['found']         = $amount > 0;

        return $out;
    }

    /**
     * Create a pending payment + Razorpay order for an already-persisted enquiry.
     * Amount is taken from the enquiry's server-resolved plan_amount. $notesType
     * discriminates the flow in the webhook (pricing_plan | trainer_personal_session).
     * Returns a checkout payload for the modal JS, or ['ok'=>false, ...].
     */
    public function startPayment(CoachPricingEnquiry $enquiry, string $notesType = 'pricing_plan'): array
    {
        $coachId = (int) $enquiry->coach_id;
        $price   = round((float) $enquiry->plan_amount, 2);
        if ($price <= 0) {
            return ['ok' => false, 'message' => __('This plan does not require online payment.')];
        }

        $resolved = $this->gwResolver->resolve($coachId, 'razorpay');
        $key    = (string) $resolved->credential('razorpay_key');
        $secret = (string) $resolved->credential('razorpay_secret');
        if ($key === '' || $secret === '') {
            Log::warning('Pricing payment: razorpay creds missing', ['coach_id' => $coachId]);
            return ['ok' => false, 'message' => __('Online payment is not available right now. Please contact us.')];
        }

        $amountPaise = (int) round($price * 100);
        try {
            $api      = new Api($key, $secret);
            $rzpOrder = $api->order->create([
                'amount'          => $amountPaise,
                'currency'        => 'INR',
                'receipt'         => ($notesType === 'pricing_plan' ? 'pricing_' : 'trainer_') . $enquiry->id,
                'payment_capture' => 1,
                'notes'           => ['type' => $notesType, 'coach_id' => $coachId, 'enquiry_id' => $enquiry->id],
            ]);
        } catch (\Throwable $e) {
            Log::error('Pricing payment: razorpay order create failed: ' . $e->getMessage(), ['coach_id' => $coachId]);
            return ['ok' => false, 'message' => __('Could not start the payment. Please try again.')];
        }

        $payment = CoachPricingPayment::create([
            'coach_id'           => $coachId,
            'enquiry_id'         => $enquiry->id,
            'gateway'            => 'razorpay',
            'gateway_order_id'   => (string) $rzpOrder['id'],
            'amount'             => $price,
            'currency'           => 'INR',
            'status'             => CoachPricingPayment::STATUS_PENDING,
            'gateway_owner_type' => $resolved->ownerType,
            'gateway_config_id'  => $resolved->configId,
        ]);

        $enquiry->update(['payment_status' => CoachPricingEnquiry::PAY_PENDING]);

        return [
            'ok'          => true,
            'mode'        => 'payment',
            'gateway'     => 'razorpay',
            'key'         => (string) ($resolved->credential('razorpay_key') ?? ''),
            'order_id'    => $payment->gateway_order_id,
            'amount'      => $amountPaise,
            'currency'    => 'INR',
            'name'        => $resolved->credential('razorpay_name') ?: config('app.name'),
            'description' => trim(($enquiry->category ?? 'Plan') . ' · ' . ($enquiry->time_period ?? '')),
            'theme'       => $resolved->credential('razorpay_theme_color') ?: null,
            'payment_id'  => $payment->id,
            'prefill'     => [
                'name'    => $enquiry->name,
                'email'   => $enquiry->email,
                'contact' => $enquiry->mobile,
            ],
            'verify_url'  => route('coach.pricing-enquiry.verify'),
            'cancel_url'  => route('coach.pricing-enquiry.cancel'),
        ];
    }

    /**
     * Verify a Razorpay checkout callback and mark the payment paid — idempotent,
     * amount-reconciled, row-locked. Signature verification is the security gate.
     */
    public function verifyRazorpay(array $in): array
    {
        $payment = CoachPricingPayment::where('id', (int) ($in['payment_id'] ?? 0))
            ->where('gateway_order_id', (string) ($in['razorpay_order_id'] ?? ''))
            ->first();

        if (! $payment) {
            return ['ok' => false, 'message' => __('Payment record not found.')];
        }
        if ($payment->isPaid()) {
            return ['ok' => true, 'mode' => 'paid', 'message' => __('Payment successful.')];
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
            Log::warning('Pricing payment: signature verify failed for payment ' . $payment->id . ': ' . $e->getMessage());
            $payment->update([
                'status'          => CoachPricingPayment::STATUS_FAILED,
                'payment_details' => json_encode(['error' => 'signature_verification_failed', 'message' => $e->getMessage()]),
            ]);
            $payment->enquiry?->update(['payment_status' => CoachPricingEnquiry::PAY_FAILED]);
            return ['ok' => false, 'message' => __('Payment verification failed.')];
        }

        $capturedPaise = null;
        try {
            $fetched = $api->payment->fetch((string) $in['razorpay_payment_id']);
            $capturedPaise = (int) $fetched['amount'];
        } catch (\Throwable $e) {
            $fetched = null;
        }

        $this->markPaidLocked($payment, (string) $in['razorpay_payment_id'], $capturedPaise, $fetched ? $fetched->toArray() : []);

        return ['ok' => true, 'mode' => 'paid', 'message' => __('Payment successful.')];
    }

    /**
     * Mark a payment paid under a row lock. Shared by the callback verify and the
     * webhook. Reconciles the captured amount (rejects underpayment) and only ever
     * transitions pending → paid once.
     */
    public function markPaidLocked(CoachPricingPayment $payment, string $txnId, ?int $capturedPaise, array $details): bool
    {
        $fresh = false;
        DB::transaction(function () use ($payment, $txnId, $capturedPaise, $details, &$fresh) {
            $locked = CoachPricingPayment::lockForUpdate()->find($payment->id);
            if (! $locked || $locked->status === CoachPricingPayment::STATUS_PAID) {
                return;
            }
            $expectedPaise = (int) round((float) $locked->amount * 100);
            if ($capturedPaise !== null && $capturedPaise < $expectedPaise) {
                throw new \RuntimeException("Pricing underpayment: captured {$capturedPaise} < expected {$expectedPaise} (payment #{$locked->id})");
            }
            $locked->update([
                'status'          => CoachPricingPayment::STATUS_PAID,
                'transaction_id'  => $txnId,
                'paid_at'         => now(),
                'payment_details' => json_encode($details),
            ]);
            CoachPricingEnquiry::where('id', $locked->enquiry_id)->update([
                'payment_status' => CoachPricingEnquiry::PAY_PAID,
                'paid_amount'    => $locked->amount,
            ]);
            $fresh = true;
        });

        // Fire the paid emails ONCE, after commit — only on the first pending→paid
        // transition, so the verify callback and the webhook can never double-send.
        // Best-effort: never let mail break the payment.
        if ($fresh) {
            $this->notifyPaid($payment->fresh()?->load('enquiry'));
        }

        return $fresh;
    }

    /**
     * On a fresh payment: coach "new paid booking" (branded email + bell) AND the
     * visitor's coach-branded paid receipt (guest, mail-only). Each is isolated so
     * one failing never blocks the other or the payment.
     */
    private function notifyPaid(?CoachPricingPayment $payment): void
    {
        if (! $payment) {
            return;
        }

        // Audit the money event (self-guarding; guest actor → system).
        \App\Services\ActivityLogger::log(
            \App\Models\ActivityLog::PAYMENT_STATUS_CHANGED,
            'pricing_payment',
            $payment,
            ['status' => 'pending'],
            ['status' => 'paid', 'gateway' => $payment->gateway, 'transaction_id' => $payment->transaction_id, 'amount' => $payment->amount],
            'Pricing booking payment #' . $payment->id . ' marked paid'
        );

        // Coach — branded email + bell + broadcast.
        try {
            $coach = \App\Models\User::find($payment->coach_id);
            if ($coach) {
                $coach->notify(\App\Notifications\PricingBookingPaidToCoach::fromPayment($payment));
            }
        } catch (\Throwable $e) {
            Log::warning('Pricing paid coach-notify failed: ' . $e->getMessage(), ['payment_id' => $payment->id]);
        }

        // Student — coach-branded paid receipt to the guest's email (on-demand).
        try {
            $email = optional($payment->enquiry)->email;
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                \Illuminate\Support\Facades\Notification::route('mail', $email)
                    ->notify(\App\Notifications\PricingBookingReceiptToStudent::fromPayment($payment, $this->coachName((int) $payment->coach_id)));
            }
        } catch (\Throwable $e) {
            Log::warning('Pricing paid student-receipt failed: ' . $e->getMessage(), ['payment_id' => $payment->id]);
        }
    }

    /** The coach's brand/display name for the receipt. Brand → coach name → app name. */
    private function coachName(int $coachId): string
    {
        try {
            $name = app(\App\Services\BrandResolver::class)->forCoach($coachId)->name;
            if ($name) {
                return (string) $name;
            }
        } catch (\Throwable $e) {
        }
        return (string) (optional(\App\Models\User::find($coachId))->name ?: config('app.name'));
    }

    /**
     * Visitor dismissed / abandoned the gateway. Keep the enquiry (as the spec
     * requires) and flag the payment + enquiry as cancelled. Only touches a
     * PENDING payment — never overrides a verified paid state.
     */
    public function markCancelled(int $paymentId, int $coachId): array
    {
        $payment = CoachPricingPayment::forCoach($coachId)->find($paymentId);
        if (! $payment) {
            return ['ok' => false, 'message' => __('Payment record not found.')];
        }
        if ($payment->status === CoachPricingPayment::STATUS_PENDING) {
            $payment->update(['status' => CoachPricingPayment::STATUS_CANCELLED]);
            $payment->enquiry?->update(['payment_status' => CoachPricingEnquiry::PAY_CANCELLED]);
        }
        return ['ok' => true];
    }

    /* ─────────────────────────────── helpers ───────────────────────────── */

    /** @return array{0:?string,1:?string} [key, secret] for this payment's coach. */
    private function credsForPayment(CoachPricingPayment $payment): array
    {
        if ($payment->gateway_config_id) {
            $row = CoachPaymentGateway::find($payment->gateway_config_id);
            if ($row && is_array($row->credentials)) {
                return [$row->credentials['razorpay_key'] ?? null, $row->credentials['razorpay_secret'] ?? null];
            }
        }
        $resolved = $this->gwResolver->resolve((int) $payment->coach_id, 'razorpay');
        return [$resolved->credential('razorpay_key'), $resolved->credential('razorpay_secret')];
    }

    /** "₹1,20,000" / "6000 " → 120000.0 / 6000.0 ; "Free"/""/"Contact us" → 0.0 */
    private function parsePrice($raw): float
    {
        $s = preg_replace('/[^0-9.]/', '', (string) $raw);
        if ($s === '' || $s === '.') {
            return 0.0;
        }
        // guard against multiple dots ("1.2.3") — keep the first numeric token
        if (substr_count($s, '.') > 1) {
            $parts = explode('.', $s);
            $s = array_shift($parts) . '.' . implode('', $parts);
        }
        return round((float) $s, 2);
    }

    private function norm($s): string
    {
        return strtolower(trim((string) $s));
    }

    private function fallbackWebsiteId(int $coachId): ?int
    {
        try {
            $id = CoachLandingPage::where('added_by', $coachId)->value('id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
