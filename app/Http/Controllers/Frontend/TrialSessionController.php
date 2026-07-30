<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CoachDomain;
use App\Models\CoachLandingPage;
use App\Models\CoachTrialSetting;
use App\Models\CoachTrialSlot;
use App\Models\User;
use App\Services\TrialSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public (guest) endpoint behind the "Book Your Trial Session" popup. Resolves
 * the owning coach from the request host (tenant-safe), validates + sanitises
 * every field server-side, and hands off to TrialSessionService. Rate-limited +
 * CSRF-protected at the route layer.
 */
class TrialSessionController extends Controller
{
    public function __construct(private TrialSessionService $service) {}

    public function submit(Request $request): JsonResponse
    {
        $coachId = $this->resolveCoachId((int) $request->input('coach_id', 0));
        if ($coachId <= 0) {
            return response()->json(['ok' => false, 'message' => __('We could not identify this site. Please refresh and try again.')], 422);
        }

        $settings = CoachTrialSetting::where('coach_id', $coachId)->first();
        if (! $settings || ! $settings->is_enabled) {
            return response()->json(['ok' => false, 'message' => __('Trial bookings are not available right now.')], 403);
        }

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:150'],
            'email'               => ['required', 'email', 'max:190'],
            'mobile'              => ['required', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{7,40}$/', 'regex:/(?:.*\d){7,}/'],
            'plan_type'           => ['required', Rule::in(['online', 'offline'])],
            'course_type'         => ['required', Rule::in(['individual', 'couple'])],
            'slot_id'             => ['required', 'integer'],
            'gender'              => ['required', Rule::in(['male', 'female'])],
            'height'              => ['nullable', 'numeric', 'min:0', 'max:999'],
            'weight'              => ['nullable', 'numeric', 'min:0', 'max:999'],
            'reason'              => ['required', Rule::in(['fitness', 'problem'])],
            'problem_description' => ['nullable', 'string', 'max:1000'],
            'source_page'         => ['nullable', 'string', 'max:190'],
        ]);

        // One free trial per student per coach: if this email already completed a
        // trial (paid or free) for THIS coach, block a repeat and guide them.
        if (\App\Models\CoachTrialEnquiry::trialAlreadyUsed($coachId, $data['email'])) {
            return response()->json([
                'ok'      => false,
                'code'    => 'trial_used',
                'message' => __('You have already used the free trial for this coach. Please contact the coach or purchase a paid plan to continue.'),
            ], 409);
        }

        // The slot must belong to THIS coach and be active — snapshot its label
        // server-side (never trust the client-sent time_slot text).
        $slot = CoachTrialSlot::forCoach($coachId)->active()->find($data['slot_id']);
        if (! $slot) {
            return response()->json(['ok' => false, 'errors' => ['slot_id' => [__('Please choose a valid time slot.')]]], 422);
        }
        $data['time_slot'] = $slot->label;

        $result = $this->service->book($coachId, $settings, $data, $request);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function verify(Request $request): JsonResponse
    {
        $in = $request->validate([
            'payment_id'          => ['required', 'integer'],
            'razorpay_payment_id' => ['required', 'string', 'max:190'],
            'razorpay_order_id'   => ['required', 'string', 'max:190'],
            'razorpay_signature'  => ['required', 'string', 'max:255'],
        ]);

        $result = $this->service->verifyRazorpay($in);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * Resolve the owning coach: host first (custom domain / subdomain), then the
     * posted id but ONLY when it matches the session tenant and is a real
     * instructor. Mirrors PricingEnquiryController::resolveCoachId (F26 audit).
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

        $sessionCoachId = 0;
        try { $sessionCoachId = (int) (request()->session()->get('tenant_coach_id') ?? 0); } catch (\Throwable $e) {}
        if ($postedId > 0 && $postedId === $sessionCoachId
            && User::where('id', $postedId)->where('role', 'instructor')->exists()) {
            return $postedId;
        }
        return 0;
    }
}
