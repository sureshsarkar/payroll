<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — per-course attendance watchlist added 2026-05-11.
 *
 * Surfaces students whose live-class attendance percent falls below the
 * course's `attendance_threshold_percent`. Two surfaces:
 *   - HTML view at GET /instructor/courses/{id}/attendance-watchlist
 *   - CSV  at  GET /instructor/courses/{id}/attendance-watchlist/export
 *
 * Both gated by the existing `findOwnedCourseOrFail()` IDOR helper, which
 * 404s anyone who isn't the course's coach.
 */
class AttendanceWatchlistTest extends TestCase
{
    use DatabaseTransactions;

    public function test_attendance_threshold_column_exists_on_courses(): void
    {
        $this->assertTrue(
            Schema::hasColumn('courses', 'attendance_threshold_percent'),
            'courses.attendance_threshold_percent missing — at-risk flagging cannot read the per-course threshold'
        );
    }

    public function test_watchlist_routes_are_registered(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());

        $this->assertContains(
            'instructor.courses.attendance-watchlist',
            $names,
            'GET watchlist route missing — instructors can\'t reach the at-risk page'
        );
        $this->assertContains(
            'instructor.courses.attendance-watchlist.export',
            $names,
            'GET watchlist CSV export route missing — Download button will 404'
        );
    }

    public function test_more_information_form_collects_threshold(): void
    {
        // Tripwire so a future refactor of the course-edit form doesn't
        // silently drop the threshold input. Without this field, the
        // default of 75% persists forever and instructors lose control.
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/course/more-information.blade.php')
        );

        $this->assertStringContainsString(
            'name="attendance_threshold_percent"',
            $view,
            'Course "More information" form is missing the attendance_threshold_percent input'
        );
        $this->assertMatchesRegularExpression(
            '/min="0"\s+max="100"/',
            $view,
            'attendance_threshold_percent input must clamp to 0–100 client-side (server validates too)'
        );
    }

    public function test_controller_has_watchlist_methods_with_ownership_gate(): void
    {
        // Both controller methods must run through findOwnedCourseOrFail() —
        // that's how we IDOR-gate. If a refactor inlines a direct
        // `Course::find` without the ownership filter, a coach could read
        // another coach's watchlist by guessing the course id.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorCourseController.php')
        );

        $this->assertStringContainsString(
            'public function attendanceWatchlist',
            $src,
            'InstructorCourseController must define attendanceWatchlist() — view endpoint missing'
        );
        $this->assertStringContainsString(
            'public function attendanceWatchlistExport',
            $src,
            'InstructorCourseController must define attendanceWatchlistExport() — CSV endpoint missing'
        );

        // Verify both methods open with the ownership gate.
        foreach (['attendanceWatchlist', 'attendanceWatchlistExport'] as $method) {
            $offset = strpos($src, "function $method");
            $this->assertNotFalse($offset, "method $method not found in controller source");
            $body = substr($src, $offset, 600); // first ~600 chars of method
            $this->assertStringContainsString(
                '$this->findOwnedCourseOrFail',
                $body,
                "$method() does not start with the findOwnedCourseOrFail() IDOR gate"
            );
        }
    }

    public function test_watchlist_computation_filters_short_sessions_and_handles_threshold_zero(): void
    {
        // Static guard on the shared computeAttendanceWatchlist() helper.
        // Two invariants matter:
        //   (1) sessions shorter than 60s are filtered out — the launcher
        //       posts join on EVERY connection-change event, so flaky
        //       reconnects inflate counts without this floor.
        //   (2) threshold == 0 means "no one is at-risk" — the feature is
        //       disabled, not "everyone fails".
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorCourseController.php')
        );

        $this->assertMatchesRegularExpression(
            "/duration_seconds['\"]?\s*,\s*['\"]?>=['\"]?\s*,\s*60/",
            $src,
            'Watchlist must filter duration_seconds >= 60 to exclude flaky-reconnect noise'
        );
        $this->assertMatchesRegularExpression(
            '/\$threshold\s*>\s*0\s*&&\s*\$totalClasses\s*>\s*0/',
            $src,
            'at_risk flag must short-circuit when threshold == 0 (feature disabled) or no live classes exist'
        );
    }

    public function test_watchlist_view_has_csv_button_and_status_banner(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/course/attendance-watchlist.blade.php')
        );

        $this->assertStringContainsString(
            'instructor.courses.attendance-watchlist.export',
            $view,
            'Watchlist view missing CSV download link'
        );
        $this->assertStringContainsString(
            'Download CSV',
            $view,
            'Watchlist view missing "Download CSV" button label'
        );
        // The three states (threshold=0, no classes, at-risk>0, all-OK)
        // each get a distinct alert banner so the instructor immediately
        // knows the room state without scanning the table.
        foreach (['alert-secondary', 'alert-info', 'alert-danger', 'alert-success'] as $cls) {
            $this->assertStringContainsString(
                $cls,
                $view,
                "Watchlist view missing $cls banner — instructor won't know the room state at a glance"
            );
        }
    }

    public function test_csv_export_streams_with_utf8_bom(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorCourseController.php')
        );
        $offset = strpos($src, 'function attendanceWatchlistExport');
        $body   = substr($src, $offset, 3000);

        $this->assertStringContainsString(
            'streamDownload',
            $body,
            'attendanceWatchlistExport must use streamDownload — non-streamed export OOMs on large cohorts'
        );
        $this->assertStringContainsString(
            "'Content-Type'           => 'text/csv",
            $body,
            'attendanceWatchlistExport must set Content-Type: text/csv'
        );
        $this->assertStringContainsString(
            "\\xEF\\xBB\\xBF",
            $body,
            'attendanceWatchlistExport must prepend UTF-8 BOM so Excel renders non-ASCII names correctly'
        );
    }

    public function test_attendance_trend_chart_is_computed_and_rendered(): void
    {
        // #6 — Per-class attendance trend. One bar per live class showing
        // the percent of enrolled students who attended. Below-threshold
        // bars must be color-coded red so instructors see the drop-offs.
        $controllerSrc = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorCourseController.php')
        );
        $this->assertStringContainsString(
            'function computeAttendanceTrend',
            $controllerSrc,
            'Watchlist controller must define computeAttendanceTrend()'
        );
        $this->assertStringContainsString(
            "'trend'     => \$trend",
            $controllerSrc,
            'Watchlist controller must pass \$trend to the view'
        );
        // The compute must use the same 60s "attended" floor as the
        // rest of the attendance subsystem so the trend matches the
        // watchlist's pct (otherwise instructors will see disagreeing
        // numbers and lose trust).
        $offset = strpos($controllerSrc, 'function computeAttendanceTrend');
        $body   = substr($controllerSrc, $offset, 1600);
        $this->assertMatchesRegularExpression(
            "/duration_seconds['\"]?\s*,\s*['\"]?>=['\"]?\s*,\s*60/",
            $body,
            'Trend computation must use the >= 60s floor (consistent with watchlist + student view)'
        );

        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/course/attendance-watchlist.blade.php')
        );
        $this->assertStringContainsString(
            'Attendance trend by class',
            $view,
            'Trend chart card missing from watchlist view'
        );
        // Bar color logic: red if below threshold, green if at/above.
        $this->assertStringContainsString(
            "'#ef4444'",
            $view,
            'Trend bars must render red for below-threshold classes'
        );
        $this->assertStringContainsString(
            "'#10b981'",
            $view,
            'Trend bars must render green for at/above-threshold classes'
        );
    }

    public function test_heatmap_grid_is_computed_and_rendered(): void
    {
        // #11 — Student × class attendance heatmap on the watchlist.
        // Controller must compute `$heatmap` and pass to the view; view
        // must render it. Three-state colouring (attended/partial/missed)
        // is the design contract — losing any of the three colors makes
        // the heatmap useless.
        $controllerSrc = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorCourseController.php')
        );
        $this->assertStringContainsString(
            'function computeAttendanceHeatmap',
            $controllerSrc,
            'Watchlist controller must define computeAttendanceHeatmap()'
        );
        $this->assertStringContainsString(
            "'heatmap'   => \$heatmap",
            $controllerSrc,
            'Watchlist controller must pass \$heatmap to the view'
        );
        // Three-state allowlist (no other states should sneak in).
        foreach (['attended', 'partial', 'missed'] as $state) {
            $this->assertStringContainsString(
                "'$state'",
                $controllerSrc,
                "computeAttendanceHeatmap must emit '$state' as a valid cell state"
            );
        }
        // O(1) DB lookup — the heatmap should NOT N+1 by querying per cell.
        $this->assertStringContainsString(
            'groupBy([',
            $controllerSrc,
            'Heatmap aggregation must use groupBy in a single query — N+1 will not scale to 100+ students × 50+ classes'
        );

        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/course/attendance-watchlist.blade.php')
        );
        $this->assertStringContainsString(
            'Attendance heatmap',
            $view,
            'Watchlist view missing the heatmap card'
        );
        // Three color legend entries must all be present so the grid
        // is interpretable without external docs.
        foreach (['Attended', 'Briefly', 'Missed'] as $legendLabel) {
            $this->assertStringContainsString(
                $legendLabel,
                $view,
                "Heatmap legend missing '$legendLabel' entry — grid colors are uninterpretable"
            );
        }
    }

    public function test_courses_index_links_to_watchlist(): void
    {
        // Discoverability tripwire — the only entry point to the watchlist
        // from the dashboard. If removed, the feature still exists but no
        // instructor will find it.
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/course/index.blade.php')
        );
        $this->assertStringContainsString(
            'instructor.courses.attendance-watchlist',
            $view,
            'Courses index missing watchlist link — feature is unreachable from the dashboard'
        );
    }
}
