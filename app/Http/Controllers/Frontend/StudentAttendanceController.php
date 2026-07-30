<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CourseLiveClass;
use App\Models\LiveClassAttendance;
use Illuminate\Http\Request;

/**
 * Audit 2026-05-18 Req 1 — student-facing attendance overview.
 *
 * Lets a student see every live-class attendance row tied to their own
 * user_id with the verification state (Verified / Partial / In Progress
 * / Absent placeholder — but absent rows aren't actually in the table,
 * so we surface only the rows the system has on file).
 *
 * No admin access needed; reuses `auth` web guard. The query is hard-
 * scoped to userAuth()->id so an attacker can't pivot via path params.
 */
class StudentAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = userAuth();
        abort_unless($user, 401);

        // 2026-06-09 — TENANT SCOPE: on a coach domain, restrict attendance to
        // this coach's live classes only. coachClassIds=null on the platform
        // (no filter). Computed from course_live_classes.course_id, which the
        // queries below already rely on.
        $tenantCoachId = (int) $request->attributes->get('resolved_coach_id');
        $coachClassIds = null;
        if ($tenantCoachId > 0) {
            $coachClassIds = CourseLiveClass::whereIn('course_id',
                \App\Models\Course::where('instructor_id', $tenantCoachId)->select('id'))
                ->pluck('id')->all();
        }

        // Pull this student's attendance rows with the class + course join.
        // Most recent first.
        $rows = LiveClassAttendance::query()
            ->select(
                'live_class_attendances.id',
                'live_class_attendances.course_live_class_id',
                'live_class_attendances.joined_at',
                'live_class_attendances.left_at',
                'live_class_attendances.duration_seconds',
                'live_class_attendances.attendance_verified',
                'live_class_attendances.verified_at',
                'live_class_attendances.is_manual',
                'live_class_attendances.role',
                'course_live_classes.start_time',
                'course_live_classes.ended_at',
                'course_live_classes.expected_duration_minutes',
                'course_live_classes.course_id',
                'courses.title as course_title',
                'courses.slug  as course_slug',
                'course_chapter_lessons.title as lesson_title',
            )
            ->join('course_live_classes', 'course_live_classes.id', '=', 'live_class_attendances.course_live_class_id')
            ->leftJoin('courses', 'courses.id', '=', 'course_live_classes.course_id')
            ->leftJoin('course_chapter_lessons', 'course_chapter_lessons.id', '=', 'course_live_classes.lesson_id')
            ->where('live_class_attendances.user_id', $user->id)
            ->when($coachClassIds !== null, fn ($q) => $q->whereIn('live_class_attendances.course_live_class_id', $coachClassIds ?: [0]))
            ->orderByDesc('live_class_attendances.joined_at')
            ->paginate(20)
            ->withQueryString();

        // Compute headline counts (across all-time for this student)
        $allRows = LiveClassAttendance::where('user_id', $user->id)
            ->when($coachClassIds !== null, fn ($q) => $q->whereIn('course_live_class_id', $coachClassIds ?: [0]))
            ->get(['attendance_verified', 'duration_seconds']);
        $totalSessions = $allRows->count();
        $verifiedCount = $allRows->where('attendance_verified', 1)->count();
        $totalMinutes  = (int) round($allRows->sum('duration_seconds') / 60);

        return view('frontend.student-dashboard.attendance.index', [
            'rows'          => $rows,
            'totalSessions' => $totalSessions,
            'verifiedCount' => $verifiedCount,
            'totalMinutes'  => $totalMinutes,
        ]);
    }
}
