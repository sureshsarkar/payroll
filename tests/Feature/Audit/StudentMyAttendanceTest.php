<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression tripwire — student-facing "My attendance" view added 2026-05-11.
 *
 * Mirrors the instructor at-risk watchlist but scoped to ONE user (the
 * caller). Discoverable from the enrolled-courses page; gated by the
 * existing enrollment check inside LearningController.
 *
 * If a future refactor removes the route, the controller method, the
 * view, or the link, this test fails loud.
 */
class StudentMyAttendanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_my_attendance_route_is_registered(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        $this->assertContains(
            'student.learning.my-attendance',
            $names,
            'GET /student/learning/{slug}/my-attendance route missing — student-side attendance page unreachable'
        );
    }

    public function test_my_attendance_controller_method_enforces_enrollment(): void
    {
        // The method must run through whereHas('enrollments', ...) with
        // has_access=1 — the same gate the existing index() uses. Without
        // it, any authenticated user could read any course's attendance
        // by guessing the slug.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/LearningController.php')
        );

        $this->assertStringContainsString(
            'function myAttendance',
            $src,
            'LearningController must define myAttendance() — student view endpoint missing'
        );

        $offset = strpos($src, 'function myAttendance');
        $this->assertNotFalse($offset, 'myAttendance method body not located');
        $body = substr($src, $offset, 1500);

        // The enrollment check pattern used by index() is:
        //   whereHas('enrollments', fn ($q) => $q->where('user_id', $user->id))
        // We accept either the closure or arrow-fn form, with or without
        // the explicit has_access=1 filter (myAttendance adds it; index
        // doesn't currently).
        $this->assertMatchesRegularExpression(
            '/whereHas\s*\(\s*[\'"]enrollments[\'"]/',
            $body,
            'myAttendance() must filter the course query by the caller\'s enrollment to prevent IDOR'
        );
    }

    public function test_my_attendance_uses_same_60_second_floor_as_instructor_side(): void
    {
        // Single source of truth for "attended": ≥ 60s. If student-side
        // and instructor-side disagree on the floor, a student looking
        // at "on track" could simultaneously be flagged as at-risk on
        // the instructor's watchlist. Drift-resistant test: the literal
        // 60 must appear in the myAttendance() method body.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/LearningController.php')
        );
        $offset = strpos($src, 'function myAttendance');
        $body   = substr($src, $offset, 2500);

        $this->assertStringContainsString(
            '>= 60',
            $body,
            'myAttendance() must use the >= 60s floor for "attended" — must match the instructor watchlist'
        );
    }

    public function test_view_exists_with_threshold_status_banners(): void
    {
        $viewPath = base_path('resources/views/frontend/student-dashboard/learning/my-attendance.blade.php');
        $this->assertFileExists($viewPath, 'My attendance view file missing');

        $view = (string) file_get_contents($viewPath);

        // Four mutually exclusive banner states for the student. Symmetric
        // with the instructor watchlist for screenshot consistency.
        foreach (['alert-secondary', 'alert-info', 'alert-danger', 'alert-success'] as $cls) {
            $this->assertStringContainsString(
                $cls,
                $view,
                "My attendance view missing $cls banner — student won't know their state at a glance"
            );
        }

        // Class-by-class table must surface "joined briefly" rows distinctly
        // — without it a student who joined for 30s thinks they were absent
        // (or worse, that the system lost their data).
        $this->assertStringContainsString(
            'Joined briefly',
            $view,
            'My attendance must show the "Joined briefly" state for sub-60s sessions'
        );
    }

    public function test_enrolled_courses_page_links_to_my_attendance(): void
    {
        // Discoverability tripwire. The link from enrolled-courses is the
        // only path students have to reach the page from the dashboard.
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/student-dashboard/enrolled-courses/index.blade.php')
        );
        $this->assertStringContainsString(
            'student.learning.my-attendance',
            $view,
            'Enrolled-courses page missing link to my-attendance — feature unreachable from student dashboard'
        );
    }
}
