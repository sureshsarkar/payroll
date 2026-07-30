<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachPricingEnquiry;
use Illuminate\Http\Request;

/**
 * Super-Admin read-only view of every coach's Pricing & Plans / Classes &
 * Schedules booking enquiry (2026-07-14). Cross-tenant by design (admin sees
 * all coaches); the coach panel remains strictly coach-scoped.
 */
class BookingEnquiryController extends Controller
{
    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('coach-landing-page.view');

        $search  = trim((string) $request->get('search'));
        $payment = $request->get('payment');
        $coach   = $request->get('coach');
        $type    = $request->get('type');   // 2026-07-15 — enquiry_type filter (e.g. trainer bookings)
        $from    = $request->get('from');
        $to      = $request->get('to');

        $enquiries = CoachPricingEnquiry::query()
            ->with(['coach:id,name,email', 'payments'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('mobile', 'like', "%{$search}%")
                      ->orWhere('trainer_id', 'like', "%{$search}%")
                      ->orWhere('schedule_id', 'like', "%{$search}%");
                });
            })
            ->when(in_array($payment, ['unpaid', 'pending', 'paid', 'failed', 'cancelled'], true), fn ($q) => $q->where('payment_status', $payment))
            ->when($coach, fn ($q) => $q->where('coach_id', (int) $coach))
            ->when($type === CoachPricingEnquiry::TYPE_TRAINER_SESSION, fn ($q) => $q->where('enquiry_type', CoachPricingEnquiry::TYPE_TRAINER_SESSION))
            ->when($type === 'other', fn ($q) => $q->where(fn ($w) => $w->whereNull('enquiry_type')->orWhere('enquiry_type', '!=', CoachPricingEnquiry::TYPE_TRAINER_SESSION)))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        // Coaches that actually have enquiries — bounded dropdown.
        $coaches = \App\Models\User::whereIn('id', CoachPricingEnquiry::query()->distinct()->pluck('coach_id'))
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.booking-enquiries.index', compact('enquiries', 'coaches', 'search', 'payment', 'coach', 'type', 'from', 'to'));
    }
}
