<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachTrialEnquiry;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Super-Admin view of Trial Session enquiries + payments across ALL coaches
 * (2026-07-03). Read-only oversight with filters by coach, date, status, plan
 * type, course type and payment status.
 */
class TrialSessionAdminController extends Controller
{
    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('trial-session.view');

        $q = CoachTrialEnquiry::query()
            ->with(['coach:id,name,email', 'payment', 'student:id,name']);

        // Filters.
        if ($coachId = $request->get('coach_id')) {
            $q->where('coach_id', $coachId);
        }
        if ($status = $request->get('status')) {
            if (in_array($status, ['pending', 'contacted', 'closed', 'cancelled'], true)) {
                $q->where('status', $status);
            }
        }
        if ($pay = $request->get('payment_status')) {
            if (in_array($pay, ['unpaid', 'paid', 'failed', 'free'], true)) {
                $q->where('payment_status', $pay);
            }
        }
        if ($plan = $request->get('plan_type')) {
            if (in_array($plan, ['online', 'offline'], true)) {
                $q->where('plan_type', $plan);
            }
        }
        if ($course = $request->get('course_type')) {
            if (in_array($course, ['individual', 'couple'], true)) {
                $q->where('course_type', $course);
            }
        }
        if ($keyword = trim((string) $request->get('keyword', ''))) {
            $q->where(function ($w) use ($keyword) {
                $w->where('name', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%")
                  ->orWhere('mobile', 'like', "%{$keyword}%");
            });
        }
        if (($from = $request->get('from')) && strtotime($from)) {
            $q->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($from)));
        }
        if (($to = $request->get('to')) && strtotime($to)) {
            $q->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($to)));
        }

        // Headline totals honour the active filters — clone BEFORE paginate()
        // adds its limit/offset to the builder.
        $totals = [
            'all'     => (clone $q)->count(),
            'paid'    => (clone $q)->where('payment_status', 'paid')->count(),
            'revenue' => (float) (clone $q)->where('payment_status', 'paid')->sum('price'),
        ];

        $enquiries = $q->orderByDesc('id')->paginate(25)->withQueryString();

        $coaches = User::where('role', 'instructor')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.trial-sessions.index', [
            'enquiries' => $enquiries,
            'coaches'   => $coaches,
            'totals'    => $totals,
            'title'     => __('Trial Sessions'),
        ]);
    }
}
