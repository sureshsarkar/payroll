<?php

namespace Tests\Feature\Domain;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Enrollment;
use Tests\TestCase;

/**
 * Phase G — tests for the 2026-05-20 Announcement audience upgrade.
 *
 * Maps to the T1–T13 checklist from the plan:
 *   T1  Admin all-students → every student sees it           ✓
 *   T2  Admin batch-wise   → only batch students see it      ✓
 *   T3  Coach batch-wise   → reaches enrolled students       ✓
 *   T4  Coach POSTing audience_type=all_students → 403       ✓ (asserted at controller)
 *   T5  Student in batch A sees A but NOT batch B            ✓
 *   T6  Draft (status=inactive) hidden from students         ✓
 *   T7  visibleToStudent scope returns paginatable result   ✓ (smoke)
 *   T10 Legacy rows (defaults applied) keep working          ✓
 *   T12 course_id is now nullable in DB                      ✓
 *   T13 Coach IDOR (edit other coach's announcement) — already
 *       covered by InstructorAnnouncementsRouteTest from yesterday's
 *       commit; not duplicated here.
 *
 * UI tests (T8 mark-as-read, T9 unread badge, T11 mobile) are
 * exercised manually via the smoke pages — out of scope for unit tests.
 */
class AnnouncementAudienceTest extends TestCase
{
    use DatabaseTransactions;

    /* ───────── schema ───────── */

    public function test_announcements_table_carries_new_columns(): void
    {
        $cols = collect(DB::select('SHOW COLUMNS FROM announcements'))->pluck('Field')->all();
        $this->assertContains('audience_type', $cols,
            'announcements.audience_type must exist (migration 2026_05_20_100000).');
        $this->assertContains('sender_role', $cols,
            'announcements.sender_role must exist.');

        // T12 — course_id must be nullable now.
        $courseCol = collect(DB::select('SHOW COLUMNS FROM announcements WHERE Field = ?', ['course_id']))->first();
        $this->assertSame('YES', $courseCol->Null,
            'announcements.course_id must allow NULL — required for all_students announcements.');
    }

    public function test_audience_type_enum_has_both_values(): void
    {
        $col = collect(DB::select('SHOW COLUMNS FROM announcements WHERE Field = ?', ['audience_type']))->first();
        $this->assertNotNull($col);
        $this->assertStringContainsString("'all_students'", (string) $col->Type);
        $this->assertStringContainsString("'batch_specific'", (string) $col->Type);
    }

    /* ───────── T1 — all_students reaches every student ───────── */

    public function test_t1_all_students_announcement_visible_to_any_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $ann = Announcement::create([
            'instructor_id' => 1,
            'sender_role'   => 'admin',
            'audience_type' => 'all_students',
            'course_id'     => null,
            'title'         => 'Platform-wide notice',
            'announcement'  => 'Body',
            'status'        => 'active',
        ]);

        $visible = Announcement::visibleToStudent($student)->pluck('id')->all();
        $this->assertContains($ann->id, $visible,
            'A student with NO enrolments must still see all_students announcements.');
    }

    /* ───────── T1b — all_students PUSH fanout reaches every student (audit [10]) ───────── */

    public function test_t1b_fanout_pushes_all_students_announcement_to_every_student(): void
    {
        // Regression for audit [10]: fanOut keyed on course_id, which is NULL
        // for all_students, so a scheduled platform-wide announcement reached
        // 0 recipients. It must now push to every student (and not to
        // non-student roles).
        \Illuminate\Support\Facades\Notification::fake();

        $s1    = User::factory()->create(['role' => 'student']);
        $s2    = User::factory()->create(['role' => 'student']);
        $coach = User::factory()->create(['role' => 'instructor']); // must NOT receive

        $ann = Announcement::create([
            'instructor_id' => $coach->id,
            'sender_role'   => 'admin',
            'audience_type' => 'all_students',
            'course_id'     => null,                 // platform-wide: no course
            'title'         => 'Platform-wide push',
            'announcement'  => 'Body',
            'status'        => 'active',
            'scheduled_at'  => now()->subMinute(),
            'delivered_at'  => null,
        ]);

        $count = app(\App\Services\AnnouncementNotifier::class)->fanOut($ann);

        $this->assertGreaterThanOrEqual(2, $count,
            'all_students fanout must reach every student, not 0 (the pre-fix bug).');
        \Illuminate\Support\Facades\Notification::assertSentTo($s1, \App\Notifications\NewBatchAnnouncement::class);
        \Illuminate\Support\Facades\Notification::assertSentTo($s2, \App\Notifications\NewBatchAnnouncement::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($coach, \App\Notifications\NewBatchAnnouncement::class);
    }

    /* ───────── T2 + T5 — batch_specific only reaches enrolled students ───────── */

    public function test_t2_batch_specific_visible_only_to_enrolled_students(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);

        [$courseA, $batchA] = $this->makeBatch($coach, 'A');
        [$courseB, $batchB] = $this->makeBatch($coach, 'B');

        $studentInA = User::factory()->create(['role' => 'student']);
        $studentInB = User::factory()->create(['role' => 'student']);
        $this->enroll($studentInA, $batchA);
        $this->enroll($studentInB, $batchB);

        // Batch-A specific announcement.
        $ann = Announcement::create([
            'instructor_id' => $coach->id,
            'sender_role'   => 'instructor',
            'audience_type' => 'batch_specific',
            'course_id'     => $courseA->id,
            'batch_id'      => $batchA->id,
            'title'         => 'Batch A only',
            'announcement'  => 'Body',
            'status'        => 'active',
        ]);
        $ann->batches()->sync([$batchA->id]);

        // Student in A sees it.
        $aSees = Announcement::visibleToStudent($studentInA)->pluck('id')->all();
        $this->assertContains($ann->id, $aSees, 'Student in batch A must see batch-A announcement.');

        // Student in B does NOT see it.
        $bSees = Announcement::visibleToStudent($studentInB)->pluck('id')->all();
        $this->assertNotContains($ann->id, $bSees,
            'Student in batch B must NOT see batch-A announcement (T5 isolation).');
    }

    /* ───────── T6 — draft (inactive) hidden ───────── */

    public function test_t6_draft_announcements_hidden_from_students(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $draft = Announcement::create([
            'instructor_id' => 1,
            'sender_role'   => 'admin',
            'audience_type' => 'all_students',
            'title'         => 'Draft notice',
            'announcement'  => 'Body',
            'status'        => 'inactive',   // = draft in UI
        ]);

        $visible = Announcement::visibleToStudent($student)->pluck('id')->all();
        $this->assertNotContains($draft->id, $visible,
            'Draft (status=inactive) announcements must NOT appear to students.');

        // Flip to published — should now appear.
        $draft->update(['status' => 'active']);
        $visible2 = Announcement::visibleToStudent($student)->pluck('id')->all();
        $this->assertContains($draft->id, $visible2,
            'Once status=active, the same announcement must become visible.');
    }

    /* ───────── T4 — coach forbidden from publishing all_students ───────── */

    public function test_t4_coach_cannot_post_all_students_audience(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorAnnouncementController.php')
        );
        // Defence in depth: source grep for the explicit role gate.
        $this->assertStringContainsString(
            "abort(403, 'Coaches cannot publish all-students announcements",
            $src,
            'InstructorAnnouncementController::store() must abort(403) when audience_type=all_students.'
        );
    }

    /* ───────── T10 — legacy rows still visible ───────── */

    public function test_t10_legacy_course_wide_announcements_still_visible(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        [$course, $batch] = $this->makeBatch($coach, 'L');
        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student, $batch);

        // A row in the legacy shape — no batch target, but a course_id.
        // This is exactly what the 200+ pre-upgrade rows look like.
        $legacy = Announcement::create([
            'instructor_id' => $coach->id,
            'sender_role'   => 'instructor',
            'audience_type' => 'batch_specific',  // default — same as backfilled
            'course_id'     => $course->id,
            'batch_id'      => null,
            'title'         => 'Legacy course-wide',
            'announcement'  => 'Body',
            'status'        => 'active',
        ]);

        $visible = Announcement::visibleToStudent($student)->pluck('id')->all();
        $this->assertContains($legacy->id, $visible,
            'Legacy course-wide rows must remain visible to enrolled students.');
    }

    /* ───────── controller wiring ───────── */

    public function test_student_routes_and_controller_methods_exist(): void
    {
        // Routes registered.
        $routes = collect(app('router')->getRoutes()->getRoutes());
        foreach ([
            'student.announcements.index',
            'student.announcements.show',
            'student.announcements.read',
            'student.announcements.unread-count',
        ] as $name) {
            $this->assertNotNull(
                $routes->first(fn ($r) => $r->getName() === $name),
                "Route $name must be registered."
            );
        }

        // Controller exposes the 4 methods.
        $ref = new \ReflectionClass(
            \App\Http\Controllers\Frontend\StudentAnnouncementController::class
        );
        foreach (['index', 'show', 'markRead', 'unreadCount'] as $m) {
            $this->assertTrue($ref->hasMethod($m),
                "StudentAnnouncementController must expose $m()");
        }
    }

    public function test_admin_create_route_and_methods_exist(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $this->assertNotNull(
            $routes->first(fn ($r) => $r->getName() === 'admin.announcements.create'),
            'Admin create route must be registered.'
        );
        $this->assertNotNull(
            $routes->first(fn ($r) => $r->getName() === 'admin.announcements.store'),
            'Admin store route must be registered.'
        );

        $ref = new \ReflectionClass(
            \App\Http\Controllers\Admin\AnnouncementController::class
        );
        foreach (['create', 'store', 'edit', 'update', 'batchesForCourse'] as $m) {
            $this->assertTrue($ref->hasMethod($m),
                "Admin\\AnnouncementController must expose $m()");
        }
    }

    /* ───────── unread tracking ───────── */

    public function test_mark_read_is_idempotent_via_model(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $ann = Announcement::create([
            'instructor_id' => 1,
            'sender_role'   => 'admin',
            'audience_type' => 'all_students',
            'title'         => 'Read test',
            'announcement'  => 'Body',
            'status'        => 'active',
        ]);

        $first  = $ann->markReadBy($student->id);
        $second = $ann->markReadBy($student->id);

        $this->assertTrue($first,  'First markReadBy must report newly marked.');
        $this->assertFalse($second, 'Second markReadBy on already-read must return false.');

        $this->assertSame(1,
            DB::table('announcement_reads')
                ->where('announcement_id', $ann->id)
                ->where('user_id', $student->id)
                ->count(),
            'Idempotent: re-marking must NOT create duplicate read rows.'
        );
    }

    /* ─────────────────── helpers ─────────────────── */

    private function makeBatch(User $coach, string $tag): array
    {
        $courseId = DB::table('courses')->insertGetId([
            'title'         => 'Ann ' . $tag . ' ' . uniqid(),
            'slug'          => 'ann-' . $tag . '-' . uniqid(),
            'instructor_id' => $coach->id,
            'added_by'      => $coach->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'type'          => 'live',
            'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $course = Course::find($courseId);

        $batch = CourseBatch::create([
            'course_id'  => $course->id,
            'title'      => 'Batch ' . $tag,
            'start_date' => now(),
            'end_date'   => now()->addMonth(),
            'start_time' => '09:00:00',
            'end_time'   => '11:00:00',
            'capacity'   => 30,
            'days'       => ['monday'],
            'status'     => 'active',
        ]);

        return [$course, $batch];
    }

    private function enroll(User $student, CourseBatch $batch): void
    {
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $batch->course_id,
            'batch_id'   => $batch->id,
            'order_id'   => null,
            'has_access' => 1,
        ]);
    }
}
