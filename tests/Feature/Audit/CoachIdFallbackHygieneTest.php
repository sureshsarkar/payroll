<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression guard for a real cross-company data leak (found 2026-08-24):
 * five HR-domain controllers each carried their own copy-pasted
 * `teamMembers()` that fell back to the legacy, unscoped
 * `User::where('coach_id', $hr->id)` lookup whenever the company-scoped
 * `EmployeeProfile::where('reporting_hr_id', ...)` list was empty for the
 * ACTIVE company. An HR who owns several companies coaches every employee
 * under the same coach_id, so this fallback pulled another company's
 * employees into attendance, payroll, salary-structure and leave-approval
 * screens the moment the active company itself had none under
 * reporting_hr_id yet — and, in HrEmployeeController specifically, let an
 * edit-save attempt cross-insert and violate employee_profiles_user_id_unique.
 *
 * The fix: every one of those methods must delegate to
 * `EmployeeProfile::teamUserIds()` (Modules/HrEmployee/app/Models/EmployeeProfile.php),
 * the single place that fallback is allowed — and even there, only when NO
 * company is bound at all (CLI/legacy paths), never merely because the
 * active company's list was empty.
 *
 * This test scans source for the danger pattern reappearing outside that one
 * sanctioned model method. It's a source scan, not a DB test, so it catches
 * the bug class even before a company-isolation integration test would.
 */
class CoachIdFallbackHygieneTest extends TestCase
{
    /** Files allowed to contain a raw `coach_id` lookup. Everything else in
     *  the HR domain must go through EmployeeProfile::teamUserIds(). */
    private const ALLOWLIST = [
        // the one sanctioned fallback, gated on "no company bound at all"
        'Modules/HrEmployee/app/Models/EmployeeProfile.php',
        // legacy write on employee creation — not a scoping read
        'Modules/HrEmployee/app/Http/Controllers/HrEmployeeController.php',
    ];

    private const SCAN_ROOTS = [
        'Modules/Attendance',
        'Modules/Leave',
        'Modules/HrEmployee',
        'Modules/Payroll',
    ];

    public function test_no_controller_reimplements_the_unscoped_coach_id_fallback(): void
    {
        $hits = [];

        foreach (self::SCAN_ROOTS as $root) {
            $abs = base_path($root);
            if (! is_dir($abs)) {
                continue;
            }

            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file->getPathname());
                if (in_array($rel, self::ALLOWLIST, true)) {
                    continue;
                }

                $body = (string) file_get_contents($file->getPathname());
                if (preg_match("/where\\(\\s*'coach_id'/", $body)) {
                    $hits[] = $rel;
                }
            }
        }

        $this->assertEmpty(
            $hits,
            "Found a raw coach_id lookup outside the sanctioned fallback:\n  ".
            implode("\n  ", $hits).
            "\nDelegate to EmployeeProfile::teamUserIds(\$hr) instead — see this ".
            'test\'s class docblock for the leak scenario this prevents.'
        );
    }

    /**
     * Every HR-domain controller that defines a private teamMembers()/
     * linkedEmployees() helper must implement it as a one-line delegation to
     * EmployeeProfile::teamUserIds() — not its own reporting_hr_id query —
     * so the company-scoping rule lives in exactly one place.
     */
    public function test_team_lookup_helpers_delegate_to_team_user_ids(): void
    {
        $files = [
            'Modules/Attendance/app/Http/Controllers/AttendanceController.php',
            'Modules/Payroll/app/Http/Controllers/PayrollController.php',
            'Modules/Payroll/app/Http/Controllers/SalaryStructureController.php',
            'Modules/Leave/app/Http/Controllers/LeaveApprovalController.php',
            'Modules/HrEmployee/app/Http/Controllers/HrEmployeeController.php',
        ];

        foreach ($files as $rel) {
            $path = base_path($rel);
            $this->assertFileExists($path);

            $body = (string) file_get_contents($path);
            $this->assertMatchesRegularExpression(
                '/EmployeeProfile::teamUserIds\(\s*\$hr\s*\)/',
                $body,
                "$rel must resolve its team via EmployeeProfile::teamUserIds(\$hr)."
            );
        }
    }
}
