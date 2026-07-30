<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseChapterLesson;
use App\Models\CourseLiveClass;
use App\Models\InstantMeeting;
use Illuminate\Http\Request;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;

class StudentLiveClassController extends Controller
{
    public function index()
    {
        $metadta['title'] = 'Live Classes';
        $userId = userAuth()->id;

        // 2026-05-20 — per-batch student gating.
        //
        // A live class is visible to the student iff the student has an
        // active enrollment in its course AND (one of):
        //   - enrollment.batch_id IS NULL   (legacy enrollment — see all
        //     course classes, preserving pre-2026-05-18 behavior)
        //   - course_live_classes.batch_id IS NULL  (course-wide class
        //     that targets all batches)
        //   - the two batch ids match
        //
        // The NULL-tolerant predicate is the only thing keeping legacy
        // enrollments from going dark after this change ships.
        // 2026-06-09 — TENANT SCOPE: on a coach domain (resolved_coach_id),
        // restrict the student's enrollments to THIS coach's courses, so the
        // live-class list shows only this coach's classes (never another
        // coach's). On the platform host there is no stamp → unchanged (all).
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');

        // 2026-06-16 — shared scope so the list + the sidebar count always agree.
        $liveClasses = CourseLiveClass::visibleToStudent($userId, $tenantCoachId)
            ->with([
                'lesson:id,title,course_id',
                'lesson.course:id,title,slug',
            ])
            ->orderBy('start_time', 'desc')
            ->paginate(10);

        // 2026-07-03 — the student's active 1:1 instant meetings appear in the
        // same unified list. Strict student_id match = private to this student;
        // tenant-scoped so Coach A's meeting never shows on Coach B's site.
        $instantMeetings = InstantMeeting::visibleToStudent($userId, $tenantCoachId)
            ->with('coach:id,name')
            ->get();

        return view('frontend.student-dashboard.live-classes.index', compact('liveClasses', 'instantMeetings'));
    }

    public function show(string $id)
    {
        $order = $this->ownedOrder($id);

        return view('frontend.student-dashboard.live-classes.show', compact('order'));
    }

    public function printInvoice(Request $request, $id)
    {
        $order = $this->ownedOrder($id);

        return view('frontend.student-dashboard.live-classes.invoice', compact('order'));
    }

    /**
     * 2026-06-25 (tenant-isolation audit) — fetch an order owned by the current
     * student, and on a coach domain only if it contains THIS coach's course, so
     * coach B's domain can't open an order that is really coach A's. 404 otherwise.
     * Mirrors StudentOrderController::ownedOrder().
     */
    private function ownedOrder(string $id): Order
    {
        $tenantCoachId = (int) request()->attributes->get('resolved_coach_id');
        return Order::where('id', $id)
            ->where('buyer_id', userAuth()->id)
            ->when($tenantCoachId > 0, fn ($q) =>
                $q->whereHas('orderItems.course', fn ($c) => $c->where('instructor_id', $tenantCoachId)))
            ->firstOrFail();
    }
}
