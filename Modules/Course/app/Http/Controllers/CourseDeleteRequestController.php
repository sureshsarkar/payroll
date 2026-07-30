<?php

namespace Modules\Course\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Course\app\Models\CourseDeleteRequest;

class CourseDeleteRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        checkAdminHasPermissionAndThrowException('course.management');
        // Paginated to avoid loading the entire requests table when the queue is large.
        $messages = CourseDeleteRequest::orderByDesc('id')->paginate(20);
        return view('course::course-delete-request.index', compact('messages'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        checkAdminHasPermissionAndThrowException('course.management');

        $request->validate([
            'action' => 'required|in:inactive,active',
        ]);

        // Both branches mutate the request status AND the course soft-delete state.
        // Wrap in a transaction so we don't end up with half-applied state if the
        // course delete/restore fails (e.g. FK constraint).
        \DB::transaction(function () use ($request, $id) {
            $message = CourseDeleteRequest::lockForUpdate()->findOrFail($id);
            if ($request->action == 'inactive') {
                $course = Course::findOrFail($message->course_id);
                $course->delete();
                $message->status = 1;
            } else {
                Course::withTrashed()->findOrFail($message->course_id)->restore();
                $message->status = 0;
            }
            $message->save();
        });

        return redirect()->back()->with(['messege' => __('Updated successfully'), 'alert-type' => 'success']);
    }
}
