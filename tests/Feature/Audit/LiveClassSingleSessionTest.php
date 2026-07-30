<?php

namespace Tests\Feature\Audit;

use App\Http\Controllers\Frontend\ZoomSignatureController;
use App\Models\LiveClassAttendance;
use App\Models\User;
use App\Models\ZoomCredential;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Modules\Order\app\Models\Order;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Enterprise H-B — one live class at a time per student.
 *
 * The Zoom SDK signature endpoint is the access-grant choke point. If a
 * student already has an OPEN (un-left) attendance in a DIFFERENT live class
 * whose scheduled window is still active, a signature for a second class must
 * be denied (409) — a shared account can't drive two meetings at once. Stale
 * open rows from past classes (window elapsed) must NOT block a new join.
 */
class LiveClassSingleSessionTest extends TestCase
{
    use DatabaseTransactions;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        ZoomCredential::create([
            'instructor_id' => $this->coach->id,
            'sdk_key'       => 'test_sdk_key',
            'sdk_secret'    => 'test_sdk_secret',
        ]);
    }

    /** @return array{0:int,1:int} [lessonId, liveClassId] */
    private function makeZoomClass(string $title, int $durationMin = 60): array
    {
        $courseId = DB::table('courses')->insertGetId([
            'title' => $title . ' ' . uniqid(), 'slug' => 'ss-' . uniqid(),
            'instructor_id' => $this->coach->id, 'added_by' => $this->coach->id,
            'is_approved' => 'approved', 'status' => 'active',
            'type' => 'course', 'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $chapterId = DB::table('course_chapters')->insertGetId([
            'title' => 'Ch', 'course_id' => $courseId, 'order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('course_chapter_items')->insertGetId([
            'chapter_id' => $chapterId, 'type' => 'live', 'order' => 1,
            'instructor_id' => $this->coach->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lessonId = DB::table('course_chapter_lessons')->insertGetId([
            'title' => 'Live', 'slug' => 'll-' . uniqid(),
            'instructor_id' => $this->coach->id, 'course_id' => $courseId, 'chapter_id' => $chapterId,
            'chapter_item_id' => $itemId, 'storage' => 'live', 'file_type' => 'live',
            'order' => 1, 'is_free' => 0, 'status' => 'active', 'duration' => $durationMin,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $liveId = DB::table('course_live_classes')->insertGetId([
            'course_id' => $courseId, 'lesson_id' => $lessonId,
            'type' => 'zoom', 'meeting_id' => '999' . rand(1000, 9999),
            'start_time' => (string) now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$lessonId, $liveId, $courseId];
    }

    private function enroll(int $userId, int $courseId): void
    {
        $order = Order::create([
            'buyer_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid',
            'payable_amount' => 0, 'paid_amount' => 0, 'commission_rate' => 0,
        ]);
        Enrollment::create(['user_id' => $userId, 'course_id' => $courseId, 'order_id' => $order->id, 'has_access' => 1]);
    }

    private function issue(User $student, int $lessonId)
    {
        $this->actingAs($student, 'web');
        return app(ZoomSignatureController::class)->issue(Request::create('/x', 'POST'), (string) $lessonId);
    }

    public function test_blocks_second_live_class_while_first_is_active(): void
    {
        [$lessonA, $liveA, $courseA] = $this->makeZoomClass('A', 60);
        [$lessonB, $liveB, $courseB] = $this->makeZoomClass('B', 60);
        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student->id, $courseA);
        $this->enroll($student->id, $courseB);

        // Student is currently IN class A (open attendance, A started now).
        LiveClassAttendance::create([
            'course_live_class_id' => $liveA, 'user_id' => $student->id, 'role' => 'student',
            'joined_at' => now(), 'left_at' => null,
        ]);
        DB::table('course_live_classes')->where('id', $liveA)->update(['start_time' => (string) now()->subMinutes(5)]);

        try {
            $this->issue($student, $lessonB);
            $this->fail('Expected 409 — already in another active live class.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_allows_join_when_other_class_window_has_elapsed(): void
    {
        [$lessonA, $liveA, $courseA] = $this->makeZoomClass('A', 60);
        [$lessonB, $liveB, $courseB] = $this->makeZoomClass('B', 60);
        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student->id, $courseA);
        $this->enroll($student->id, $courseB);

        // Stale open row: class A started 3h ago (60-min window long elapsed).
        LiveClassAttendance::create([
            'course_live_class_id' => $liveA, 'user_id' => $student->id, 'role' => 'student',
            'joined_at' => now()->subHours(3), 'left_at' => null,
        ]);
        DB::table('course_live_classes')->where('id', $liveA)->update(['start_time' => (string) now()->subHours(3)]);

        $resp = $this->issue($student, $lessonB);
        $this->assertInstanceOf(JsonResponse::class, $resp, 'a stale past-class row must not block a new join');
    }

    public function test_allows_join_with_no_open_attendance(): void
    {
        [$lessonB, $liveB, $courseB] = $this->makeZoomClass('B', 60);
        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student->id, $courseB);

        $resp = $this->issue($student, $lessonB);
        $this->assertInstanceOf(JsonResponse::class, $resp);
    }
}
