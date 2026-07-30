<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * UI/UX audit P1-7 + P1-8 — verify the "Continue learning" hero on
 * student dashboard AND the resume/continue button on enrolled-courses.
 *
 * Investigation: both were already implemented in the student-dashboard
 * corporate redesign (2026-05). The audit recommendation was outdated.
 *
 * This test pins both so they can't silently regress.
 */
class StudentDashboardResumeTest extends TestCase
{
    public function test_student_dashboard_renders_continue_learning_hero(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/student-dashboard/index.blade.php')
        );
        // The hero is conditional on $resume + $resume->course (only
        // shown when the student has a course in progress). Pattern
        // must remain present.
        $this->assertStringContainsString('Continue learning', $contents,
            'student dashboard must render the "Continue learning" hero (P1-7)');
        $this->assertStringContainsString('$resume', $contents,
            'hero must be driven by the $resume payload from the controller');
        $this->assertStringContainsString('student.learning.index', $contents,
            'hero CTA must route to student.learning.index for the current course');
    }

    public function test_student_dashboard_renders_progress_bar_for_resume(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/student-dashboard/index.blade.php')
        );
        $this->assertStringContainsString('sd-resume__bar-fill', $contents);
        $this->assertStringContainsString('$resumePercent', $contents,
            'progress bar fill must be driven by $resumePercent');
    }

    public function test_enrolled_courses_renders_continue_button_for_in_progress(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/student-dashboard/enrolled-courses/index.blade.php')
        );
        $this->assertStringContainsString('isInProgress', $contents,
            'enrolled-courses must branch on isInProgress to choose Continue vs Start');
        // Continue label
        $this->assertMatchesRegularExpression(
            "/__\\('Continue'\\)/",
            $contents,
            'enrolled-courses must render a "Continue" CTA for in-progress courses'
        );
        $this->assertStringContainsString('student.learning.index', $contents);
    }

    public function test_enrolled_courses_renders_start_course_for_not_started(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/student-dashboard/enrolled-courses/index.blade.php')
        );
        // The third branch — not-started — uses "Start course". Together
        // with Continue + Certificate, this is the 3-state CTA pattern.
        $this->assertMatchesRegularExpression(
            "/__\\('Start course'\\)/",
            $contents,
            'enrolled-courses must render a "Start course" CTA for never-started courses'
        );
    }
}
