<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tripwire — #10 attendance-based certificate eligibility
 * (2026-05-12).
 *
 * Legacy gate: course completion = 100% of CourseChapterItem rows
 * watched. That gate is impossible for live-only cohort courses (no
 * recorded lesson rows to "watch"), so we added a parallel path:
 * attendance percent ≥ course.attendance_threshold_percent.
 *
 * If a refactor removes the new helper or restores the legacy
 * `!= 100` strict check, live-cohort students lose their certificate.
 */
class CertificateAttendanceEligibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_controller_uses_eligibility_helper_not_strict_100_pct(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/StudentDashboardController.php')
        );

        // The new helper must exist and the controller must call it.
        $this->assertStringContainsString(
            'function meetsCertificateRequirements',
            $src,
            'StudentDashboardController must define meetsCertificateRequirements() — eligibility logic is missing'
        );
        $this->assertStringContainsString(
            '$this->meetsCertificateRequirements($course',
            $src,
            'downloadCertificate() must call meetsCertificateRequirements() — the strict 100% gate would lock out live-cohort students'
        );
        // Old gate `$courseCompletedPercent != 100` must be GONE — leaving
        // it in addition to the helper would short-circuit and break the
        // attendance path.
        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*\$courseCompletedPercent\s*!=\s*100\s*\)\s*\{\s*return\s+abort\(404\)/',
            $src,
            'Strict `!= 100` gate still present — must be replaced by the helper'
        );
    }

    public function test_eligibility_helper_short_circuits_threshold_zero(): void
    {
        // threshold=0 means "instructor disabled attendance gating".
        // The helper must NOT auto-issue certificates in that case for
        // students who fall short of lesson 100%, otherwise the
        // setting silently does the opposite of what's documented.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/StudentDashboardController.php')
        );
        $offset = strpos($src, 'function meetsCertificateRequirements');
        $body   = substr($src, $offset, 2000);

        $this->assertStringContainsString(
            'if ($threshold <= 0) {',
            $body,
            'Helper must short-circuit when threshold == 0 — disabling attendance gating shouldn\'t auto-issue certificates'
        );
    }

    public function test_eligibility_helper_uses_60_second_floor(): void
    {
        // Consistency tripwire — the eligibility check must use the same
        // "attended = ≥ 60s aggregate" rule as the watchlist + student
        // my-attendance view. Different floors here would mean a student
        // who "sees themselves as on track" gets a 404 on the certificate
        // download, or vice versa.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/StudentDashboardController.php')
        );
        $offset = strpos($src, 'function meetsCertificateRequirements');
        $body   = substr($src, $offset, 2000);

        $this->assertMatchesRegularExpression(
            "/duration_seconds['\"]?\s*,\s*['\"]?>=['\"]?\s*,\s*60/",
            $body,
            'Certificate eligibility must use the >= 60s floor — must match the watchlist + my-attendance views'
        );
    }
}
