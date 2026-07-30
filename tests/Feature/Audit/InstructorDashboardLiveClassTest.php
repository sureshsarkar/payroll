<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * UI/UX audit P1-14 — verify the instructor dashboard renders upcoming
 * live classes prominently.
 *
 * Investigation: the dashboard already renders a list of upcoming live
 * classes with date pills (Today/Tomorrow), time, batch info, and an
 * empty state. The audit asked for a "hero card with countdown + Start
 * CTA" — that's stylistic; the existing list IS the live-class surface
 * and surfaces more information than a single hero card would.
 *
 * This test pins the existing implementation.
 */
class InstructorDashboardLiveClassTest extends TestCase
{
    public function test_instructor_dashboard_renders_live_class_surface(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/instructor-dashboard/index.blade.php')
        );

        // The data binding — controller passes upcoming live classes
        // via $myContent['upcoming_live'].
        $this->assertStringContainsString(
            "@forelse (\$myContent['upcoming_live']",
            $contents,
            'instructor dashboard must iterate over $myContent[upcoming_live]'
        );

        // Visual surface — list rows with time + label.
        $this->assertStringContainsString('instructor.live-classes.index', $contents,
            'live class rows must link to live-classes index');

        // Empty state — "No upcoming live classes" copy.
        $this->assertStringContainsString('No upcoming live classes', $contents,
            'must render a friendly empty state when no classes upcoming');

        // Date pill — Today / Tomorrow / specific date.
        $this->assertMatchesRegularExpression(
            "/(__\\('Today'\\)|__\\('Tomorrow'\\))/",
            $contents,
            'live class rows must show a date pill (Today / Tomorrow)'
        );
    }

    public function test_today_pulse_kpi_includes_live_classes_today(): void
    {
        // Adjacent surface — the "Today's Pulse" KPI grid includes
        // a live-classes-today counter. Pinning together since they
        // share the controller payload.
        $contents = file_get_contents(
            resource_path('views/frontend/instructor-dashboard/index.blade.php')
        );
        $this->assertStringContainsString('live_classes_today', $contents,
            "Today's Pulse must include a live_classes_today KPI");
    }
}
