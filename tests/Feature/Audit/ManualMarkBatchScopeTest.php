<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\Frontend\Coach\LiveClassController;
use App\Models\CourseBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Audit finding [7] — manual attendance must respect batch scope.
 *
 * attendanceManualMark verified the marked user was enrolled in the COURSE
 * (has_access=1) but ignored the live class's batch_id. A coach running
 * Batch A's class could therefore mark a Batch B student of the same course
 * present, polluting the wrong batch's attendance. The fix adds an
 * enrollments.batch_id constraint when the class is batch-scoped, while
 * leaving course-wide classes (batch_id NULL) checking course enrollment
 * only.
 */
class ManualMarkBatchScopeTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCourseForCoach(User $coach): int
    {
        return DB::table('courses')->insertGetId([
            'title' => 'Batch course ' . uniqid(), 'slug' => 'bc-' . uniqid(),
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeLessonId(int $courseId, int $coachId): int
    {
        $chapterId = DB::table('course_chapters')->insertGetId([
            'title' => 'Ch ' . uniqid(), 'course_id' => $courseId, 'order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('course_chapter_items')->insertGetId([
            'chapter_id' => $chapterId, 'type' => 'live', 'order' => 1,
            'instructor_id' => $coachId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('course_chapter_lessons')->insertGetId([
            'title' => 'Live lesson ' . uniqid(), 'slug' => 'll-' . uniqid(),
            'instructor_id' => $coachId, 'course_id' => $courseId, 'chapter_id' => $chapterId,
            'chapter_item_id' => $itemId, 'storage' => 'live', 'file_type' => 'live',
            'order' => 1, 'is_free' => 0, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeLiveClass(int $courseId, int $coachId, ?int $batchId): int
    {
        return DB::table('course_live_classes')->insertGetId([
            'course_id' => $courseId, 'batch_id' => $batchId,
            'lesson_id' => $this->makeLessonId($courseId, $coachId),
            'start_time' => (string) now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function enroll(int $userId, int $courseId, ?int $batchId): void
    {
        $order = Order::create([
            'buyer_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid',
            'payable_amount' => 0, 'paid_amount' => 0, 'commission_rate' => 0,
        ]);
        Enrollment::create([
            'user_id' => $userId, 'course_id' => $courseId,
            'batch_id' => $batchId, 'order_id' => $order->id, 'has_access' => 1,
        ]);
    }

    private function mark(User $coach, int $liveClassId, int $userId): \Illuminate\Http\RedirectResponse
    {
        $this->actingAs($coach, 'web');
        $request = Request::create('/x', 'POST', ['user_id' => $userId, 'reason' => 'late join, verified by email']);
        return app(LiveClassController::class)->attendanceManualMark($request, (string) $liveClassId);
    }

    public function test_cannot_mark_other_batch_student_on_batched_class(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = $this->makeCourseForCoach($coach);
        $batchA = CourseBatch::create(['course_id' => $courseId, 'title' => 'A', 'status' => 'active', 'capacity' => 30]);
        $batchB = CourseBatch::create(['course_id' => $courseId, 'title' => 'B', 'status' => 'active', 'capacity' => 30]);

        $liveA = $this->makeLiveClass($courseId, $coach->id, $batchA->id);

        $studentB = User::factory()->create(['role' => 'student']);
        $this->enroll($studentB->id, $courseId, $batchB->id); // enrolled, but in BATCH B

        try {
            $this->mark($coach, $liveA, $studentB->id);
            $this->fail('Expected a 403 — Batch B student must not be markable on a Batch A class.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseMissing('live_class_attendances', [
            'course_live_class_id' => $liveA, 'user_id' => $studentB->id,
        ]);
    }

    public function test_can_mark_same_batch_student_on_batched_class(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = $this->makeCourseForCoach($coach);
        $batchA = CourseBatch::create(['course_id' => $courseId, 'title' => 'A', 'status' => 'active', 'capacity' => 30]);

        $liveA = $this->makeLiveClass($courseId, $coach->id, $batchA->id);

        $studentA = User::factory()->create(['role' => 'student']);
        $this->enroll($studentA->id, $courseId, $batchA->id);

        $resp = $this->mark($coach, $liveA, $studentA->id);
        $this->assertEquals(302, $resp->getStatusCode());

        $this->assertDatabaseHas('live_class_attendances', [
            'course_live_class_id' => $liveA, 'user_id' => $studentA->id,
            'role' => 'student', 'is_manual' => 1,
        ]);
    }

    public function test_course_wide_class_still_marks_any_enrolled_student(): void
    {
        // batch_id NULL on the live class => course-level check only (legacy).
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $courseId = $this->makeCourseForCoach($coach);
        $liveWide = $this->makeLiveClass($courseId, $coach->id, null);

        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student->id, $courseId, null); // course-wide enrollment

        $resp = $this->mark($coach, $liveWide, $student->id);
        $this->assertEquals(302, $resp->getStatusCode());
        $this->assertDatabaseHas('live_class_attendances', [
            'course_live_class_id' => $liveWide, 'user_id' => $student->id,
        ]);
    }
}
