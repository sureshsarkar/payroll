<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CoachDomain;
use App\Models\CoachLandingPage;
use App\Models\CoachPricingEnquiry;
use App\Models\CoachPricingPayment;
use App\Models\User;
use App\Services\PricingPaymentService;
use Illuminate\Http\Request;

/**
 * Public endpoint for the website-builder "Pricing & Plans" Book Class modal.
 * Stores a lead scoped to the OWNING coach. The coach is resolved from the host
 * (custom domain / subdomain) where possible — the posted coach_id is only a
 * fallback and is validated to be a real instructor, so a lead can never be
 * attributed to a non-coach or forged onto the platform.
 */
class PricingEnquiryController extends Controller
{
    public function store(Request $request, ?PricingPaymentService $pay = null)
    {
        // Router injects $pay; a direct call (tests / internal) resolves it here.
        $pay ??= app(PricingPaymentService::class);

        $data = $request->validate([
            'coach_id'      => 'nullable|integer',
            'section_id'    => 'nullable|integer',
            'category'      => 'nullable|string|max:120',
            'course_type'   => 'nullable|string|max:40',
            'time_period'   => 'nullable|string|max:80',
            'price'         => 'nullable|string|max:40',
            'name'          => 'required|string|max:150',
            'email'         => 'required|email|max:190',
            'mobile'        => 'required|string|max:40',
            'age'           => 'nullable|string|max:10',
            'gender'        => 'nullable|string|max:20',
            'reason'        => 'nullable|string|max:60',
            'time_slot'     => 'nullable|string|max:150',
            // free-form extras (problem desc, height/weight, couple person 2)
            'problem'       => 'nullable|string|max:1000',
            'height'        => 'nullable|string|max:20',
            'weight'        => 'nullable|string|max:20',
            'p2_name'       => 'nullable|string|max:150',
            'p2_age'        => 'nullable|string|max:10',
            'p2_gender'     => 'nullable|string|max:20',
        ]);

        $coachId = $this->resolveCoachId((int) ($data['coach_id'] ?? 0));
        if ($coachId <= 0) {
            return response()->json(['ok' => false, 'message' => __('Could not identify the coach. Please reload and try again.')], 422);
        }

        $details = array_filter([
            'problem_description' => $data['problem'] ?? null,
            'height'              => $data['height'] ?? null,
            'weight'              => $data['weight'] ?? null,
            'person2'             => array_filter([
                'name'   => $data['p2_name'] ?? null,
                'age'    => $data['p2_age'] ?? null,
                'gender' => $data['p2_gender'] ?? null,
            ]) ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        // The always-present lead columns. The booking form must save even if the
        // 2026-07-13 payment migrations haven't run yet on this environment.
        $payload = [
            'coach_id'    => $coachId,
            'category'    => $data['category'] ?? null,
            'course_type' => $data['course_type'] ?? null,
            'time_period' => $data['time_period'] ?? null,
            'price'       => $data['price'] ?? null,
            'name'        => $data['name'],
            'email'       => $data['email'],
            'mobile'      => $data['mobile'],
            'age'         => $data['age'] ?? null,
            'gender'      => $data['gender'] ?? null,
            'reason'      => $data['reason'] ?? null,
            'time_slot'   => $data['time_slot'] ?? null,
            'details'     => $details ?: null,
            'status'      => CoachPricingEnquiry::STATUS_NEW,
        ];

        // 2026-07-13 — re-derive the authoritative plan amount SERVER-SIDE from the
        // coach's stored section content (the posted `price` is never trusted).
        // Guarded: if the payment columns/table aren't migrated on this env, the
        // enquiry still saves as a plain lead (no 500 on the visitor-facing form).
        $plan = null;
        $paymentReady = $this->paymentColumnsReady();
        if ($paymentReady) {
            try {
                $plan = $pay->resolvePlanAmount(
                    $coachId,
                    (int) ($data['section_id'] ?? 0) ?: null,
                    (string) ($data['category'] ?? ''),
                    $data['course_type'] ?? null,
                    $data['time_period'] ?? null
                );
                $payload += [
                    'section_id'     => $plan['section_id'],
                    'website_id'     => $plan['website_id'],
                    'plan_amount'    => $plan['found'] ? $plan['amount'] : null,
                    'currency'       => $plan['currency'],
                    'payment_status' => CoachPricingEnquiry::PAY_UNPAID,
                ];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Pricing enquiry: plan resolve failed: ' . $e->getMessage());
                $plan = null;
            }
        }

        $enquiry = CoachPricingEnquiry::create($payload);

        // 2026-07-14 — a priced plan ALWAYS redirects to the payment gateway
        // (coach's own gateway, else the platform default). No Thank-You is shown
        // before payment. Free / "Contact us" / unpriced plans have no amount to
        // charge, so they stay a lead (the only case that keeps the old ack).
        if ($paymentReady && $plan && $plan['found']) {
            try {
                $checkout = $pay->startPayment($enquiry);
                if ($checkout['ok'] ?? false) {
                    return response()->json($checkout);
                }
            } catch (\Throwable $e) {
                // No gateway available anywhere → can't charge; fall back to a lead
                // so the visitor isn't stranded (the enquiry is already saved unpaid).
                \Illuminate\Support\Facades\Log::warning('Pricing payment start failed: ' . $e->getMessage());
            }
        }

        return response()->json(['ok' => true, 'mode' => 'lead', 'message' => __('Thank you! Your booking request has been received. We will contact you shortly.')]);
    }

    /**
     * True only when the 2026-07-13 payment migrations have run on this DB.
     * Cached per-process so the visitor-facing form pays no repeated Schema cost.
     * When false, the booking flow degrades gracefully to plain lead capture.
     */
    private function paymentColumnsReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $ready = \Illuminate\Support\Facades\Schema::hasColumn('coach_pricing_enquiries', 'payment_status')
                && \Illuminate\Support\Facades\Schema::hasTable('coach_pricing_payments');
        } catch (\Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    /**
     * Razorpay checkout callback for a pricing-plan payment. Delegates to the
     * service, whose signature verification is the security gate — a payment is
     * never marked paid on trust.
     */
    public function verify(Request $request, ?PricingPaymentService $pay = null)
    {
        $pay ??= app(PricingPaymentService::class);
        $in = $request->validate([
            'payment_id'          => 'required|integer',
            'razorpay_payment_id' => 'required|string|max:120',
            'razorpay_order_id'   => 'required|string|max:120',
            'razorpay_signature'  => 'required|string|max:255',
        ]);

        return response()->json($pay->verifyRazorpay($in));
    }

    /**
     * Visitor dismissed / abandoned the gateway. Keeps the enquiry and flags the
     * payment cancelled. Scoped to the host's coach so one site can't cancel
     * another coach's payments; a genuine completed payment is unaffected (the
     * webhook/verify still transitions it to paid).
     */
    public function cancel(Request $request, ?PricingPaymentService $pay = null)
    {
        $pay ??= app(PricingPaymentService::class);
        $in = $request->validate(['payment_id' => 'required|integer']);
        $coachId = $this->resolveCoachId(0);

        return response()->json($pay->markCancelled((int) $in['payment_id'], $coachId));
    }

    /**
     * Classes & Schedules "Book a Session" popup (2026-07-14). A visitor books a
     * class slot: the enquiry is created FIRST (carrying class / slot / trainer +
     * plan / course / period / height / weight / reason / problem), then — when the
     * selected time period resolves to a price > 0 — redirects to the gateway.
     * Reuses the pricing payment engine (resolveScheduleAmount + startPayment) and
     * the shared verify/cancel/webhook. Retry never creates a duplicate enquiry.
     */
    public function storeScheduleBooking(Request $request, ?PricingPaymentService $pay = null)
    {
        $pay ??= app(PricingPaymentService::class);

        $data = $request->validate([
            'coach_id'    => 'nullable|integer',
            'section_id'  => 'nullable|integer',
            'plan_type'   => 'nullable|string|max:60',
            'course_type' => 'nullable|string|max:60',
            'time_period' => 'nullable|string|max:80',
            'price'       => 'nullable|string|max:40',
            'class_name'  => 'nullable|string|max:150',
            'class_time'  => 'nullable|string|max:150',
            'trainer'     => 'nullable|string|max:150',
            'schedule_id' => 'nullable|string|max:150',
            'source_page' => 'nullable|string|max:190',
            'source_button' => 'nullable|string|max:120',
            'name'        => 'required|string|max:150',
            'email'       => 'required|email|max:190',
            'mobile'      => ['required', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{7,40}$/', 'regex:/(?:.*\d){7,}/'],
            'reason'      => 'nullable|string|max:60',
            'problem'     => 'nullable|string|max:1000',
            'height'      => 'nullable|string|max:20',
            'weight'      => 'nullable|string|max:20',
        ]);

        $coachId = $this->resolveCoachId((int) ($data['coach_id'] ?? 0));
        if ($coachId <= 0) {
            return response()->json(['ok' => false, 'message' => __('Could not identify the coach. Please reload and try again.')], 422);
        }

        // Server-authoritative amount from the schedule section's periods config.
        $plan = ['found' => false, 'amount' => 0.0, 'currency' => 'INR', 'website_id' => null, 'section_id' => null];
        $paymentReady = $this->paymentColumnsReady();
        if ($paymentReady) {
            try {
                $plan = $pay->resolveScheduleAmount($coachId, (int) ($data['section_id'] ?? 0) ?: null, $data['time_period'] ?? null);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Schedule booking: amount resolve failed: ' . $e->getMessage());
            }
        }

        $details = array_filter([
            'class_name' => $data['class_name'] ?? null,
            'trainer'    => $data['trainer'] ?? null,
            'plan_type'  => $data['plan_type'] ?? null,
            'height'     => $data['height'] ?? null,
            'weight'     => $data['weight'] ?? null,
            'problem_description' => $data['problem'] ?? null,
            'domain'     => $request->getHost(),
        ], fn ($v) => $v !== null && $v !== '');

        // Retry without duplicating: reuse a recent unpaid enquiry from the same
        // visitor for the same class/period instead of creating a second row.
        $enquiry = null;
        if ($paymentReady) {
            $enquiry = CoachPricingEnquiry::forCoach($coachId)
                ->where('email', $data['email'])->where('mobile', $data['mobile'])
                ->where('schedule_id', $data['schedule_id'] ?? '')
                ->where('time_period', $data['time_period'] ?? '')
                ->whereIn('payment_status', [CoachPricingEnquiry::PAY_UNPAID, CoachPricingEnquiry::PAY_PENDING, CoachPricingEnquiry::PAY_FAILED, CoachPricingEnquiry::PAY_CANCELLED])
                ->where('created_at', '>=', now()->subMinutes(30))
                ->latest('id')->first();
        }

        $payload = [
            'coach_id'    => $coachId,
            'category'    => $data['plan_type'] ?? null,        // plan type (Online/Offline)
            'course_type' => $data['course_type'] ?? null,
            'time_period' => $data['time_period'] ?? null,
            'price'       => $data['price'] ?? null,
            'name'        => $data['name'],
            'email'       => $data['email'],
            'mobile'      => $data['mobile'],
            'reason'      => $data['reason'] ?? null,
            'time_slot'   => $data['class_time'] ?? null,       // slot time
            'details'     => $details ?: null,
            'status'      => CoachPricingEnquiry::STATUS_NEW,
            'source_page' => $data['source_page'] ?? 'Class Schedule',
            'source_button' => $data['source_button'] ?? null,
            'trainer_id'  => $data['trainer'] ?? null,          // trainer name
            'schedule_id' => $data['schedule_id'] ?? null,      // class ref
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
        ];
        if ($paymentReady) {
            $payload += [
                'section_id'     => $plan['section_id'],
                'website_id'     => $plan['website_id'],
                'plan_amount'    => $plan['found'] ? $plan['amount'] : null,
                'currency'       => $plan['currency'],
                'payment_status' => CoachPricingEnquiry::PAY_UNPAID,
            ];
        }

        $isRetry = (bool) $enquiry;
        if ($enquiry) {
            $enquiry->update($payload);
        } else {
            $enquiry = CoachPricingEnquiry::create($payload);
        }

        // Audit trail (self-guarding; guest actor resolves to system).
        \App\Services\ActivityLogger::log(
            $isRetry ? \App\Models\ActivityLog::UPDATED : \App\Models\ActivityLog::CREATED,
            'schedule_booking',
            $enquiry,
            null,
            ['class' => $data['class_name'] ?? '', 'period' => $data['time_period'] ?? '', 'amount' => $plan['amount'] ?? 0, 'payment_status' => $enquiry->payment_status ?? 'unpaid'],
            'Class Schedule booking enquiry #' . $enquiry->id . ($isRetry ? ' resubmitted' : ' created')
        );

        // Priced period → start the gateway (sets payment_status = pending).
        $checkout = null;
        if ($paymentReady && $plan['found']) {
            try {
                $checkout = $pay->startPayment($enquiry);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Schedule booking payment start failed: ' . $e->getMessage());
            }
        }

        // Submit-time emails to coach + student with the resulting payment status.
        // First submission only — a retry/resubmit reuses the enquiry and does not
        // re-email (the paid emails still fire once payment succeeds).
        if (! $isRetry) {
            $this->notifyBookingSubmitted($enquiry->fresh());
        }

        if ($checkout && ($checkout['ok'] ?? false)) {
            return response()->json($checkout);
        }

        return response()->json(['ok' => true, 'mode' => 'lead', 'message' => __('Thank you! Your booking request has been received. We will contact you shortly.')]);
    }

    /**
     * Public endpoint for the Trainer Detail "Book Personal Class Session" popup
     * (2026-07-15, Phase 2). A guest picks a trainer session-package and pays.
     *
     * Reuses the SAME pipeline as pricing/schedule bookings — coach_pricing_enquiries
     * (discriminated by enquiry_type = trainer_personal_session), the server-side
     * amount resolver, startPayment (Razorpay via the coach gateway), the FE verify
     * + HMAC webhook, and the branded emails. NEVER trusts the posted price; the
     * package is double-tenant-gated (coach + trainer). Retries reuse a recent
     * unpaid enquiry for the same visitor+package instead of creating duplicates.
     */
    public function storeTrainerBooking(Request $request, ?PricingPaymentService $pay = null)
    {
        $pay ??= app(PricingPaymentService::class);

        $data = $request->validate([
            'coach_id'      => 'nullable|integer',
            'section_id'    => 'nullable|integer',   // website-builder section path (self-contained)
            'trainer_id'    => 'nullable|integer',   // coach-panel entity path
            'package_id'    => 'required|integer',   // entity: package id · section: package index
            'source_page'   => 'nullable|string|max:190',
            'source_button' => 'nullable|string|max:120',
            'name'          => 'required|string|max:150',
            'email'         => 'required|email|max:190',
            'mobile'        => ['required', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{7,40}$/', 'regex:/(?:.*\d){7,}/'],
            // 2026-07-15 (Phase 5) — fuller "Book Personal Class Session" form.
            'plan_type'     => 'required|string|max:60',
            'course_type'   => 'required|string|max:60',
            'gender'        => 'required|string|max:20',
            'height'        => 'required|string|max:20',
            'weight'        => 'required|string|max:20',
            'reason'        => 'required|string|max:60',
            'problem'       => 'nullable|string|max:1000',
        ]);

        $sectionId = (int) ($data['section_id'] ?? 0);
        $trainerId = (int) ($data['trainer_id'] ?? 0);
        $isSection = $sectionId > 0;
        if (! $isSection && $trainerId <= 0) {
            return response()->json(['ok' => false, 'message' => __('Invalid booking request. Please reload and try again.')], 422);
        }

        $coachId = $this->resolveCoachId((int) ($data['coach_id'] ?? 0));
        if ($coachId <= 0) {
            return response()->json(['ok' => false, 'message' => __('Could not identify the coach. Please reload and try again.')], 422);
        }

        // Server-authoritative amount — re-read from the trusted source (section
        // content for the builder path, or the trainer package for the entity
        // path), tenant-gated. The posted price is NEVER trusted.
        $paymentReady = $this->paymentColumnsReady();
        $trainerRefId = null; $packageRefId = null; $sectionRefId = null;
        $res = ['found' => false, 'amount' => 0.0, 'currency' => 'INR', 'website_id' => null];

        if ($isSection) {
            if ($paymentReady) {
                try {
                    $res = $pay->resolveTrainerSectionAmount($coachId, $sectionId, (int) $data['package_id']);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Trainer section booking: amount resolve failed: ' . $e->getMessage());
                }
            }
            // Section must resolve to THIS coach (tenant gate inside the resolver).
            if ($paymentReady && empty($res['section_id'])) {
                return response()->json(['ok' => false, 'message' => __('This booking form is not available. Please reload and try again.')], 422);
            }
            $trainerName  = $res['trainer_name'] ?? __('Trainer');
            $pkgLabel     = $res['package_label'] ?? null;
            $sectionRefId = $res['section_id'] ?? $sectionId;
        } else {
            $entity = ['found' => false, 'amount' => 0.0, 'currency' => 'INR', 'website_id' => null, 'trainer' => null, 'package' => null];
            if ($paymentReady) {
                try {
                    $entity = $pay->resolveTrainerPackageAmount($coachId, $trainerId, (int) $data['package_id']);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Trainer booking: amount resolve failed: ' . $e->getMessage());
                }
            }
            $trainer = $entity['trainer'] ?? \App\Models\CoachTrainer::forCoach($coachId)->active()->find($trainerId);
            if (! $trainer) {
                return response()->json(['ok' => false, 'message' => __('This trainer is not available. Please reload and try again.')], 422);
            }
            $package      = $entity['package'] ?? null;
            $trainerName  = $trainer->name;
            $pkgLabel     = $package ? $package->label() : null;
            $trainerRefId = (int) $trainer->id;
            $packageRefId = $package ? (int) $package->id : null;
            $res          = ['found' => $entity['found'], 'amount' => $entity['amount'], 'currency' => $entity['currency'], 'website_id' => $entity['website_id']];
        }

        $details = array_filter([
            'trainer'             => $trainerName,
            'package'             => $pkgLabel,
            'plan_type'           => $data['plan_type'] ?? null,
            'height'              => $data['height'] ?? null,
            'weight'              => $data['weight'] ?? null,
            'problem_description' => $data['problem'] ?? null,
            'section_id'          => $sectionRefId,
            'package_index'       => $isSection ? (int) $data['package_id'] : null,
            'domain'              => $request->getHost(),
        ], fn ($v) => $v !== null && $v !== '');

        // Retry without duplicating: reuse a recent unpaid trainer enquiry from the
        // same visitor for the same trainer + package (keyed on the readable
        // snapshot so it works for BOTH the section and the entity path).
        $enquiry = null;
        if ($paymentReady) {
            $enquiry = CoachPricingEnquiry::forCoach($coachId)
                ->where('enquiry_type', CoachPricingEnquiry::TYPE_TRAINER_SESSION)
                ->where('email', $data['email'])->where('mobile', $data['mobile'])
                ->where('category', $trainerName)
                ->where('time_period', $pkgLabel)
                ->whereIn('payment_status', [CoachPricingEnquiry::PAY_UNPAID, CoachPricingEnquiry::PAY_PENDING, CoachPricingEnquiry::PAY_FAILED, CoachPricingEnquiry::PAY_CANCELLED])
                ->where('created_at', '>=', now()->subMinutes(30))
                ->latest('id')->first();
        }

        $payload = [
            'coach_id'      => $coachId,
            'category'      => $trainerName,          // readable snapshot: trainer
            'course_type'   => $data['course_type'] ?? null,
            'time_period'   => $pkgLabel,             // readable snapshot: package
            'gender'        => $data['gender'] ?? null,
            'reason'        => $data['reason'] ?? null,
            'price'         => $res['found'] ? (string) $res['amount'] : null,
            'name'          => $data['name'],
            'email'         => $data['email'],
            'mobile'        => $data['mobile'],
            'details'       => $details ?: null,
            'status'        => CoachPricingEnquiry::STATUS_NEW,
            'source_page'   => $data['source_page'] ?? ('Trainer · ' . $trainerName),
            'source_button' => $data['source_button'] ?? __('Book Personal Class Session'),
            'trainer_id'    => $trainerName,          // free-text name column (kept consistent with schedule bookings)
            'ip_address'    => $request->ip(),
            'user_agent'    => substr((string) $request->userAgent(), 0, 255),
        ];
        if ($paymentReady) {
            $payload += [
                'enquiry_type'       => CoachPricingEnquiry::TYPE_TRAINER_SESSION,
                'trainer_ref_id'     => $trainerRefId,
                'trainer_package_id' => $packageRefId,
                'website_id'         => $res['website_id'],
                'plan_amount'        => $res['found'] ? $res['amount'] : null,
                'currency'           => $res['currency'],
                'payment_status'     => CoachPricingEnquiry::PAY_UNPAID,
            ];
        }

        $isRetry = (bool) $enquiry;
        if ($enquiry) {
            $enquiry->update($payload);
        } else {
            $enquiry = CoachPricingEnquiry::create($payload);
        }

        \App\Services\ActivityLogger::log(
            $isRetry ? \App\Models\ActivityLog::UPDATED : \App\Models\ActivityLog::CREATED,
            'trainer_booking',
            $enquiry,
            null,
            ['trainer' => $trainerName, 'package' => $pkgLabel, 'amount' => $res['amount'] ?? 0, 'payment_status' => $enquiry->payment_status ?? 'unpaid'],
            'Trainer session booking enquiry #' . $enquiry->id . ($isRetry ? ' resubmitted' : ' created')
        );

        // Priced package → start the gateway (discriminated in the webhook).
        $checkout = null;
        if ($paymentReady && $res['found']) {
            try {
                $checkout = $pay->startPayment($enquiry, CoachPricingEnquiry::TYPE_TRAINER_SESSION);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Trainer booking payment start failed: ' . $e->getMessage());
            }
        }

        if (! $isRetry) {
            $this->notifyBookingSubmitted($enquiry->fresh());
        }

        if ($checkout && ($checkout['ok'] ?? false)) {
            return response()->json($checkout);
        }

        return response()->json(['ok' => true, 'mode' => 'lead', 'message' => __('Thank you! Your booking request has been received. We will contact you shortly.')]);
    }

    /** Coach + student "booking received" emails (with payment status). Best-effort. */
    private function notifyBookingSubmitted(CoachPricingEnquiry $enquiry): void
    {
        try {
            $coach = \App\Models\User::find($enquiry->coach_id);
            if ($coach) {
                $coach->notify(\App\Notifications\BookingEnquiryToCoach::fromEnquiry($enquiry));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Booking submit coach-notify failed: ' . $e->getMessage());
        }
        try {
            if ($enquiry->email && filter_var($enquiry->email, FILTER_VALIDATE_EMAIL)) {
                \Illuminate\Support\Facades\Notification::route('mail', $enquiry->email)
                    ->notify(\App\Notifications\BookingEnquiryToStudent::fromEnquiry($enquiry, $this->coachBrandName((int) $enquiry->coach_id)));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Booking submit student-notify failed: ' . $e->getMessage());
        }
    }

    private function coachBrandName(int $coachId): string
    {
        try {
            $n = app(\App\Services\BrandResolver::class)->forCoach($coachId)->name;
            if ($n) {
                return (string) $n;
            }
        } catch (\Throwable $e) {
        }
        return (string) (optional(\App\Models\User::find($coachId))->name ?: config('app.name'));
    }

    /**
     * Public endpoint for the REUSABLE Booking Enquiry modal — opened by the
     * Class Schedule "Book Now" button, a Trainer "Book Personal Classes" button,
     * and any future CTA. Stores a lead into the SAME coach_pricing_enquiries
     * table (so every lead surfaces in Coach Panel → Pricing Enquiries), scoped
     * to the host's coach (forge-safe), with contextual metadata so the coach
     * knows where the lead came from.
     */
    public function storeBooking(Request $request)
    {
        $data = $request->validate([
            'coach_id'      => 'nullable|integer',
            'name'          => 'required|string|max:150',
            'email'         => 'required|email|max:190',
            // phone: 7–20 chars of digits and the usual separators, ≥7 digits.
            'mobile'        => ['required', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{7,40}$/', 'regex:/(?:.*\d){7,}/'],
            'message'       => 'nullable|string|max:1000',
            // contextual (carried by the trigger button)
            'class_name'    => 'nullable|string|max:190',
            'class_time'    => 'nullable|string|max:120',
            'trainer'       => 'nullable|string|max:150',
            'source_page'   => 'nullable|string|max:190',
            'source_button' => 'nullable|string|max:120',
            'trainer_id'    => 'nullable|string|max:150',
            'schedule_id'   => 'nullable|string|max:150',
        ], [
            'mobile.regex' => __('Please enter a valid phone number.'),
        ]);

        $coachId = $this->resolveCoachId((int) ($data['coach_id'] ?? 0));
        if ($coachId <= 0) {
            return response()->json(['ok' => false, 'message' => __('Could not identify the coach. Please reload and try again.')], 422);
        }

        // Duplicate-submission guard: an identical lead (same coach, email, phone
        // and source) within the last 2 minutes is treated as a double-click /
        // retry — we ack success without creating a second row.
        $recent = CoachPricingEnquiry::forCoach($coachId)
            ->where('email', $data['email'])
            ->where('mobile', $data['mobile'])
            ->where('source_button', $data['source_button'] ?? null)
            ->where('schedule_id', $data['schedule_id'] ?? null)
            ->where('created_at', '>=', now()->subSeconds(120))
            ->exists();

        if ($recent) {
            return response()->json(['ok' => true, 'message' => __('Thank you! Your booking enquiry has been submitted successfully. The coach/team will contact you shortly.')]);
        }

        $details = array_filter([
            'message' => $data['message'] ?? null,
            'trainer' => $data['trainer'] ?? null,
            // White-label provenance: which coach website (host) the lead came
            // from. coach_id is the canonical tenant link; the host is captured
            // for the coach's own reference / multi-domain setups.
            'domain'  => $request->getHost(),
        ], fn ($v) => $v !== null && $v !== '');

        CoachPricingEnquiry::create([
            'coach_id'      => $coachId,
            // Surface the context in the existing panel columns too.
            'category'      => $data['source_page'] ?? __('Booking enquiry'),
            'time_period'   => $data['class_name'] ?? null,
            'time_slot'     => $data['class_time'] ?? null,
            'reason'        => $data['trainer'] ?? null,
            'name'          => $data['name'],
            'email'         => $data['email'],
            'mobile'        => $data['mobile'],
            'details'       => $details ?: null,
            'status'        => CoachPricingEnquiry::STATUS_NEW,
            'source_page'   => $data['source_page'] ?? null,
            'source_button' => $data['source_button'] ?? null,
            'trainer_id'    => $data['trainer_id'] ?? null,
            'schedule_id'   => $data['schedule_id'] ?? null,
            'ip_address'    => $request->ip(),
            'user_agent'    => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['ok' => true, 'message' => __('Thank you! Your booking enquiry has been submitted successfully. The coach/team will contact you shortly.')]);
    }

    /**
     * Resolve the owning coach: host first (custom domain / subdomain), then the
     * posted id but ONLY if it's a real instructor (tenant-safe fallback).
     */
    private function resolveCoachId(int $postedId): int
    {
        $stamped = (int) request()->attributes->get('resolved_coach_id');
        if ($stamped > 0) {
            return $stamped;
        }

        $host = request()->getHost();
        $byDomain = (int) (CoachDomain::coachIdForHost($host) ?? 0);
        if ($byDomain > 0) {
            return $byDomain;
        }

        $coachDomain = (string) config('app.coach_domain');
        if ($coachDomain && str_ends_with($host, '.' . $coachDomain)) {
            $sub  = substr($host, 0, -1 * (strlen($coachDomain) + 1));
            $site = CoachLandingPage::where('slug', $sub)->orWhere('subdomain', $host)->first();
            if ($site) {
                return (int) $site->added_by;
            }
        }

        // F26 (audit 2026-06-26) — trust the posted coach id ONLY when it matches
        // the tenant stamped on the session by TenantContext (the visitor is
        // actually on this coach's site). On the bare platform host there is no
        // such stamp, so a crafted coach_id can no longer poison another coach's
        // CRM. The id must still be a genuine instructor.
        $sessionCoachId = 0;
        try { $sessionCoachId = (int) (request()->session()->get('tenant_coach_id') ?? 0); } catch (\Throwable $e) {}
        if ($postedId > 0 && $postedId === $sessionCoachId
            && User::where('id', $postedId)->where('role', 'instructor')->exists()) {
            return $postedId;
        }
        return 0;
    }
}
