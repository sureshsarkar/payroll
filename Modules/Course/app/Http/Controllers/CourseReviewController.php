<?php

namespace Modules\Course\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CourseReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Review;

class CourseReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * FT-IDOR-4 fix (2026-05-27) — no permission gate.
     * Routes here sit behind `auth:admin`, so any logged-in admin /
     * sub-admin (even one whose role only includes `dashboard.view`)
     * could reach approve/delete on user-generated reviews. Reviews
     * are part of the course-management surface, so we use the same
     * `course.management` permission key that CourseController +
     * CourseContentController + CourseDeleteRequestController already
     * gate on (see migration 2026_05_18_120000_seed_additional_admin_roles
     * — the "Course Manager" role gets `course.view` + `course.create`
     * but NOT `course.management`, so a content-only sub-admin loses
     * write access here — correct outcome).
     */
    public function index(Request $request)
    {
        checkAdminHasPermissionAndThrowException('course.management');

        $query = CourseReview::query();
        $query->with(['course:title,id']);
        $query->whereHas('course')->whereHas('user');
        $query->when($request->keyword, fn ($q) => $q->whereHas('course', fn ($q) => $q->where('title', 'like', "%{$request->keyword}%")));

        $query->when($request->status, fn ($q) => $q->where('status', $request->status));
        $orderBy = $request->order_by == 1 ? 'asc' : 'desc';
        $reviews = $request->get('par-page') == 'all' ?
            $query->orderBy('id', $orderBy)->get() :
            $query->orderBy('id', $orderBy)->paginate($request->get('par-page') ?? null)->withQueryString();
        return view('course::course-review.index', compact('reviews'));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        checkAdminHasPermissionAndThrowException('course.management');

        $review = CourseReview::findOrFail($id);
        return view('course::course-review.show', compact('review'));
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('course.management');

        // FT-IDOR-4 (b) fix (2026-05-27) — was `status => required`
        // (any string accepted). The column is a boolean and the UI
        // submits "0" or "1" only. Without the `in:0,1` whitelist, an
        // attacker could POST `status=DROP TABLE` (or any other
        // string) which then gets cast to boolean true at save time —
        // permanently approving the review even though the form
        // wouldn't have sent that value.
        $request->validate([
            'status' => 'required|in:0,1',
        ]);
        $review = CourseReview::findOrFail($id);
        $review->status = $request->status;
        $review->save();
        return redirect()->route('admin.course-review.index')->with(['alert-type' => 'success', 'messege' => __('Updated successfully')]);
    }
    public function destroy($id)
    {
        checkAdminHasPermissionAndThrowException('course.management');

        $review = CourseReview::findOrFail($id);
        $review->delete();
        return redirect()->route('admin.course-review.index')->with(['alert-type' => 'success', 'messege' => __('Deleted successfully')]);
    }
}
