<?php

namespace Tests\Feature\Domain;

use App\Jobs\NotifyLiveClassStarted;
use App\Models\CoachDomain;
use App\Models\CourseLiveClass;
use App\Models\User;
use App\Notifications\LiveClassStartedToStudent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Live Class Started" notification — enterprise acceptance scenarios.
 *
 * When a class starts, only enrolled SAME-course + SAME-batch students who have
 * NOT joined receive the branded email. Strictly tenant-isolated + duplicate-safe.
 */
class LiveClassStartedNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role, string $status = 'active'): int
    {
        return DB::table('users')->insertGetId([
            'role' => $role, 'name' => ucfirst($role) . ' ' . uniqid(),
            'email' => substr($role, 0, 1) . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => $status, 'is_banned' => 'no',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function course(int $coachId): int
    {
        return DB::table('courses')->insertGetId([
            'title' => 'C ' . uniqid(), 'slug' => 'c' . uniqid(), 'instructor_id' => $coachId, 'added_by' => $coachId,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'live', 'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function batch(int $courseId): int
    {
        return DB::table('course_batches')->insertGetId([
            'course_id' => $courseId, 'title' => 'B ' . uniqid(), 'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '09:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'days' => json_encode(['monday']),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function lesson(int $courseId, int $coachId): int
    {
        $chap = DB::table('course_chapters')->insertGetId([
            'course_id' => $courseId, 'title' => 'Ch', 'order' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('course_chapter_lessons')->insertGetId([
            'chapter_id' => $chap, 'instructor_id' => $coachId, 'title' => 'L', 'downloadable' => 0, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function liveClass(int $courseId, int $batchId, int $lessonId): int
    {
        return DB::table('course_live_classes')->insertGetId([
            'course_id' => $courseId, 'batch_id' => $batchId, 'lesson_id' => $lessonId,
            'start_time' => now()->format('Y-m-d H:i:s'), 'type' => 'zoom', 'expected_duration_minutes' => 60,
            'coach_joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function enroll(int $userId, int $courseId, int $batchId): void
    {
        DB::table('enrollments')->insert([
            'user_id' => $userId, 'course_id' => $courseId, 'has_access' => 1, 'batch_id' => $batchId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function markJoined(int $userId, int $liveClassId): void
    {
        DB::table('live_class_attendances')->insert([
            'course_live_class_id' => $liveClassId, 'user_id' => $userId, 'role' => 'student',
            'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_only_non_joined_same_batch_students_are_notified_and_isolated(): void
    {
        Notification::fake();

        $coachA = $this->user('instructor');
        $coachB = $this->user('instructor');

        $courseA = $this->course($coachA);
        $courseB = $this->course($coachB);
        $batchA1 = $this->batch($courseA);
        $batchA2 = $this->batch($courseA);
        $batchB1 = $this->batch($courseB);
        $lessonA = $this->lesson($courseA, $coachA);
        $lessonB = $this->lesson($courseB, $coachB);

        $sNotJoined = $this->user('student');                 // (1) same batch, not joined → SEND
        $sJoined    = $this->user('student');                 // (2) same batch, joined → skip
        $sOtherBatch = $this->user('student');                // (3) other batch → skip
        $sOtherCoach = $this->user('student');                // (4) other coach → skip
        $sInactive  = $this->user('student', 'deactive');     // (9) inactive → skip

        $classA1 = $this->liveClass($courseA, $batchA1, $lessonA);

        $this->enroll($sNotJoined, $courseA, $batchA1);
        $this->enroll($sJoined, $courseA, $batchA1);
        $this->markJoined($sJoined, $classA1);
        $this->enroll($sOtherBatch, $courseA, $batchA2);
        $this->enroll($sOtherCoach, $courseB, $batchB1);
        $this->enroll($sInactive, $courseA, $batchA1);

        (new NotifyLiveClassStarted($classA1))->handle(app(\App\Services\LiveClassNotificationService::class));

        Notification::assertSentTo(User::find($sNotJoined), LiveClassStartedToStudent::class);  // 1
        Notification::assertNotSentTo(User::find($sJoined), LiveClassStartedToStudent::class);   // 2
        Notification::assertNotSentTo(User::find($sOtherBatch), LiveClassStartedToStudent::class); // 3
        Notification::assertNotSentTo(User::find($sOtherCoach), LiveClassStartedToStudent::class); // 4
        Notification::assertNotSentTo(User::find($sInactive), LiveClassStartedToStudent::class);   // 9

        // Ledger logged the send.
        $this->assertDatabaseHas('live_class_start_notifications', [
            'live_class_id' => $classA1, 'student_id' => $sNotJoined, 'email_status' => 'sent',
        ]);
    }

    public function test_duplicate_start_does_not_resend(): void
    {
        Notification::fake();
        $coach = $this->user('instructor');
        $course = $this->course($coach);
        $batch = $this->batch($course);
        $lesson = $this->lesson($course, $coach);
        $student = $this->user('student');
        $class = $this->liveClass($course, $batch, $lesson);
        $this->enroll($student, $course, $batch);

        $svc = app(\App\Services\LiveClassNotificationService::class);
        (new NotifyLiveClassStarted($class))->handle($svc);  // first
        (new NotifyLiveClassStarted($class))->handle($svc);  // duplicate start event

        Notification::assertSentToTimes(User::find($student), LiveClassStartedToStudent::class, 1);  // (7)
        $this->assertSame(1, DB::table('live_class_start_notifications')->where('live_class_id', $class)->count());
    }

    public function test_concurrent_classes_stay_isolated(): void
    {
        Notification::fake();
        $coachA = $this->user('instructor');
        $coachB = $this->user('instructor');
        $courseA = $this->course($coachA);
        $courseB = $this->course($coachB);
        $batchA = $this->batch($courseA);
        $batchB = $this->batch($courseB);
        $classA = $this->liveClass($courseA, $batchA, $this->lesson($courseA, $coachA));
        $classB = $this->liveClass($courseB, $batchB, $this->lesson($courseB, $coachB));
        $sA = $this->user('student');
        $sB = $this->user('student');
        $this->enroll($sA, $courseA, $batchA);
        $this->enroll($sB, $courseB, $batchB);

        $svc = app(\App\Services\LiveClassNotificationService::class);
        (new NotifyLiveClassStarted($classA))->handle($svc);
        (new NotifyLiveClassStarted($classB))->handle($svc);

        // Each student only hears about THEIR class (10). Exactly one each.
        Notification::assertSentToTimes(User::find($sA), LiveClassStartedToStudent::class, 1);
        Notification::assertSentToTimes(User::find($sB), LiveClassStartedToStudent::class, 1);
    }

    public function test_join_url_points_to_coach_domain_not_platform(): void
    {
        // (5) + (6) — coach-domain join link + coach-scoped branding (coachId).
        $coach = $this->user('instructor');
        $course = $this->course($coach);
        $batch = $this->batch($course);
        $class = CourseLiveClass::find($this->liveClass($course, $batch, $this->lesson($course, $coach)));

        // Give the coach a custom host.
        DB::table('coach_domains')->insert([
            'coach_id' => $coach, 'hostname' => 'mycoach.example.com', 'status' => 'active', 'is_primary' => 1,
            'verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $n = new LiveClassStartedToStudent($class, 'My Course', $coach);
        $ref = new \ReflectionMethod($n, 'placeholders');
        $ref->setAccessible(true);
        $p = $ref->invoke($n, User::find($coach));

        $this->assertStringContainsString('mycoach.example.com', $p['join_url']);
        $this->assertStringNotContainsString('mbsguru.com', $p['join_url']);
    }
}
