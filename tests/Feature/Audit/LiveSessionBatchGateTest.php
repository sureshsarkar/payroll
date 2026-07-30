<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\Frontend\LearningController;
use App\Models\CourseBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Audit finding [8] - student live-session launcher page must agree with the
 * authoritative ZoomSignatureController per-batch gate.
 *
 * liveSession only checked course-level enrollment, so a Batch B student
 * could load Batch A's launcher (the signature endpoint would then 403 on
 * join). The page now applies the same batch rule: deny only when the
 * enrollment is in a different non-null batch; NULL-batch (legacy)
 * enrollments may join any batch class.
 */
class LiveSessionBatchGateTest extends TestCase
{
    use DatabaseTransactions;

    private array $course; // [id, slug]

    private function makeCourseForCoach(User $coach): array
    {
        $slug = 'bg-' . uniqid();
        $id = DB::table('courses')->insertGetId([
            'title' => 'BG course ' . uniqid(), 'slug' => $slug,
            'instructor_id' => $coach->id, 'added_by' => $coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return ['id' => $id, 'slug' => $slug];
    }

    private function makeZoomLesson(int $courseId, int $coachId, ?int $batchId): int
    {
        $chapterId = DB::table('course_chapters')->insertGetId([
            'title' => 'Ch ' . uniqid(), 'course_id' => $courseId, 'order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('course_chapter_items')->insertGetId([
            'chapter_id' => $chapterId, 'type' => 'live', 'order' => 1,
            'instructor_id' => $coachId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lessonId = DB::table('course_chapter_lessons')->insertGetId([
            'title' => 'Live lesson ' . uniqid(), 'slug' => 'll-' . uniqid(),
            'instructor_id' => $coachId, 'course_id' => $courseId, 'chapter_id' => $chapterId,
            'chapter_item_id' => $itemId, 'storage' => 'live', 'file_type' => 'live',
            'order' => 1, 'is_free' => 0, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('course_live_classes')->insert([
            'course_id' => $courseId, 'batch_id' => $batchId, 'lesson_id' => $lessonId,
            'type' => 'zoom', 'meeting_id' => '1234567890', 'start_time' => (string) now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $lessonId;
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

    private function invokeLive(User $student, int $lessonId)
    {
        $this->actingAs($student, 'web');
        $request = Request::create('/x', 'GET');
        return app(LearningController::class)->liveSession($request, $this->course['slug'], (string) $lessonId);
    }

    public function test_different_batch_student_is_redirected_not_launched(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $this->course = $this->makeCourseForCoach($coach);
        $batchA = CourseBatch::create(['course_id' => $this->course['id'], 'title' => 'A', 'status' => 'active', 'capacity' => 30]);
        $batchB = CourseBatch::create(['course_id' => $this->course['id'], 'title' => 'B', 'status' => 'active', 'capacity' => 30]);

        $lesson = $this->makeZoomLesson($this->course['id'], $coach->id, $batchA->id);

        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student->id, $this->course['id'], $batchB->id);

        $resp = $this->invokeLive($student, $lesson);
        $this->assertInstanceOf(RedirectResponse::class, $resp, 'Batch B student must be redirected, not shown the launcher.');
        $this->assertStringContainsString($this->course['slug'], $resp->getTargetUrl());
    }

    public function test_same_batch_student_reaches_launcher(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $this->course = $this->makeCourseForCoach($coach);
        $batchA = CourseBatch::create(['course_id' => $this->course['id'], 'title' => 'A', 'status' => 'active', 'capacity' => 30]);

        $lesson = $this->makeZoomLesson($this->course['id'], $coach->id, $batchA->id);

        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student->id, $this->course['id'], $batchA->id);

        $resp = $this->invokeLive($student, $lesson);
        // view() returns a View without rendering - proves the gate passed
        // and execution reached the zoom-launcher branch.
        $this->assertInstanceOf(View::class, $resp, 'Same-batch student must reach the launcher view.');
    }

    public function test_legacy_null_batch_enrollment_still_reaches_launcher(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $this->course = $this->makeCourseForCoach($coach);
        $batchA = CourseBatch::create(['course_id' => $this->course['id'], 'title' => 'A', 'status' => 'active', 'capacity' => 30]);

        $lesson = $this->makeZoomLesson($this->course['id'], $coach->id, $batchA->id);

        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student->id, $this->course['id'], null); // legacy NULL-batch enrollment

        $resp = $this->invokeLive($student, $lesson);
        $this->assertInstanceOf(View::class, $resp, 'Legacy NULL-batch enrollment may join any batch class.');
    }
}
