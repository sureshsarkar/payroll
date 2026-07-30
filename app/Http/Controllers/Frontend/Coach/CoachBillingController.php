<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Services\CoachBillingService;

/**
 * Coach-facing, READ-ONLY "My Plan & Billing" (2026-06-24, Phase 4). The coach
 * can see their active plan + the commission/capacity/settlement it dictates
 * and their revenue split — but can NEVER edit any pricing/payment config (no
 * edit routes exist; all of that is Super-Admin-only).
 */
class CoachBillingController extends Controller
{
    private function coachId(): int
    {
        return userAuth()->role === 'instructor' ? (int) userAuth()->id : (int) userAuth()->coach_id;
    }

    public function index()
    {
        $summary = app(CoachBillingService::class)->summary($this->coachId());

        return view('frontend.instructor-dashboard.my-plan.index', compact('summary'));
    }
}
