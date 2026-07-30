<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Verifies the file-serve / download audit guarantees.
 *
 * Real bugs found and fixed in this audit pass:
 *
 *   - LearningController::downloadResource (web)
 *       Before: any authenticated user could hit
 *       /student/learning/resource-download/{lessonId} and download any
 *       lesson's resource by guessing the lesson_id — the route was just
 *       behind auth+verified, with no per-course enrollment check. Plus
 *       no path-traversal guard on the DB-stored `file_path` (legacy rows
 *       from before the upload-helper allowlist could contain `..`).
 *       Fixed: Enrollment::where(course_id, has_access=1)->firstOrFail()
 *       and realpath()-against-public/uploads check.
 *
 *   - InstructorDashboardController::printInvoice (web)
 *       Before: Order::where('id', $id)->firstOrFail() — no ownership scope,
 *       so any instructor could print any order's invoice and read the
 *       buyer's name/email/address + gross amount.
 *       Fixed (2026-06-01): resolve the order-ITEM id through
 *       coachOwnedOrderItemQuery() (course-ownership scope) — the same gate
 *       the "View order" action uses. (A short-lived seller_id scope 404'd
 *       legitimate student-purchased orders, since seller_id is only set on
 *       coach *manual* sales.)
 *
 *   - API\DashboardController::downloadCertificate
 *       Before: str_replace([student_name]...) with raw user.name into PDF
 *       HTML, then Dompdf with enable_remote=true. SSRF + HTML injection
 *       if any field contained `<img src=http://attacker>`.
 *       Fixed: htmlspecialchars on every interpolated value (already in
 *       place on the web-side StudentDashboardController equivalent).
 */
class FileServeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_download_resource_has_enrollment_check(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Frontend/LearningController.php'));

        // The downloadResource() body must contain Enrollment::where with
        // user_id, course_id, has_access=1 — same gate used for cert + getFileInfo.
        $this->assertMatchesRegularExpression(
            '/function\s+downloadResource[^}]+Enrollment::where[^}]+\->where\(\s*[\'"]has_access[\'"]\s*,\s*1\s*\)/s',
            $src,
            'downloadResource must enforce an Enrollment+has_access=1 gate before serving the file'
        );
    }

    public function test_download_resource_has_path_traversal_guard(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Frontend/LearningController.php'));

        // The downloadResource() body must call realpath() and verify the
        // resolved path stays under public/uploads.
        $this->assertStringContainsString('realpath(public_path(', $src);
        $this->assertStringContainsString("realpath(public_path('uploads')", $src);
        $this->assertStringContainsString('str_starts_with($real, $allowed)', $src,
            'downloadResource must reject paths that resolve outside public/uploads/');
    }

    public function test_instructor_invoice_print_is_ownership_scoped(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Frontend/InstructorDashboardController.php'));

        // Isolate the printInvoice() body (up to the next method declaration).
        preg_match('/function\s+printInvoice\b.*?(?=\n\s*public function )/s', $src, $m);
        $body = $m[0] ?? '';
        $this->assertNotEmpty($body, 'printInvoice method not found');

        // 2026-06-01: printInvoice resolves the order-ITEM id through the
        // course-ownership scope (coachOwnedOrderItemQuery) — the same gate the
        // "View order" action uses — so a coach can only print invoices for
        // orders containing their own courses. Keeps the PII/amount protection
        // AND fixes the 404 on student-purchased orders that the old seller_id
        // scope caused (seller_id is only set on coach manual sales).
        $this->assertStringContainsString('coachOwnedOrderItemQuery()', $body,
            'printInvoice must scope by course ownership via coachOwnedOrderItemQuery()');

        // It must NOT regress to an unscoped Order::where('id',$id)->firstOrFail()
        // lookup (the original IDOR leaking buyer PII + gross amount).
        $this->assertDoesNotMatchRegularExpression(
            '/Order::where\(\s*[\'"]id[\'"]\s*,\s*\$id\s*\)\s*->firstOrFail/s',
            $body,
            'printInvoice must not look up Order by raw id without an ownership scope'
        );
    }

    public function test_api_certificate_escapes_str_replace_values(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/API/DashboardController.php'));
        // The escape closure introduced by the audit.
        $this->assertStringContainsString('htmlspecialchars((string) $s', $src,
            'API downloadCertificate must escape interpolated values before str_replace into PDF HTML');
        // And actually call it on the user-controlled fields.
        $this->assertStringContainsString("'[student_name]',    \$esc(\$user->name)", $src);
        $this->assertStringContainsString("'[course]',          \$esc(\$course->title)", $src);
        $this->assertStringContainsString("'[instructor_name]', \$esc(\$course->instructor", $src);
    }
}
