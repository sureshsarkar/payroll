<?php

namespace Tests\Feature\Domain;

use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use ReflectionClass;
use Tests\TestCase;

/**
 * Domain test — certificate eligibility on course completion.
 *
 * Audit 2026-05-18: implementation of the previous skeleton.
 *
 * The shipped flow in this app:
 *
 *   - There is NO `certificates` table. Certificates are rendered on
 *     the fly by StudentDashboardController::downloadCertificate when
 *     the student hits /download-certificate/{id}.
 *   - Eligibility is decided by the private
 *     StudentDashboardController::meetsCertificateRequirements() —
 *     either 100% of CourseChapterItem rows watched (legacy lessons
 *     path) OR attendance ratio meets `course.attendance_threshold_percent`
 *     (live-class cohort path, audit 2026-05-12).
 *
 * These tests pin the eligibility contract by reflecting into that
 * private method. Going via the HTTP endpoint adds Dompdf rendering on
 * top, which is the wrong shape to assert in unit tests.
 */
class CertificateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fully_watched_recorded_course_is_eligible(): void
    {
        $coach  = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        [$courseId] = $this->seedCourse($coach->id);

        // Two chapter items → student watched both → 100% → eligible.
        $itemIds = $this->seedChapterItems($courseId, $coach->id, 2);
        $this->markWatched($student->id, $courseId, $itemIds);

        // 100% completed → meetsCertificateRequirements should be true
        // regardless of attendance_threshold_percent.
        $course = Course::find($courseId);
        $this->assertTrue(
            $this->meetsCertificateRequirements($course, 100.0, $student->id),
            'student with 100% lesson watch must be eligible'
        );
    }

    public function test_partial_watch_is_NOT_eligible_when_no_attendance_path(): void
    {
        $coach  = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        [$courseId] = $this->seedCourse($coach->id, ['attendance_threshold_percent' => 0]);

        // 1 of 2 items watched → 50% → not eligible (no attendance path).
        $itemIds = $this->seedChapterItems($courseId, $coach->id, 2);
        $this->markWatched($student->id, $courseId, [$itemIds[0]]);

        $course = Course::find($courseId);
        $this->assertFalse(
            $this->meetsCertificateRequirements($course, 50.0, $student->id),
            '50% watched + no attendance path must NOT be eligible'
        );
    }

    public function test_attendance_path_grants_eligibility_for_live_cohort(): void
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        [$courseId] = $this->seedCourse($coach->id, ['attendance_threshold_percent' => 80]);

        // Course has 5 live classes, student attended 4 (= 80%) → eligible.
        $liveClassIds = $this->seedLiveClasses($courseId, $coach->id, 5);
        foreach (array_slice($liveClassIds, 0, 4) as $lcId) {
            DB::table('live_class_attendances')->insert([
                'course_live_class_id' => $lcId,
                'user_id'              => $student->id,
                'role'                 => 'student',
                'joined_at'            => now(),
                'duration_seconds'     => 60 * 30,
                'attendance_verified'  => 1,
                'verified_at'          => now(),
                'is_manual'            => 0,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        $course = Course::find($courseId);
        $this->assertTrue(
            $this->meetsCertificateRequirements($course, 0.0, $student->id),
            'attended 4/5 live classes with 80% threshold must be eligible'
        );
    }

    public function test_attendance_path_below_threshold_is_NOT_eligible(): void
    {
        $coach   = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        [$courseId] = $this->seedCourse($coach->id, ['attendance_threshold_percent' => 80]);

        $liveClassIds = $this->seedLiveClasses($courseId, $coach->id, 5);
        // attended 2/5 = 40% < 80% threshold
        foreach (array_slice($liveClassIds, 0, 2) as $lcId) {
            DB::table('live_class_attendances')->insert([
                'course_live_class_id' => $lcId,
                'user_id'              => $student->id,
                'role'                 => 'student',
                'joined_at'            => now(),
                'duration_seconds'     => 60 * 30,
                'attendance_verified'  => 1,
                'verified_at'          => now(),
                'is_manual'            => 0,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        $course = Course::find($courseId);
        $this->assertFalse(
            $this->meetsCertificateRequirements($course, 0.0, $student->id),
            'attended 2/5 < 80% threshold must NOT be eligible'
        );
    }

    /* -------------------- fixtures + helpers -------------------- */

    private function seedCourse(int $coachId, array $extra = []): array
    {
        $courseId = DB::table('courses')->insertGetId(array_merge([
            'title'         => 'Cert test course '.uniqid(),
            'slug'          => 'cert-test-'.uniqid(),
            'instructor_id' => $coachId,
            'added_by'      => $coachId,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'price'         => 0, 'discount' => 0,
            'created_at'    => now(), 'updated_at' => now(),
        ], $extra));
        return [$courseId];
    }

    private function seedChapterItems(int $courseId, int $coachId, int $count): array
    {
        $chapterId = DB::table('course_chapters')->insertGetId([
            'title'      => 'Chapter '.uniqid(),
            'course_id'  => $courseId,
            'order'      => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $itemIds = [];
        for ($i = 0; $i < $count; $i++) {
            $itemIds[] = DB::table('course_chapter_items')->insertGetId([
                'chapter_id'    => $chapterId,
                'type'          => 'lesson',
                'order'         => $i + 1,
                'instructor_id' => $coachId,
                'created_at'    => now(), 'updated_at' => now(),
            ]);
        }
        return $itemIds;
    }

    private function markWatched(int $userId, int $courseId, array $chapterItemIds): void
    {
        $cols = collect(DB::select('SHOW COLUMNS FROM course_progress'))->pluck('Field')->all();
        foreach ($chapterItemIds as $itemId) {
            $row = [
                'user_id'    => $userId,
                'course_id'  => $courseId,
                'watched'    => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            // Different deployments use chapter_item_id vs course_chapter_item_id
            if (in_array('chapter_item_id', $cols, true))         $row['chapter_item_id'] = $itemId;
            if (in_array('course_chapter_item_id', $cols, true))  $row['course_chapter_item_id'] = $itemId;
            DB::table('course_progress')->insert($row);
        }
    }

    private function seedLiveClasses(int $courseId, int $coachId, int $count): array
    {
        // Live classes are anchored to a course_chapter_lessons row by
        // lesson_id (NOT NULL in this schema). Seed one lesson per class
        // so the FK constraint is honoured.
        $chapterId = DB::table('course_chapters')->insertGetId([
            'title'      => 'Live chapter '.uniqid(),
            'course_id'  => $courseId,
            'order'      => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $chapterItemId = DB::table('course_chapter_items')->insertGetId([
                'chapter_id'    => $chapterId,
                'type'          => 'live',
                'order'         => $i + 1,
                'instructor_id' => $coachId,
                'created_at'    => now(), 'updated_at' => now(),
            ]);
            $lessonId = DB::table('course_chapter_lessons')->insertGetId([
                'title'           => 'Live lesson '.($i + 1),
                'slug'            => 'live-lesson-'.uniqid(),
                'instructor_id'   => $coachId,
                'course_id'       => $courseId,
                'chapter_id'      => $chapterId,
                'chapter_item_id' => $chapterItemId,
                'storage'         => 'live',
                'file_type'       => 'live',
                'order'           => $i + 1,
                'is_free'         => 0,
                'status'          => 'active',
                'created_at'      => now(), 'updated_at' => now(),
            ]);
            $ids[] = DB::table('course_live_classes')->insertGetId([
                'course_id'  => $courseId,
                'lesson_id'  => $lessonId,
                'start_time' => (string) now()->subDays($count - $i),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $ids;
    }

    /**
     * Reflect into the private meetsCertificateRequirements() so we can
     * test the contract without rendering Dompdf or going through HTTP.
     */
    private function meetsCertificateRequirements(Course $course, float $pct, int $studentId): bool
    {
        \Illuminate\Support\Facades\Auth::guard('web')->loginUsingId($studentId);

        $controller = new \App\Http\Controllers\Frontend\StudentDashboardController();
        $ref = new ReflectionClass($controller);
        $m = $ref->getMethod('meetsCertificateRequirements');
        $m->setAccessible(true);
        return (bool) $m->invoke($controller, $course, $pct);
    }
}
