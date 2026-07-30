<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CourseReview;

class StudentReviewController extends Controller
{
    // 2026-06-10 — TENANT SCOPE: on a coach custom domain, act on only the
    // reviews this student left on THIS coach's courses (instructor_id). On the
    // platform domain resolved_coach_id is 0 → the when() closure is a no-op.
    private function coachScope(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        return $q->when($tenantCoachId > 0, fn ($x) =>
            $x->whereHas('course', fn ($c) => $c->where('instructor_id', $tenantCoachId)));
    }

    function index() {
        $reviews = $this->coachScope(CourseReview::with('course:title,id')->where('user_id', auth('web')->user()->id))
            ->orderBy('id', 'desc')->paginate(10);
        return view('frontend.student-dashboard.review.index', compact('reviews'));
    }

    function show(string $id) {
       $review = $this->coachScope(CourseReview::with('course:id,title')->where('id', $id)->where('user_id', auth('web')->user()->id))->firstOrFail();
       return view('frontend.student-dashboard.review.show', compact('review'));
    }

    function destroy(string $id) {
        $this->coachScope(CourseReview::where('id', $id)->where('user_id', auth('web')->user()->id))->delete();
        return response()->json(['status' => 'success', 'message' => __('Review deleted successfully')]);
    }
}
