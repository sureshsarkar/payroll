<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * 2026-06-04 (#5) → 2026-06-05 redesign — a coach hosting a live class clicked
 * "Mark lesson complete" and got logged out: that button posted to
 * student.make-lesson-complete (studentrole-only; the middleware redirects any
 * NON-student to 'logoutme'). Completion is now a COACH action — the wrap-up
 * shows the host a "Mark class completed" button posting to the coach-only
 * live-class.complete route (sets ended_at + credits attendance). The student
 * self-mark button is gone entirely, so a coach can never hit the logout route.
 */
class LiveClassHostNotLoggedOutTest extends TestCase
{
    public function test_completion_is_a_coach_action_not_the_student_logout_route(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/student-dashboard/live/zoom.blade.php')
        );

        // The wrap-up JS still knows whether the viewer is the host.
        $this->assertStringContainsString('var isHost', $src,
            'the wrap-up JS must know whether the viewer is the host.');

        // The student-only "Mark lesson complete" button (which logged the host
        // out) is gone — both the element id and the student route.
        $this->assertStringNotContainsString('wuMarkComplete', $src,
            'the student-only "Mark lesson complete" button must be removed.');
        $this->assertStringNotContainsString("route('student.make-lesson-complete')", $src,
            'the wrap-up must no longer post to the student-only lesson-complete route (it logged the host out).');

        // Completion is a coach-panel action. 2026-07-10 (New Changes for UI
        // #9) — the gate widened from host-only (isHost) to any coach-panel
        // operator (canManage = coach OR authorised staff), so staff can end a
        // class too. Backend markCompleted() still re-authorises by ownership +
        // assigned batch, so surfacing the button is safe.
        $this->assertMatchesRegularExpression(
            "/canManage\s*&&\s*completeClassUrl\s*\?\s*'<button[^']*id=\"wuCompleteClass\"/s",
            $src,
            'a coach-panel operator (coach or staff) must get the "Mark class completed" button.'
        );
        // ...posting to the coach-only completion route (instructor.* prefixed).
        $this->assertStringContainsString("instructor.live-class.complete", $src,
            'class completion must post to the coach-only instructor.live-class.complete route.');
        $this->assertStringContainsString(
            "if (document.getElementById('wuCompleteClass'))",
            $src,
            'the completion listener must be guarded so it only attaches for the host.'
        );
    }

    public function test_make_lesson_complete_stays_student_only(): void
    {
        // The lesson-complete route is student-only by design; the middleware
        // logging a non-student out is WHY the host must never see the button.
        $src = (string) file_get_contents(
            app_path('Http/Middleware/StudentroleMiddleware.php')
        );
        $this->assertMatchesRegularExpression(
            "/role\s*!==\s*'student'.*logoutme/s",
            $src,
            'StudentroleMiddleware logs out non-students — so host access must be prevented at the view.'
        );
    }
}
