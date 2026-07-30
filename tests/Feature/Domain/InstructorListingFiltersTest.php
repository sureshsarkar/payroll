<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\LiveClassController;
use App\Http\Controllers\Frontend\InstructorCourseController;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseChapter;
use App\Models\CourseChapterLesson;
use App\Models\CourseLiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 2026-07-09 (Some Changes.docx) — Course-Batches & Live-Classes listing filters
 * + search. Server-side, tenant-scoped. Also guards the Analytics currency fix.
 */
class InstructorListingFiltersTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
    }

    private function course(User $coach, string $title, string $type = 'live'): Course
    {
        $c = (new Course())->forceFill([
            'instructor_id' => $coach->id, 'added_by' => $coach->id, 'title' => $title,
            'slug' => 'c-' . uniqid(), 'price' => 0, 'status' => 'active',
            'is_approved' => 'approved', 'type' => $type, 'coach_soft_delete' => 0,
        ]);
        $c->save();

        return $c;
    }

    private function batch(Course $course, string $title, string $status, ?string $createdAt = null): CourseBatch
    {
        $b = (new CourseBatch())->forceFill([
            'course_id' => $course->id, 'title' => $title, 'status' => $status,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString(),
            'start_time' => '10:00', 'end_time' => '11:00', 'capacity' => 20,
            'created_at' => $createdAt ?: now(), 'updated_at' => now(),
        ]);
        $b->save();

        return $b;
    }

    private function lesson(Course $course, string $title): CourseChapterLesson
    {
        $chapter = (new CourseChapter())->forceFill([
            'instructor_id' => $course->instructor_id, 'course_id' => $course->id,
            'title' => 'Ch', 'order' => 1, 'status' => 'active',
        ]);
        $chapter->save();
        $l = (new CourseChapterLesson())->forceFill([
            'title' => $title, 'instructor_id' => $course->instructor_id, 'course_id' => $course->id,
            'chapter_id' => $chapter->id, 'duration' => 60, 'storage' => 'live', 'file_type' => 'live',
        ]);
        $l->save();

        return $l;
    }

    private function liveClass(Course $course, CourseChapterLesson $lesson, $startTime, ?string $endedAt = null, int $dur = 60): CourseLiveClass
    {
        $lc = (new CourseLiveClass())->forceFill([
            'course_id' => $course->id, 'lesson_id' => $lesson->id, 'start_time' => $startTime,
            'ended_at' => $endedAt, 'expected_duration_minutes' => $dur, 'type' => 'zoom',
        ]);
        $lc->save();

        return $lc;
    }

    /** @return \Illuminate\Support\Collection batch titles visible for this filter */
    private function batchTitles(array $query = []): array
    {
        $req = Request::create('/instructor/course-batches', 'GET', $query);
        $courses = app(InstructorCourseController::class)->batchesIndex($req, null)->getData()['courses'];

        return $courses->flatMap(fn ($c) => $c->batches->pluck('title'))->all();
    }

    private function liveTitles(array $query = []): array
    {
        request()->replace($query);
        $classes = app(LiveClassController::class)->index()->getData()['liveClasses'];

        return $classes->map(fn ($lc) => $lc->lesson?->title)->all();
    }

    /* ── Course Batches ─────────────────────────────────────────────── */

    public function test_batches_search_by_batch_name(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $course = $this->course($coach, 'Yoga Course');
        $this->batch($course, 'Morning Batch', 'active');
        $this->batch($course, 'Evening Batch', 'active');

        $titles = $this->batchTitles(['q' => 'Morning']);
        $this->assertContains('Morning Batch', $titles);
        $this->assertNotContains('Evening Batch', $titles);
    }

    public function test_batches_search_by_course_name(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $yoga = $this->course($coach, 'Yoga Course');
        $java = $this->course($coach, 'Java Course');
        $this->batch($yoga, 'B1', 'active');
        $this->batch($java, 'B2', 'active');

        $titles = $this->batchTitles(['q' => 'Yoga']);
        $this->assertContains('B1', $titles);
        $this->assertNotContains('B2', $titles);
    }

    public function test_batches_status_filter(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $course = $this->course($coach, 'C');
        $this->batch($course, 'Active One', 'active');
        $this->batch($course, 'Inactive One', 'inactive');

        $this->assertSame(['Active One'], $this->batchTitles(['status' => 'active']));
        $this->assertSame(['Inactive One'], $this->batchTitles(['status' => 'inactive']));
    }

    public function test_batches_date_filter_by_creation_date(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $course = $this->course($coach, 'C');
        $this->batch($course, 'Old Batch', 'active', now()->subDays(10)->toDateTimeString());
        $this->batch($course, 'Today Batch', 'active', now()->toDateTimeString());

        $titles = $this->batchTitles(['date' => now()->toDateString()]);
        $this->assertContains('Today Batch', $titles);
        $this->assertNotContains('Old Batch', $titles);
    }

    public function test_batches_are_tenant_scoped(): void
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $courseB = $this->course($coachB, 'B Course');
        $this->batch($courseB, 'B Secret Batch', 'active');

        $this->actingAs($coachA, 'web');
        $titles = $this->batchTitles(['q' => 'Secret']);
        $this->assertNotContains('B Secret Batch', $titles, 'coach A must never see coach B batches');
    }

    /* ── Live Classes ───────────────────────────────────────────────── */

    public function test_live_classes_search_by_course_or_title(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $yoga = $this->course($coach, 'Yoga Course');
        $lessonA = $this->lesson($yoga, 'Advanced Java Live');
        $lessonB = $this->lesson($yoga, 'Meditation Basics');
        $this->liveClass($yoga, $lessonA, now()->addDay());
        $this->liveClass($yoga, $lessonB, now()->addDay());

        $this->assertSame(['Advanced Java Live'], $this->liveTitles(['q' => 'Java']));      // by title
        $found = $this->liveTitles(['q' => 'Yoga']);                                          // by course
        $this->assertContains('Advanced Java Live', $found);
        $this->assertContains('Meditation Basics', $found);
    }

    public function test_live_classes_date_filter(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $course = $this->course($coach, 'C');
        $lesson = $this->lesson($course, 'L');
        $this->liveClass($course, $lesson, now()->addDays(3)->setTime(10, 0)); // future date
        $todayLesson = $this->lesson($course, 'Today L');
        $this->liveClass($course, $todayLesson, now()->setTime(9, 0));

        $titles = $this->liveTitles(['date' => now()->toDateString()]);
        $this->assertContains('Today L', $titles);
        $this->assertNotContains('L', $titles);
    }

    public function test_live_classes_status_filter(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $course = $this->course($coach, 'C');
        $scheduled = $this->lesson($course, 'Scheduled Class');
        $this->liveClass($course, $scheduled, now()->addDays(2));
        $live = $this->lesson($course, 'Live Now Class');
        $this->liveClass($course, $live, now()->subMinutes(10), null, 60);       // started, not over
        $completed = $this->lesson($course, 'Done Class');
        $this->liveClass($course, $completed, now()->subHours(5), null, 60);     // long over

        $this->assertSame(['Scheduled Class'], $this->liveTitles(['status' => 'scheduled']));
        $this->assertSame(['Live Now Class'], $this->liveTitles(['status' => 'live']));
        $this->assertSame(['Done Class'], $this->liveTitles(['status' => 'completed']));
    }

    /* ── Analytics currency (source guard) ──────────────────────────── */

    public function test_analytics_chart_uses_dynamic_currency_not_hardcoded_dollar(): void
    {
        $src = file_get_contents(resource_path('views/frontend/instructor-dashboard/analytics/index.blade.php'));
        $this->assertStringContainsString('const CUR = @json($curIcon)', $src, 'chart reads the platform currency symbol');
        $this->assertStringNotContainsString("'\$' + Number(ctx.parsed.y)", $src, 'no hardcoded $ in the revenue tooltip');
        $this->assertStringNotContainsString("'\$' + (v >= 1000", $src, 'no hardcoded $ on the axis');
    }
}
