<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * UI/UX audit P3-4 — instructor courses listing layout.
 *
 * Investigation: the page already uses a responsive card grid
 * (grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)))
 * — the audit's recommendation of "card-grid layout option" is
 * already met.
 *
 * The audit also suggested a table↔grid toggle for users with many
 * courses. That's a 1-week feature (toggle state, query persistence,
 * dual template). Out of scope for this regression-pin pass; logged
 * as P3-4-followup for the next contributor.
 *
 * What this test pins:
 *   - The grid uses CSS grid (not Bootstrap row/col fixed grid)
 *   - minmax responsive columns (cards reflow on viewport change)
 *   - The $courses paginator is iterated
 */
class InstructorCoursesLayoutTest extends TestCase
{
    public function test_instructor_courses_renders_responsive_card_grid(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/instructor-dashboard/course/index.blade.php')
        );

        $this->assertStringContainsString('.ic-grid', $contents,
            'instructor courses must use the .ic-grid card layout');
        $this->assertStringContainsString('display: grid', $contents,
            'must use CSS grid (responsive reflow)');
        $this->assertStringContainsString(
            'repeat(auto-fill, minmax(280px, 1fr))',
            $contents,
            'must use minmax responsive columns (cards reflow on viewport change)'
        );
    }

    public function test_instructor_courses_iterates_paginator(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/instructor-dashboard/course/index.blade.php')
        );
        $this->assertStringContainsString('@foreach ($courses as $course)', $contents);
        // Pagination — paginator must surface its links() somewhere
        // (not necessarily this file, but at minimum the foreach should
        // be on a paginator-aware variable name).
        $this->assertStringContainsString('$courses', $contents);
    }
}
