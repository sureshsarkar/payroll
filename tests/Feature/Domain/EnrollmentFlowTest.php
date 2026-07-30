<?php

namespace Tests\Feature\Domain;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Domain test — enrolment business invariants.
 *
 * Audit 2026-05-18 phase 5 — promoted from skeleton. Tests the
 * database-level invariants that the order → enrollment pipeline
 * relies on. Verifies the SQL contracts, NOT the HTTP form path
 * (which would need session + CSRF + a feature-route smoke harness).
 */
class EnrollmentFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unique_user_course_constraint_prevents_duplicate_enrolments(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();

        $courseId = \DB::table('courses')->insertGetId([
            'title' => 'Test '.uniqid(), 'slug' => 'test-'.uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $orderId = \DB::table('orders')->insertGetId([
            'buyer_id' => $student->id, 'payable_currency' => 'INR',
            'payable_amount' => 0, 'paid_amount' => 0,
            'payment_method' => 'free', 'payment_status' => 'paid',
            'status' => 'completed',
            'invoice_id' => 'TEST-'.uniqid(),
            'transaction_id' => 'TX-'.uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // First enrolment succeeds
        Enrollment::create([
            'user_id' => $student->id, 'order_id' => $orderId,
            'course_id' => $courseId, 'has_access' => 1,
        ]);

        // Second insert with same (user_id, course_id) MUST fail at the DB layer
        $this->expectException(\Illuminate\Database\QueryException::class);
        Enrollment::create([
            'user_id' => $student->id, 'order_id' => $orderId,
            'course_id' => $courseId, 'has_access' => 1,
        ]);
    }

    public function test_firstOrCreate_is_idempotent_for_same_user_course(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();

        $courseId = \DB::table('courses')->insertGetId([
            'title' => 'Test '.uniqid(), 'slug' => 'test-'.uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = \DB::table('orders')->insertGetId([
            'buyer_id' => $student->id, 'payable_currency' => 'INR',
            'payable_amount' => 0, 'paid_amount' => 0,
            'payment_method' => 'free', 'payment_status' => 'paid',
            'status' => 'completed',
            'invoice_id' => 'TEST-'.uniqid(),
            'transaction_id' => 'TX-'.uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // First firstOrCreate inserts
        $a = Enrollment::firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $courseId],
            ['order_id' => $orderId, 'has_access' => 1]
        );
        // Second is a no-op — same row id returned
        $b = Enrollment::firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $courseId],
            ['order_id' => $orderId, 'has_access' => 1]
        );

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Enrollment::where('user_id', $student->id)
            ->where('course_id', $courseId)->count());
    }

    public function test_enrollment_batch_id_can_be_null_for_course_wide_enrol(): void
    {
        // After phase-2 migration enrollments.batch_id is nullable.
        // Course-wide enrolments (free / no-batch-picker flow) leave it NULL;
        // the visibility scopes still resolve correctly.
        $coach = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();

        $courseId = \DB::table('courses')->insertGetId([
            'title' => 'Test '.uniqid(), 'slug' => 'test-'.uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = \DB::table('orders')->insertGetId([
            'buyer_id' => $student->id, 'payable_currency' => 'INR',
            'payable_amount' => 0, 'paid_amount' => 0,
            'payment_method' => 'free', 'payment_status' => 'paid',
            'status' => 'completed',
            'invoice_id' => 'TEST-'.uniqid(),
            'transaction_id' => 'TX-'.uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $e = Enrollment::create([
            'user_id' => $student->id, 'order_id' => $orderId,
            'course_id' => $courseId, 'batch_id' => null,
            'has_access' => 1,
        ]);

        $this->assertNull($e->fresh()->batch_id);
        $this->assertSame(1, $e->has_access);
    }
}
