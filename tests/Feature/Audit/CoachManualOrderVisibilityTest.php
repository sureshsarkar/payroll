<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Doc-C-OfflineOrder regression pin (2026-05-29).
 *
 * User report: 'When I created an order using to assigning the course
 * to a student it is not listed here but student can visit the course
 * in his panel without payment & it is happening only at the first time.'
 *
 *   InstructorDashboardController::store creates (as of 2026-06-01):
 *     - Order row with payment_status=PENDING, status=PENDING
 *       (the coach generates the order; the student pays later — the coach
 *       then marks it Paid on the order screen → markPaid grants access +
 *       credits commission). This REPLACES the 2026-05-26 "bug-doc C4"
 *       behaviour that created orders paid+completed with immediate access.
 *     - OrderItem row with the correct course_id
 *     - NO Enrollment row (student has no access until the order is paid;
 *       markPaid creates the enrollment with has_access=1 at that point)
 *     - CoachStudentLink row (student is already the coach's; idempotent)
 *
 *   InstructorDashboardController::mySells lists:
 *     - OrderItem::whereIn('course_id', $coachOwnedCourseIds)
 *
 * If course_id flows through correctly, the new order MUST appear.
 *
 * This test pins the controller's store-time data shape so any future
 * regression that breaks order_item creation — or silently re-grants
 * immediate paid access — fails loudly.
 */
class CoachManualOrderVisibilityTest extends TestCase
{
    public function test_store_creates_order_item_with_correct_course_id(): void
    {
        $src = file_get_contents(
            base_path('app/Http/Controllers/Frontend/InstructorDashboardController.php')
        );

        // The store method must create an OrderItem with course_id from
        // the validated request. Any future refactor that drops the
        // course_id assignment would silently break the My Sales listing.
        $this->assertMatchesRegularExpression(
            "/OrderItem::create\\(\\s*\\[(?:[^\\]]*\\n)*?[^\\]]*'course_id'\\s*=>\\s*\\\$validated\\['course_id'\\]/",
            $src,
            'store() must create OrderItem with course_id from the validated input — '
            . 'dropping this would hide manual orders from My Sales'
        );
    }

    public function test_mysells_query_includes_order_items_by_coach_course_ids(): void
    {
        $src = file_get_contents(
            base_path('app/Http/Controllers/Frontend/InstructorDashboardController.php')
        );

        // The mySells() listing must filter OrderItem by course_id IN
        // (coach's courses). Any refactor that scopes by something else
        // (e.g. order.coach_id directly) needs explicit reviewer attention
        // because it changes which orders are visible.
        $this->assertStringContainsString(
            "OrderItem::whereIn('course_id', \$courseIds)",
            $src,
            'mySells() listing query must filter OrderItem by coach-owned course IDs'
        );

        // The course-id source must include BOTH added_by AND
        // instructor_id (a coach can be the original author OR the
        // assigned instructor; either counts).
        $this->assertMatchesRegularExpression(
            "/->where\\('added_by',\\s*\\\$coachId\\)\\s*->orWhere\\('instructor_id',\\s*\\\$coachId\\)/",
            $src,
            "coach-owned course IDs must include both added_by AND instructor_id matches"
        );
    }

    public function test_defensive_logging_on_manual_order_create(): void
    {
        // After this commit, every successful manual-order creation
        // logs a structured line with all key IDs so any future
        // 'order is missing from list' report can be traced from a
        // single grep on storage/logs.
        $src = file_get_contents(
            base_path('app/Http/Controllers/Frontend/InstructorDashboardController.php')
        );

        $this->assertStringContainsString(
            'coach-manual-order-created',
            $src,
            'store() must emit a structured log line on every manual-order create '
            . '(so the next Doc-C-OfflineOrder-style report has a traceable record)'
        );
    }

    /**
     * 2026-06-01 — coach-generated orders must be created PENDING with no
     * access, NOT auto-paid. Pins the corrected behaviour so a future
     * refactor can't silently re-grant immediate paid access (the thing the
     * coach explicitly asked us to stop doing).
     */
    public function test_store_creates_pending_order_without_granting_access(): void
    {
        $src = file_get_contents(
            base_path('app/Http/Controllers/Frontend/InstructorDashboardController.php')
        );

        // Isolate the store() body (up to the next method declaration) so we
        // assert on THIS method, not the whole controller.
        preg_match('/function\s+store\b.*?(?=\n\s*(?:public|private|protected)\s+function )/s', $src, $m);
        $body = $m[0] ?? '';
        $this->assertNotEmpty($body, 'store() method not found');

        // The Order::create payload must mark the order pending, not paid.
        $this->assertMatchesRegularExpression(
            "/'payment_status'\\s*=>\\s*'pending'/",
            $body,
            "store() must create coach orders with payment_status='pending' (not 'paid')"
        );
        $this->assertMatchesRegularExpression(
            "/'status'\\s*=>\\s*'pending'/",
            $body,
            "store() must create coach orders with status='pending' (not 'completed')"
        );

        // It must NOT grant access at creation time — i.e. it must not write
        // an Enrollment with has_access => 1. Access is markPaid()'s job.
        $this->assertDoesNotMatchRegularExpression(
            "/Enrollment::(?:updateOrCreate|create|firstOrCreate)\\([^;]*'has_access'\\s*=>\\s*1/s",
            $body,
            'store() must NOT create a has_access=1 enrollment — pending orders grant no access until paid'
        );
    }
}
