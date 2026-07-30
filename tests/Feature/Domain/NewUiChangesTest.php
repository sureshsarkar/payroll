<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\InstructorAnnouncementController;
use App\Http\Controllers\Frontend\InstructorDashboardController;
use App\Http\Controllers\Frontend\Coach\LandingPageEnquiryController;
use App\Http\Middleware\ResolveCoachByDomain;
use App\Models\CoachDomain;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 2026-07-10 — "New Changes for UI" doc.
 * Automation coverage for the access-control / logic items:
 *   #2 subdomain→custom-domain 301 redirect
 *   #3 dynamic panel tab titles
 *   #6 staff order scope (own courses only)
 *   #7 student listing status + search
 *   #8.1 staff enquiry visibility (assigned_to)
 *   #11 announcement course/batch scope for staff
 */
class NewUiChangesTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
    }

    private function staffFor(User $coach): User
    {
        return User::factory()->create(['role' => 'staff', 'coach_id' => $coach->id, 'added_by' => $coach->id]);
    }

    private function course(User $coach, string $type = 'live', ?int $addedBy = null): Course
    {
        $c = (new Course())->forceFill([
            'instructor_id' => $coach->id,
            'added_by'      => $addedBy ?? $coach->id,
            'title'         => 'C ' . uniqid(),
            'slug'          => 'c-' . uniqid(),
            'price'         => 0, 'status' => 'active', 'is_approved' => 'approved',
            'type'          => $type, 'coach_soft_delete' => 0,
        ]);
        $c->save();
        return $c;
    }

    private function batch(Course $course): CourseBatch
    {
        return CourseBatch::query()->forceCreate([
            'course_id' => $course->id, 'title' => 'B ' . uniqid(), 'status' => 'active',
            'start_date' => now(), 'end_date' => now()->addMonth(),
            'start_time' => '10:00', 'end_time' => '11:00', 'days' => ['Mon'], 'capacity' => 10,
        ]);
    }

    private function assign(User $coach, User $staff, CourseBatch $batch): void
    {
        TeacherBatchAssignment::query()->forceCreate([
            'coach_id' => $coach->id, 'teacher_id' => $staff->id,
            'course_id' => $batch->course_id, 'batch_id' => $batch->id,
            'permission_type' => 'manage', 'status' => 'active', 'assigned_at' => now(),
        ]);
        TeacherBatchAssignment::forgetCacheFor($staff->id);
    }

    /* ─────────────────────── #2 domain redirect ─────────────────────── */

    private function domain(User $coach, string $host, string $kind, string $status, bool $primary = false): CoachDomain
    {
        return CoachDomain::query()->forceCreate([
            'coach_id' => $coach->id, 'hostname' => $host, 'kind' => $kind,
            'is_primary' => $primary, 'verified_at' => now(), 'status' => $status,
        ]);
    }

    public function test_subdomain_301_redirects_to_active_custom_domain_preserving_path_and_query(): void
    {
        $coach = $this->coach();
        $this->domain($coach, 'slidus.mbsguru.com', 'subdomain', CoachDomain::STATUS_ACTIVE, false);
        $this->domain($coach, 'slidus.com', 'custom', CoachDomain::STATUS_ACTIVE, true);

        $mw  = new ResolveCoachByDomain();
        $req = Request::create('http://slidus.mbsguru.com/courses/yoga?course=10', 'GET');
        $res = $mw->handle($req, fn ($r) => response('next'));

        $this->assertSame(301, $res->getStatusCode());
        $this->assertSame('http://slidus.com/courses/yoga?course=10', $res->headers->get('Location'));
    }

    public function test_no_redirect_when_custom_domain_is_not_active(): void
    {
        $coach = $this->coach();
        $this->domain($coach, 'slidus.mbsguru.com', 'subdomain', CoachDomain::STATUS_ACTIVE, false);
        // Custom domain only VERIFIED (awaiting approval), not active/serving.
        $this->domain($coach, 'slidus.com', 'custom', CoachDomain::STATUS_VERIFIED, true);

        $mw  = new ResolveCoachByDomain();
        $req = Request::create('http://slidus.mbsguru.com/', 'GET');
        $res = $mw->handle($req, fn ($r) => response('next'));

        $this->assertSame('next', $res->getContent());
    }

    public function test_no_redirect_loop_when_already_on_custom_domain(): void
    {
        $coach = $this->coach();
        $this->domain($coach, 'slidus.mbsguru.com', 'subdomain', CoachDomain::STATUS_ACTIVE, false);
        $this->domain($coach, 'slidus.com', 'custom', CoachDomain::STATUS_ACTIVE, true);

        $mw  = new ResolveCoachByDomain();
        $req = Request::create('http://slidus.com/courses/yoga', 'GET');
        $res = $mw->handle($req, fn ($r) => response('next'));

        $this->assertSame('next', $res->getContent()); // no 301 away from the main domain
    }

    /* ─────────────────────── #3 tab titles ─────────────────────── */

    public function test_panel_module_title_prefers_explicit_and_derives_from_path(): void
    {
        $this->assertSame('Live Classes', panelModuleTitle('Live Classes'));

        app()->instance('request', Request::create('/instructor/coach-orders', 'GET'));
        $this->assertSame('Orders', panelModuleTitle());

        app()->instance('request', Request::create('/student/live-classes', 'GET'));
        $this->assertSame('Live Classes', panelModuleTitle());

        app()->instance('request', Request::create('/instructor', 'GET'));
        $this->assertSame('Coach Dashboard', panelModuleTitle());
    }

    /* ─────────────────────── #6 staff order scope ─────────────────────── */

    private function invokePrivate(object $obj, string $method, array $args = [])
    {
        $m = new \ReflectionMethod($obj, $method);
        $m->setAccessible(true);
        return $m->invoke($obj, ...$args);
    }

    public function test_order_scope_coach_sees_all_staff_sees_only_own_courses(): void
    {
        $coach = $this->coach();
        $staff = $this->staffFor($coach);
        $coachCourse = $this->course($coach, 'live');                       // authored by coach
        $staffCourse = $this->course($coach, 'live', addedBy: $staff->id);  // authored by staff

        $ctrl = app(InstructorDashboardController::class);

        $this->actingAs($coach, 'web');
        $coachIds = $this->invokePrivate($ctrl, 'orderScopeCourseIds');
        $this->assertContains($coachCourse->id, $coachIds);
        $this->assertContains($staffCourse->id, $coachIds, 'coach sees every tenant course');

        $this->actingAs($staff, 'web');
        $staffIds = $this->invokePrivate($ctrl, 'orderScopeCourseIds');
        $this->assertContains($staffCourse->id, $staffIds);
        $this->assertNotContains($coachCourse->id, $staffIds, 'staff sees ONLY courses they authored');
    }

    /* ─────────────────────── #11 announcement scope ─────────────────────── */

    public function test_announcement_course_query_limits_staff_to_assigned_batch_courses(): void
    {
        $coach = $this->coach();
        $staff = $this->staffFor($coach);

        $assignedCourse   = $this->course($coach, 'live');
        $assignedBatch    = $this->batch($assignedCourse);
        $this->assign($coach, $staff, $assignedBatch);

        $unassignedCourse = $this->course($coach, 'live');
        $this->batch($unassignedCourse); // has a batch, but not assigned to staff

        $ctrl = app(InstructorAnnouncementController::class);

        // Coach: sees both live courses.
        $this->actingAs($coach, 'web');
        $coachCourseIds = $this->invokePrivate($ctrl, 'announcementCourseQuery', [$coach->id])->pluck('id')->all();
        $this->assertContains($assignedCourse->id, $coachCourseIds);
        $this->assertContains($unassignedCourse->id, $coachCourseIds);

        // Staff: only the course whose batch is assigned to them.
        $this->actingAs($staff, 'web');
        $staffCourseIds = $this->invokePrivate($ctrl, 'announcementCourseQuery', [$coach->id])->pluck('id')->all();
        $this->assertContains($assignedCourse->id, $staffCourseIds);
        $this->assertNotContains($unassignedCourse->id, $staffCourseIds);
    }

    public function test_announcement_deny_unassigned_batches_blocks_staff_but_allows_coach(): void
    {
        $coach = $this->coach();
        $staff = $this->staffFor($coach);
        $course = $this->course($coach, 'live');
        $assignedBatch   = $this->batch($course);
        $unassignedBatch = $this->batch($course);
        $this->assign($coach, $staff, $assignedBatch);

        $ctrl = app(InstructorAnnouncementController::class);

        // Coach — never blocked (null returned).
        $this->actingAs($coach, 'web');
        $this->assertNull($this->invokePrivate($ctrl, 'denyUnassignedBatches', [collect([$unassignedBatch->id])]));

        // Staff — assigned batch allowed, unassigned blocked (redirect returned).
        $this->actingAs($staff, 'web');
        $this->assertNull($this->invokePrivate($ctrl, 'denyUnassignedBatches', [collect([$assignedBatch->id])]));
        $this->assertNotNull($this->invokePrivate($ctrl, 'denyUnassignedBatches', [collect([$unassignedBatch->id])]));
    }

    /* ─────────────────────── #8.1 enquiry scope ─────────────────────── */

    public function test_enquiry_scope_restricts_staff_to_assigned_leads(): void
    {
        $coach = $this->coach();
        $staff = $this->staffFor($coach);
        $other = $this->staffFor($coach);

        \Illuminate\Support\Facades\DB::table('landing_page_enquiries')->insert([
            ['coach_id' => $coach->id, 'added_by' => $coach->id, 'first_name' => 'Mine',  'email' => 'a@x.test', 'status' => 'new', 'assigned_to' => $staff->id, 'created_at' => now(), 'updated_at' => now()],
            ['coach_id' => $coach->id, 'added_by' => $coach->id, 'first_name' => 'Other', 'email' => 'b@x.test', 'status' => 'new', 'assigned_to' => $other->id, 'created_at' => now(), 'updated_at' => now()],
            ['coach_id' => $coach->id, 'added_by' => $coach->id, 'first_name' => 'None',  'email' => 'c@x.test', 'status' => 'new', 'assigned_to' => null,        'created_at' => now(), 'updated_at' => now()],
        ]);

        $ctrl  = app(LandingPageEnquiryController::class);
        $model = new \App\Models\LandingPageEnquiry();

        // Coach — sees all 3 of their tenant's leads.
        $this->actingAs($coach, 'web');
        $coachCount = $this->invokePrivate($ctrl, 'scopeAssignedForStaff', [$model->newQuery()->where('coach_id', $coach->id)])->count();
        $this->assertSame(3, $coachCount);

        // Staff — sees only the 1 assigned to them.
        $this->actingAs($staff, 'web');
        $q = $this->invokePrivate($ctrl, 'scopeAssignedForStaff', [$model->newQuery()->where('coach_id', $coach->id)]);
        $this->assertSame(1, $q->count());
        $this->assertSame('Mine', $q->first()->first_name);
    }

    /* ─────────────────────── #7 student filter ─────────────────────── */

    public function test_student_listing_status_filter_and_search(): void
    {
        $coach = $this->coach();

        $active   = User::factory()->create(['role' => 'student', 'name' => 'Ann Active',   'email' => 'ann@x.test',   'phone' => '111', 'status' => 'active',   'is_banned' => 'no']);
        $inactive = User::factory()->create(['role' => 'student', 'name' => 'Ivan Inactive', 'email' => 'ivan@x.test',  'phone' => '222', 'status' => 'deactive', 'is_banned' => 'no']);
        $blocked  = User::factory()->create(['role' => 'student', 'name' => 'Bob Blocked',   'email' => 'bob@x.test',   'phone' => '333', 'status' => 'active',   'is_banned' => 'yes']);

        foreach ([$active, $inactive, $blocked] as $s) {
            \App\Models\CoachStudentLink::link($coach->id, $s->id);
        }

        $this->actingAs($coach, 'web');
        $ctrl = app(InstructorDashboardController::class);

        $names = fn ($req) => collect($ctrl->myStudents($req)->getData()['mystudents']->items())->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Ann Active', 'Ivan Inactive', 'Bob Blocked'], $names(Request::create('/', 'GET')));
        $this->assertSame(['Ann Active'],   $names(Request::create('/', 'GET', ['status' => 'active'])));
        $this->assertSame(['Ivan Inactive'], $names(Request::create('/', 'GET', ['status' => 'inactive'])));
        $this->assertSame(['Bob Blocked'],  $names(Request::create('/', 'GET', ['status' => 'blocked'])));
        $this->assertSame(['Bob Blocked'],  $names(Request::create('/', 'GET', ['q' => 'bob@x'])));   // email search
        $this->assertSame(['Ivan Inactive'], $names(Request::create('/', 'GET', ['q' => '222'])));      // phone search
    }
}
