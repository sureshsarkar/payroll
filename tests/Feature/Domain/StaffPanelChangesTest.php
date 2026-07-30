<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\CoachAnalyticsController;
use App\Http\Controllers\Frontend\Coach\LiveClassController;
use App\Http\Controllers\Frontend\InstructorAnnouncementController;
use App\Http\Controllers\Frontend\InstructorCourseController;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 2026-07-10 — Staff Panel changes doc.
 * Covers the "recorded courses excluded from batch/live-class/announcement
 * dropdowns" rule and the Analytics custom date-range filter. (Staff-authorship
 * visibility is covered end-to-end with a real staff user.)
 */
class StaffPanelChangesTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
    }

    private function course(User $coach, string $title, string $type): Course
    {
        $c = (new Course())->forceFill([
            'instructor_id' => $coach->id, 'added_by' => $coach->id, 'title' => $title,
            'slug' => 'c-' . uniqid(), 'price' => 0, 'status' => 'active', 'is_approved' => 'approved',
            'type' => $type, 'coach_soft_delete' => 0,
        ]);
        $c->save();

        return $c;
    }

    /** Seed one of each course type and return their titles by type. */
    private function seedCourses(User $coach): array
    {
        return [
            'live'     => $this->course($coach, 'Live Yoga', 'live')->title,
            'hybrid'   => $this->course($coach, 'Hybrid Yoga', 'hybrid')->title,
            'recorded' => $this->course($coach, 'Recorded Yoga', 'recorded')->title,
        ];
    }

    private function courseTitles($collection): array
    {
        return collect($collection)->pluck('title')->all();
    }

    public function test_batch_create_dropdown_excludes_recorded_courses(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $t = $this->seedCourses($coach);

        $view = app(InstructorCourseController::class)->batchesIndex(Request::create('/', 'GET'), null);
        $titles = $this->courseTitles($view->getData()['batchCourses']);

        $this->assertContains($t['live'], $titles);
        $this->assertContains($t['hybrid'], $titles);
        $this->assertNotContains($t['recorded'], $titles, 'recorded courses have no batches');
    }

    public function test_live_class_dropdown_excludes_recorded_courses(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $t = $this->seedCourses($coach);

        $view = app(LiveClassController::class)->index();
        $titles = $this->courseTitles($view->getData()['courses']);

        $this->assertContains($t['live'], $titles);
        $this->assertContains($t['hybrid'], $titles);
        $this->assertNotContains($t['recorded'], $titles, 'recorded courses have no live classes');
    }

    public function test_announcement_create_dropdown_excludes_recorded_courses(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');
        $t = $this->seedCourses($coach);

        $view = app(InstructorAnnouncementController::class)->create();
        $titles = $this->courseTitles($view->getData()['courses']);

        $this->assertContains($t['live'], $titles);
        $this->assertContains($t['hybrid'], $titles);
        $this->assertNotContains($t['recorded'], $titles, 'announcements need a batch; recorded has none');
    }

    public function test_analytics_accepts_a_custom_date_range(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');

        $req = Request::create('/instructor/analytics', 'GET', ['from' => '2026-06-01', 'to' => '2026-06-30']);
        $data = app(CoachAnalyticsController::class)->index($req)->getData();

        $this->assertTrue($data['isCustom'], 'a valid from/to switches to custom mode');
        $this->assertSame('2026-06-01', $data['from']);
        $this->assertSame('2026-06-30', $data['to']);
        // 30 daily buckets for June (inclusive).
        $this->assertCount(30, $data['chartValues']);
    }

    public function test_analytics_ignores_an_invalid_custom_range_and_falls_back(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach, 'web');

        $req = Request::create('/instructor/analytics', 'GET', ['from' => 'garbage', 'to' => '']);
        $data = app(CoachAnalyticsController::class)->index($req)->getData();

        $this->assertFalse($data['isCustom']);
        $this->assertSame(30, $data['range']); // default window
    }

    /* ── sidebar counts are staff-scoped, not coach-scoped ─────────── */

    private function batchCount(): int
    {
        $asp = new \App\Providers\AppServiceProvider(app());
        $m = new \ReflectionMethod($asp, 'getCoachActiveBatchCount');
        $m->setAccessible(true);
        return (int) $m->invoke($asp);
    }

    private function enquiryCount(): int
    {
        $asp = new \App\Providers\AppServiceProvider(app());
        $m = new \ReflectionMethod($asp, 'getCoachEnquiriesNewCount');
        $m->setAccessible(true);
        return (int) $m->invoke($asp);
    }

    public function test_sidebar_batch_count_is_staff_scoped(): void
    {
        $coach = $this->coach();
        $staff = User::factory()->create(['role' => 'staff', 'coach_id' => $coach->id, 'added_by' => $coach->id]);

        // A batch under the COACH's own course.
        $coachCourse = $this->course($coach, 'Coach Course', 'live');
        \App\Models\CourseBatch::query()->forceCreate(['course_id' => $coachCourse->id, 'title' => 'CB', 'status' => 'active', 'start_date' => now(), 'end_date' => now()->addMonth(), 'start_time' => '10:00', 'end_time' => '11:00', 'days' => ['Mon'], 'capacity' => 10]);
        // A batch under a STAFF-authored course.
        $staffCourse = (new Course())->forceFill(['instructor_id' => $coach->id, 'added_by' => $staff->id, 'title' => 'Staff Course', 'slug' => 's-' . uniqid(), 'price' => 0, 'status' => 'active', 'is_approved' => 'approved', 'type' => 'live', 'coach_soft_delete' => 0]);
        $staffCourse->save();
        \App\Models\CourseBatch::query()->forceCreate(['course_id' => $staffCourse->id, 'title' => 'SB', 'status' => 'active', 'start_date' => now(), 'end_date' => now()->addMonth(), 'start_time' => '10:00', 'end_time' => '11:00', 'days' => ['Tue'], 'capacity' => 10]);

        $this->actingAs($coach, 'web');
        $this->assertSame(2, $this->batchCount(), 'coach sees all their active batches');

        $this->actingAs($staff, 'web');
        $this->assertSame(1, $this->batchCount(), 'staff sees only the batch under a course they authored');
    }

    public function test_sidebar_enquiry_count_is_staff_scoped(): void
    {
        $coach = $this->coach();
        $staff = User::factory()->create(['role' => 'staff', 'coach_id' => $coach->id, 'added_by' => $coach->id]);

        // An untargeted (no product_id) coach enquiry.
        \Illuminate\Support\Facades\DB::table('landing_page_enquiries')->insert([
            'coach_id' => $coach->id, 'added_by' => $coach->id, 'first_name' => 'Lead', 'email' => 'l@x.test',
            'status' => 'new', 'product_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($coach, 'web');
        $this->assertSame(1, $this->enquiryCount(), 'coach sees their new enquiry');

        $this->actingAs($staff, 'web');
        $this->assertSame(0, $this->enquiryCount(), 'staff with no assigned-batch courses sees 0 (untargeted lead is coach-only)');
    }
}
