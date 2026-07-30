<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTrialEnquiry;
use App\Models\CoachTrialPayment;
use App\Models\CoachTrialSetting;
use App\Models\CoachTrialSlot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Coach-panel management for the "Book Your Trial Session" popup (2026-07-03).
 *
 * One settings-hub entry covering: popup settings (enable, price, copy, gateway),
 * time-slot CRUD, and read-only Enquiries + Payments lists. Every read and write
 * is scoped to the owning coach (instructor = self, staff = their coach_id) — a
 * coach can never see or touch another coach's trial data.
 */
class CoachTrialSessionController extends Controller
{
    /** Gateways the trial flow can charge on today (v1 = Razorpay). */
    private const ALLOWED_GATEWAYS = ['razorpay'];

    /** Effective coach id — a real coach is their own id; staff act for their coach. */
    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    /* ───────────────────────────── Settings ───────────────────────────── */

    public function index(Request $request)
    {
        $coachId  = $this->coachId();
        $settings = CoachTrialSetting::forCoach($coachId);
        $slots    = CoachTrialSlot::forCoach($coachId)->ordered()->get();

        $counts = [
            'enquiries' => CoachTrialEnquiry::forCoach($coachId)->count(),
            'paid'      => CoachTrialPayment::forCoach($coachId)->where('status', CoachTrialPayment::STATUS_PAID)->count(),
        ];

        return view('frontend.instructor-dashboard.trial-sessions.index', compact('settings', 'slots', 'counts'));
    }

    public function update(Request $request)
    {
        $coachId = $this->coachId();

        $data = $request->validate([
            'is_enabled'        => ['nullable', 'boolean'],
            'title'             => ['nullable', 'string', 'max:150'],
            'subtitle'          => ['nullable', 'string', 'max:255'],
            'success_message'   => ['nullable', 'string', 'max:2000'],
            'price'             => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency_icon'     => ['nullable', 'string', 'max:8'],
            'require_payment'   => ['nullable', 'boolean'],
            'payment_gateway'   => ['nullable', 'string', Rule::in(self::ALLOWED_GATEWAYS)],
            'auto_show'         => ['nullable', 'boolean'],
            'show_delay_seconds'=> ['nullable', 'integer', 'min:0', 'max:60'],
            'show_frequency'    => ['nullable', Rule::in(['session', 'daily', 'once_30d', 'always'])],
        ]);

        $settings = CoachTrialSetting::forCoach($coachId);
        $settings->coach_id           = $coachId;
        $settings->is_enabled         = $request->boolean('is_enabled');
        $settings->title              = $data['title'] ?? null;
        $settings->subtitle           = $data['subtitle'] ?? null;
        $settings->success_message    = $data['success_message'] ?? null;
        $settings->price              = round((float) $data['price'], 2);
        $settings->currency_icon      = $data['currency_icon'] ?: '₹';
        $settings->require_payment    = $request->boolean('require_payment');
        $settings->payment_gateway    = $data['payment_gateway'] ?? 'razorpay';
        $settings->auto_show          = $request->boolean('auto_show');
        $settings->show_delay_seconds = (int) ($data['show_delay_seconds'] ?? 2);
        $settings->show_frequency     = $data['show_frequency'] ?? 'session';
        $settings->save();

        return redirect()->route('instructor.trial-sessions.index')
            ->with(['messege' => __('Trial session settings saved'), 'alert-type' => 'success']);
    }

    /* ─────────────────────────── Time slots CRUD ───────────────────────── */

    public function storeSlot(Request $request)
    {
        $coachId = $this->coachId();
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:190'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        CoachTrialSlot::create([
            'coach_id'   => $coachId,
            'label'      => trim($data['label']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active'  => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with(['messege' => __('Time slot added'), 'alert-type' => 'success']);
    }

    public function updateSlot(Request $request, $id)
    {
        $coachId = $this->coachId();
        $slot = CoachTrialSlot::forCoach($coachId)->findOrFail($id);

        $data = $request->validate([
            'label'      => ['required', 'string', 'max:190'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $slot->update([
            'label'      => trim($data['label']),
            'sort_order' => (int) ($data['sort_order'] ?? $slot->sort_order),
            'is_active'  => $request->boolean('is_active'),
        ]);

        return redirect()->back()->with(['messege' => __('Time slot updated'), 'alert-type' => 'success']);
    }

    public function destroySlot($id)
    {
        $coachId = $this->coachId();
        CoachTrialSlot::forCoach($coachId)->findOrFail($id)->delete();

        return redirect()->back()->with(['messege' => __('Time slot deleted'), 'alert-type' => 'success']);
    }

    /* ─────────────────────── Enquiries + Payments lists ────────────────── */

    public function enquiries(Request $request)
    {
        $coachId = $this->coachId();
        $search  = trim((string) $request->get('search'));
        $status  = (string) $request->get('status');
        $pay     = (string) $request->get('payment_status');

        $enquiries = CoachTrialEnquiry::forCoach($coachId)
            ->with('student:id,name,email,status')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['pending', 'contacted', 'closed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when(in_array($pay, ['unpaid', 'paid', 'failed', 'free'], true), fn ($q) => $q->where('payment_status', $pay))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('frontend.instructor-dashboard.trial-sessions.enquiries', compact('enquiries'));
    }

    public function updateEnquiryStatus(Request $request, $id)
    {
        $coachId = $this->coachId();
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'contacted', 'closed', 'cancelled'])],
        ]);
        $enquiry = CoachTrialEnquiry::forCoach($coachId)->findOrFail($id);
        $enquiry->update(['status' => $data['status']]);

        return redirect()->back()->with(['messege' => __('Enquiry updated'), 'alert-type' => 'success']);
    }

    public function payments(Request $request)
    {
        $coachId = $this->coachId();
        $status  = (string) $request->get('status');

        $payments = CoachTrialPayment::forCoach($coachId)
            ->with('enquiry:id,name,email,mobile')
            ->when(in_array($status, ['pending', 'paid', 'failed', 'refunded'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('frontend.instructor-dashboard.trial-sessions.payments', compact('payments'));
    }
}
