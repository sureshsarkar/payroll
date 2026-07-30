<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\LiveClassController;
use App\Http\Controllers\Frontend\InstructorCourseController;
use App\Models\CoachStaff;
use App\Models\CourseChapterLesson;
use App\Models\CourseLiveClass;
use App\Models\TeacherBatchAssignment;
use App\Models\User;
use App\Models\ZoomCredential;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Role Permission Test" doc (2026-07-06) — the four FUNCTIONAL bugs.
 *
 *   Bug 2  live-class create batch dropdown must be scoped to the staff's
 *          assigned batches (coach still sees all).
 *   Bug 3  the batch payload carries a ready-to-use datetime-local so the
 *          form can auto-fill Start Time from the chosen batch.
 *   Bug 4  a live class created by staff is owned by the COACH (not the acting
 *          staff), so it is visible in the coach-scoped list.
 *
 * (Bug 1 — thumbnail 404 on a coach subdomain — is a routing fix verified by
 * the reserved-slug list in routes/web.php; there is no local wildcard-DNS
 * host to exercise it in-process, so it is covered by the route unit assertion
 * test_filemanager_slugs_are_reserved_on_coach_subdomains below.)
 */
class RolePermissionDocFunctionalTest extends TestCase
{
    use DatabaseTransactions;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
    }

    private function makeCourse(): int
    {
        return DB::table('courses')->insertGetId([
            'title' => 'C ' . uniqid(), 'slug' => 'c-' . uniqid(),
            'instructor_id' => $this->coach->id, 'added_by' => $this->coach->id,
            'is_approved' => 'approved', 'status' => 'active', 'type' => 'live',
            'price' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeBatch(int $courseId, string $title, ?string $startTime, ?string $startDate): int
    {
        return DB::table('course_batches')->insertGetId([
            'course_id' => $courseId, 'title' => $title,
            'start_time' => $startTime, 'start_date' => $startDate,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeStaff(array $assignedBatchIds): CoachStaff
    {
        $staff = CoachStaff::find(DB::table('users')->insertGetId([
            'role' => 'Manager', 'name' => 'Teacher', 'email' => 't' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'coach_id' => $this->coach->id, 'added_by' => $this->coach->id,
            'created_at' => now(), 'updated_at' => now(),
        ]));
        foreach ($assignedBatchIds as $bId) {
            TeacherBatchAssignment::create([
                'coach_id' => $this->coach->id, 'teacher_id' => $staff->id,
                'course_id' => DB::table('course_batches')->where('id', $bId)->value('course_id'),
                'batch_id' => $bId, 'status' => 'active', 'assigned_at' => now(),
            ]);
        }
        TeacherBatchAssignment::forgetCacheFor($staff->id);
        return $staff;
    }

    private function batchesFor(int $courseId): array
    {
        return app(InstructorCourseController::class)->getBatchesByCourse($courseId)->getData(true)['batches'];
    }

    /* ── Bug 2 · batch scope ─────────────────────────────────────────── */

    public function test_coach_sees_all_batches_of_the_course(): void
    {
        $courseId = $this->makeCourse();
        $b1 = $this->makeBatch($courseId, 'Batch A', '09:30:00', '2026-07-10');
        $b2 = $this->makeBatch($courseId, 'Batch B', '18:00:00', '2026-07-11');

        Auth::guard('web')->login($this->coach);
        $ids = collect($this->batchesFor($courseId))->pluck('id')->all();

        $this->assertContains($b1, $ids);
        $this->assertContains($b2, $ids, 'coach must see every batch');
    }

    public function test_staff_sees_only_their_assigned_batches(): void
    {
        $courseId = $this->makeCourse();
        $assigned = $this->makeBatch($courseId, 'Assigned', '09:30:00', '2026-07-10');
        $other    = $this->makeBatch($courseId, 'Not assigned', '18:00:00', '2026-07-11');
        $staff = $this->makeStaff([$assigned]);

        Auth::guard('web')->login($staff);
        $ids = collect($this->batchesFor($courseId))->pluck('id')->all();

        $this->assertContains($assigned, $ids);
        $this->assertNotContains($other, $ids, 'staff must NOT see a batch they were not assigned');
    }

    /* ── Bug 3 · auto-fill datetime in the payload ───────────────────── */

    public function test_batch_payload_exposes_autofill_start_datetime(): void
    {
        $courseId = $this->makeCourse();
        $b1 = $this->makeBatch($courseId, 'Timed', '09:30:00', '2026-07-10');

        Auth::guard('web')->login($this->coach);
        $byId = collect($this->batchesFor($courseId))->keyBy('id');

        // Format is exactly what a <input type="datetime-local"> expects, so the
        // form JS can drop it straight into #start_time (still editable).
        $this->assertSame('2026-07-10T09:30', $byId[$b1]['start_datetime_local'], 'date + time combined for the form');
    }

    /* ── Bug 4 · staff-created class owned by coach & visible ────────── */

    public function test_staff_created_live_class_is_owned_by_the_coach_and_visible(): void
    {
        Http::fake([
            'api.zoom.us/*' => Http::response(['id' => '9998887776', 'join_url' => 'https://zoom.us/j/9998887776'], 201),
        ]);

        $courseId = $this->makeCourse();
        $batchId  = $this->makeBatch($courseId, 'B', '10:00:00', '2026-07-10');
        DB::table('course_chapters')->insert([
            'title' => 'Ch', 'course_id' => $courseId, 'order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $staff = $this->makeStaff([$batchId]);

        // Fresh cached S2S token → no OAuth HTTP; only the meeting call is faked.
        ZoomCredential::create([
            'instructor_id' => $this->coach->id, 'account_id' => 'acc', 'client_id' => 'cid',
            'client_secret' => 'sec', 'zoom_access_token' => 'tok', 'zoom_token_expires_at' => now()->addHour(),
        ]);

        Auth::guard('web')->login($staff);
        $req = Request::create('/x', 'POST', [
            'live_class_title' => 'Staff Session ' . uniqid(),
            'course_id' => $courseId, 'batch_id' => $batchId,
            'live_type' => 'zoom', 'start_time' => now()->addDay()->format('Y-m-d\TH:i'), 'duration' => 60,
        ]);
        $title = $req->input('live_class_title');

        $res = app(LiveClassController::class)->store($req);
        $this->assertContains($res->getStatusCode(), [200, 201], 'store should succeed: ' . $res->getContent());

        $lesson = CourseChapterLesson::where('title', $title)->first();
        $this->assertNotNull($lesson, 'lesson row created');
        $this->assertSame(
            (int) $this->coach->id, (int) $lesson->instructor_id,
            'BUG 4: lesson must be owned by the coach, not the acting staff member'
        );

        // The coach-scoped list query (what index() runs) now finds it.
        $visible = CourseLiveClass::whereHas('lesson', fn ($q) => $q->where('instructor_id', $this->coach->id))
            ->where('batch_id', $batchId)
            ->where('lesson_id', $lesson->id)
            ->exists();
        $this->assertTrue($visible, 'staff-created class must appear in the coach-scoped live-class list');
    }

    /* ── Bug 1 · filemanager slugs reserved on coach subdomains ──────── */

    public function test_filemanager_slugs_are_reserved_on_coach_subdomains(): void
    {
        // The coach-subdomain catch-all is only registered when app.coach_domain
        // is a real dotted host. Assert the reserved-slug guard covers the file
        // manager entry points so thumbnail upload doesn't 404 as a coach page.
        $web = file_get_contents(base_path('routes/web.php'));
        $this->assertMatchesRegularExpression(
            "/'frontend-filemanager'\s*,\s*'laravel-filemanager'/",
            $web,
            'both file-manager slugs must be in $coachReservedSlugs'
        );
    }
}
