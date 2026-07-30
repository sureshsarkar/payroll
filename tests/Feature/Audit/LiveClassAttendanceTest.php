<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — live-class attendance log added on 2026-05-07.
 * Hooks the Zoom Component View `connection-change` event to a backend
 * endpoint that records a row per (user, live class, session).
 *
 * Fails loudly if any of the load-bearing parts go missing.
 */
class LiveClassAttendanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_attendance_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasTable('live_class_attendances'),
            'live_class_attendances table is missing — attendance logging is broken'
        );

        foreach ([
            'course_live_class_id',
            'user_id',
            'role',
            'joined_at',
            'left_at',
            'duration_seconds',
            'client_ip',
            'user_agent',
        ] as $col) {
            $this->assertTrue(
                Schema::hasColumn('live_class_attendances', $col),
                "live_class_attendances.$col missing"
            );
        }
    }

    public function test_attendance_routes_are_registered(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());

        $this->assertContains(
            'live-class.attendance',
            $names,
            'POST /live-class/{id}/attendance route missing — launcher attendance pings will 404'
        );
        $this->assertContains(
            'instructor.live-class.attendance',
            $names,
            'GET /instructor/live-classes/{id}/attendance route missing — instructor can\'t see who attended'
        );
        $this->assertContains(
            'instructor.live-classes.live-counts',
            $names,
            'GET /instructor/live-classes/live-counts AJAX endpoint missing — pulse badge poller will 404'
        );
    }

    public function test_index_view_renders_live_attendance_pulse(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/live-classes/index.blade.php')
        );
        $this->assertStringContainsString(
            'live-attendance-pulse',
            $view,
            'Live-attendance pulse badge missing from instructor live-classes index'
        );
        $this->assertStringContainsString(
            'data-live-class-id=',
            $view,
            'Pulse badge has no data-live-class-id — poller can\'t update it'
        );
        $this->assertStringContainsString(
            'instructor.live-classes.live-counts',
            $view,
            'Index view does not call the live-counts polling endpoint'
        );
    }

    public function test_launcher_posts_join_and_leave_events(): void
    {
        $launcher = (string) file_get_contents(
            base_path('resources/views/frontend/student-dashboard/live/zoom.blade.php')
        );

        $this->assertStringContainsString(
            "postAttendance('join')",
            $launcher,
            'Launcher no longer posts a join event on connection-change'
        );
        $this->assertStringContainsString(
            "postAttendance('leave'",
            $launcher,
            'Launcher no longer posts a leave event'
        );
        $this->assertStringContainsString(
            "navigator.sendBeacon",
            $launcher,
            'Launcher no longer uses sendBeacon for pagehide attendance — abrupt tab closes will leave open sessions'
        );
        $this->assertStringContainsString(
            "live-class.attendance",
            $launcher,
            'Launcher does not reference the named attendance route'
        );
    }

    public function test_launcher_shows_end_of_class_wrap_up(): void
    {
        // The wrap-up overlay is what students see after the meeting closes —
        // a "class ended", attendance duration summary, and Mark-Complete
        // CTA wired to the existing learning/make-lesson-complete endpoint.
        // Without it the meeting closes into an empty Zoom chrome and there's
        // no clear next step.
        $launcher = (string) file_get_contents(
            base_path('resources/views/frontend/student-dashboard/live/zoom.blade.php')
        );

        $this->assertStringContainsString(
            'showWrapUp',
            $launcher,
            'Launcher missing showWrapUp() — students see no end-of-class summary'
        );
        $this->assertStringContainsString(
            "wuMarkComplete",
            $launcher,
            'Launcher missing the mark-complete button in the wrap-up'
        );
        $this->assertStringContainsString(
            "student.make-lesson-complete",
            $launcher,
            'Launcher mark-complete CTA does not call the named student.make-lesson-complete route'
        );
    }

    public function test_attendance_controller_enforces_enrollment_or_ownership(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/LiveClassAttendanceController.php')
        );

        $this->assertStringContainsString(
            "abort_unless(\$user, 401)",
            $src,
            'Attendance endpoint missing auth guard'
        );
        $this->assertStringContainsString(
            "Enrollment::where",
            $src,
            'Attendance endpoint missing enrollment check — random users could log attendance'
        );
        // Accept either `$course->instructor_id` or null-safe
        // `$course?->instructor_id` — what matters is that role is
        // decided from the course relation, not from request input.
        $this->assertMatchesRegularExpression(
            '/\$course\??->instructor_id/',
            $src,
            'Attendance endpoint role decision must come from course.instructor_id, not user-supplied input'
        );
    }

    public function test_attendance_csv_export_route_is_registered(): void
    {
        // 2026-05-11 CSV export — instructors download per-lesson
        // attendance for compliance / record-keeping. Without the named
        // route, the Download button on the attendance view 404s and the
        // feature silently disappears under a refactor.
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        $this->assertContains(
            'instructor.live-class.attendance.export',
            $names,
            'GET /instructor/live-classes/{id}/attendance/export route missing — CSV download will 404'
        );
    }

    public function test_attendance_csv_export_controller_enforces_ownership(): void
    {
        // Same IDOR posture as the HTML view: only the course's own
        // instructor sees/exports their attendance. The auth check must
        // live in the controller method itself (not just middleware) so
        // a coach can't read another coach's class by guessing the id.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );

        $this->assertStringContainsString(
            'attendanceExport',
            $src,
            'LiveClassController must define attendanceExport() — CSV download endpoint is missing'
        );
        // The export method must contain an instructor_id ownership check.
        // We look for the abort_unless line specifically inside that method,
        // not just anywhere in the controller (which would also match
        // other unrelated methods).
        $methodStart = strpos($src, 'public function attendanceExport');
        $this->assertNotFalse($methodStart, 'attendanceExport method body not found');
        // Look ahead at most 2000 chars into the method body.
        $methodBody = substr($src, $methodStart, 2000);
        // 2026-05-20 — ownership gate now lives in enforceCanAccessLiveClass($lc)
        // which accepts the owning coach OR an assigned CoachStaff teacher.
        // The intent of the test — "this method body MUST run the gate" —
        // is unchanged; we just match the helper call now.
        $this->assertMatchesRegularExpression(
            '/\$this->enforceCanAccessLiveClass\s*\(\s*\$liveClass\s*\)/',
            $methodBody,
            'attendanceExport missing ownership gate — coaches could read each other\'s data'
        );
    }

    public function test_attendance_csv_export_streams_with_csv_headers(): void
    {
        // The export must (a) stream rather than build the whole sheet
        // in memory (large classes have 1000s of rows) and (b) advertise
        // text/csv with a download disposition. Verifying via static
        // source inspection rather than HTTP because that's the pattern
        // used by the rest of this test file.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        $methodStart = strpos($src, 'public function attendanceExport');
        // Widened from 4000 → 12000 after #5 (late/early columns) grew
        // the method. Keep ample headroom for future growth.
        $methodBody  = substr($src, $methodStart, 12000);

        $this->assertStringContainsString(
            'streamDownload',
            $methodBody,
            'attendanceExport must use streamDownload — non-streamed export OOMs on large classes'
        );
        $this->assertStringContainsString(
            "'Content-Type'        => 'text/csv",
            $methodBody,
            'attendanceExport must set Content-Type: text/csv'
        );
        $this->assertStringContainsString(
            'fputcsv',
            $methodBody,
            'attendanceExport must use fputcsv to encode rows — manual escaping is bug-prone'
        );
        // UTF-8 BOM check — Excel on Windows defaults to the system code
        // page (cp1252) and renders Devanagari / emoji as mojibake without
        // it. The BOM tells Excel "this is UTF-8".
        $this->assertStringContainsString(
            "\\xEF\\xBB\\xBF",
            $methodBody,
            'attendanceExport must prepend a UTF-8 BOM so Excel renders non-ASCII names correctly'
        );
    }

    public function test_attendance_view_has_csv_download_button(): void
    {
        // Discoverability tripwire — the Download button is the only UI
        // entry to the export endpoint. If it's removed by a refactor,
        // the feature still exists but instructors can't find it.
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/live-classes/attendance.blade.php')
        );
        $this->assertStringContainsString(
            'instructor.live-class.attendance.export',
            $view,
            'Attendance view missing the CSV download link — feature is unreachable from the UI'
        );
        $this->assertStringContainsString(
            'Download CSV',
            $view,
            'Attendance view missing the "Download CSV" label on the export button'
        );
    }

    public function test_manual_override_schema_and_route_exist(): void
    {
        // #7 manual override — instructor marks a student present with
        // a typed reason. Backed by three new columns + a route.
        foreach (['is_manual', 'manual_reason', 'marked_by'] as $col) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn('live_class_attendances', $col),
                "live_class_attendances.$col missing — manual override cannot be recorded"
            );
        }
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        $this->assertContains(
            'instructor.live-class.attendance.manual-mark',
            $names,
            'POST manual-mark route missing — "Mark present" button will 404'
        );
    }

    public function test_manual_mark_controller_validates_and_enforces_enrollment(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        $offset = strpos($src, 'function attendanceManualMark');
        $this->assertNotFalse($offset, 'attendanceManualMark method missing');
        // Widened 2500 → 3600 after audit [7] added the batch-scope guard
        // (enrollments.batch_id) ahead of the is_manual/marked_by writes.
        $body = substr($src, $offset, 3600);

        // Three guards must be present:
        //   (1) Reason is REQUIRED and min:3 — prevents empty/junk overrides.
        //   (2) Ownership gate: caller must be the course instructor.
        //   (3) Target user must be ENROLLED in the course — can't
        //       mark random users present.
        $this->assertStringContainsString(
            "'required', 'string', 'min:3', 'max:255'",
            $body,
            'manual-mark must require a meaningful reason (min:3) — empty reasons defeat the audit trail'
        );
        // 2026-05-20 — ownership gate now lives in enforceCanAccessLiveClass($lc)
        // which accepts the owning coach OR an assigned CoachStaff teacher.
        // The intent of the test — "this method body MUST run the gate" —
        // is unchanged; we just match the helper call now.
        $this->assertMatchesRegularExpression(
            '/\$this->enforceCanAccessLiveClass\s*\(\s*\$liveClass\s*\)/',
            $body,
            'manual-mark must run the live-class ownership gate (enforceCanAccessLiveClass)'
        );
        $this->assertStringContainsString(
            'Enrollment::where',
            $body,
            'manual-mark must verify the target user is enrolled in the course'
        );
        // Records the override with provenance — is_manual=true, marked_by=caller.
        $this->assertStringContainsString(
            "'is_manual'            => true",
            $body,
            'manual-mark must flag the row as is_manual=true so the UI can distinguish it'
        );
        $this->assertStringContainsString(
            "'marked_by'            => userAuth()->id",
            $body,
            'manual-mark must record marked_by — audit trail relies on it'
        );
    }

    public function test_attendance_view_shows_late_and_early_columns(): void
    {
        // #5 — late-join / early-leave indicators surface as two new
        // columns in the per-user summary table + two new columns in
        // the CSV export.
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/live-classes/attendance.blade.php')
        );
        $this->assertStringContainsString(
            "{{ __('Late by') }}",
            $view,
            'Per-user summary missing "Late by" column header'
        );
        $this->assertStringContainsString(
            "{{ __('Left early by') }}",
            $view,
            'Per-user summary missing "Left early by" column header'
        );
        // On-time / Stayed badges (the "you were perfectly on time" feedback).
        $this->assertStringContainsString(
            "On time",
            $view,
            'On-time badge missing — students who joined right at start time should be highlighted'
        );
    }

    public function test_csv_export_includes_late_and_early_columns(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        $offset = strpos($src, 'function attendanceExport');
        $body   = substr($src, $offset, 8000);

        // CSV header row includes the two new columns.
        $this->assertStringContainsString(
            "'Late by (min)', 'Left early by (min)', 'Manual override'",
            $body,
            'attendanceExport CSV header missing the late/early/manual columns'
        );
    }

    public function test_recordings_panel_renders_on_attendance_view(): void
    {
        // #9 — Cloud recording link per attendance row.
        // Eager-loaded in attendance() and rendered in the view above
        // the manual-mark section. If the eager-load or the view block
        // gets removed, recordings disappear silently.
        $controllerSrc = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        $this->assertStringContainsString(
            "'recordings:id,course_live_class_id",
            $controllerSrc,
            'attendance() controller must eager-load recordings — view will run N+1 queries otherwise'
        );

        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/live-classes/attendance.blade.php')
        );
        $this->assertStringContainsString(
            "\$liveClass->recordings",
            $view,
            'Attendance view missing recordings panel — Cloud recordings are not surfaced'
        );
        $this->assertStringContainsString(
            'play_url',
            $view,
            'Recordings panel missing the play_url link'
        );
    }

    public function test_student_my_attendance_surfaces_recording_link(): void
    {
        // #9 student side — for any class with a recording, the student
        // sees a "Watch recording" link inline with their attendance
        // row. Most valuable for "Missed" rows (catch-up).
        $controllerSrc = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/LearningController.php')
        );
        $this->assertStringContainsString(
            "'recordings:id,course_live_class_id,play_url",
            $controllerSrc,
            'myAttendance() must eager-load recordings — N+1 otherwise'
        );
        $this->assertStringContainsString(
            "'recording_url'",
            $controllerSrc,
            'myAttendance() must expose recording_url on each row for the view'
        );

        $view = (string) file_get_contents(
            base_path('resources/views/frontend/student-dashboard/learning/my-attendance.blade.php')
        );
        $this->assertStringContainsString(
            '$r->recording_url',
            $view,
            'Student my-attendance view missing the per-class recording link'
        );
    }

    public function test_late_seconds_uses_signed_diff_against_class_start(): void
    {
        // The late/early math must use Carbon's signed diffInSeconds(false)
        // so we can tell "joined before start" (negative diff → 0 late
        // seconds, i.e. on time or early) apart from "joined after start"
        // (positive duration to absorb as late).
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        $offset = strpos($src, 'function attendance(');
        $body   = substr($src, $offset, 5000);

        $this->assertStringContainsString(
            'diffInSeconds($classStart, false)',
            $body,
            'Late calculation must use signed diff so on-time joins do not register as massively late'
        );
        $this->assertStringContainsString(
            'diffInSeconds($classEnd, false)',
            $body,
            'Early-leave calculation must use signed diff so staying past end does not register as early-leave'
        );
    }

    public function test_attendance_view_shows_manual_form_and_manual_badge(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/live-classes/attendance.blade.php')
        );
        $this->assertStringContainsString(
            "instructor.live-class.attendance.manual-mark",
            $view,
            'Manual-mark form action missing from attendance view'
        );
        $this->assertStringContainsString(
            'is_manual',
            $view,
            'Manual rows must be visually distinguished in the session log'
        );
        $this->assertStringContainsString(
            "absentees->isNotEmpty()",
            $view,
            'Manual-mark form must only render when there are absentees to mark (saves UI noise)'
        );
    }
}
