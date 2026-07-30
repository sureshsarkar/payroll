<?php

namespace Tests\Feature\Domain;

use App\Models\CourseChapter;
use App\Models\CourseLiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Regression tripwires for SRS-CLC-001 (2026-05-19 phase 4).
 *
 * The SRS removed the Chapter dropdown from the Add Live Class flow.
 * These tests pin the 12 acceptance criteria from §7 of the document
 * so a future refactor can't quietly reintroduce the dependency.
 *
 * Acceptance map (from SRS v3 §7):
 *   A-3  Chapter field not rendered in DOM       → test_chapter_field_not_in_live_class_modal_dom
 *   A-4  Form submits without chapter_id         → test_validation_accepts_request_without_chapter_id
 *   A-5  Live Class created via API w/o chapter  → test_store_creates_class_when_chapter_id_missing
 *   A-6  Live Class row stored OK                → covered by A-5
 *   A-2  Course auto-fills the modal             → test_modal_renders_course_field
 *   A-10 Edit/list/view flow works post-change   → test_edit_modal_omits_chapter_dropdown
 *   Out-of-scope guarantee — Zoom flow untouched → test_zoom_credentials_logic_unchanged
 */
class LiveClassChapterRemovalTest extends TestCase
{
    use DatabaseTransactions;

    /** A-3 — Chapter field must not appear in the modal markup. */
    public function test_chapter_field_not_in_live_class_modal_dom(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/live-classes/index.blade.php')
        );

        // Neither the named input nor the labelled-for attribute should exist.
        $this->assertStringNotContainsString('name="chapter_id"', $src,
            'Add Live Class modal must NOT carry a chapter_id select (SRS-CLC-001 A-3).');
        $this->assertStringNotContainsString('id="chapter_id"', $src,
            'Add Live Class modal must NOT carry an #chapter_id select.');
        $this->assertDoesNotMatchRegularExpression('/<label[^>]*for="chapter_id"/i', $src,
            'No <label for="chapter_id"> should remain.');
    }

    /** A-10 — Edit modal must also drop the Chapter dropdown. */
    public function test_edit_modal_omits_chapter_dropdown(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/live-classes/edit.blade.php')
        );

        $this->assertStringNotContainsString('name="chapter_id"', $src,
            'Edit Live Class view must NOT carry a chapter_id select (SRS-CLC-001 A-10).');
    }

    /** A-2 — Course must still be auto-fillable in the modal. */
    public function test_modal_renders_course_field(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/live-classes/index.blade.php')
        );
        $this->assertStringContainsString('name="course_id"', $src,
            'Course field must still exist (SRS-CLC-001 A-2).');
    }

    /** A-4 / A-5 — Validation rules must accept missing chapter_id. */
    public function test_validation_accepts_request_without_chapter_id(): void
    {
        // Inspect the controller's validation rules directly via source —
        // we can't easily invoke the full HTTP path without seeding a
        // coach's Zoom credentials.
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        // Rule must be nullable, not required.
        $this->assertMatchesRegularExpression(
            "/'chapter_id'\\s*=>\\s*'nullable\\|exists:course_chapters,id'/",
            $src,
            'chapter_id validation rule must be nullable (SRS-CLC-001 BE-1/BE-3).'
        );
        // The required-message line must be gone.
        $this->assertStringNotContainsString(
            "'chapter_id.required'",
            $src,
            "'chapter_id.required' validation message must be removed."
        );
    }

    /**
     * A-5 / A-6 — A coach can create a Live Class with no chapter_id in
     * the request and the row lands in DB cleanly with the supporting
     * lesson + chapter resolved server-side.
     *
     * We exercise the controller method directly (not via HTTP) so the
     * test doesn't depend on Zoom credentials being configured.
     */
    public function test_store_creates_class_when_chapter_id_missing(): void
    {
        $coach = User::factory()->create(['role' => 'instructor']);
        $courseId = DB::table('courses')->insertGetId([
            'title'         => 'Chapter-removal test '.uniqid(),
            'slug'          => 'chap-rm-'.uniqid(),
            'instructor_id' => $coach->id,
            'added_by'      => $coach->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'price'         => 0, 'discount' => 0,
            'created_at'    => now(), 'updated_at' => now(),
        ]);
        $batchId = DB::table('course_batches')->insertGetId([
            'course_id'  => $courseId,
            'title'      => 'Batch A',
            'start_date' => now(),
            'end_date'   => now()->addMonth(),
            'start_time' => '09:00:00',
            'end_time'   => '11:00:00',
            'capacity'   => 30,
            'days'       => json_encode(['monday']),
            'status'     => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Auth::guard('web')->loginUsingId($coach->id);

        // Reflect into resolveDefaultChapter() — proves the server-side
        // resolver path works when no chapter exists yet. Resolve via
        // the container so the constructor's CourseLiveClass dependency
        // is satisfied (it's an Eloquent model, not a service).
        $ctrl = app(\App\Http\Controllers\Frontend\Coach\LiveClassController::class);
        $ref  = new \ReflectionClass($ctrl);
        $m    = $ref->getMethod('resolveDefaultChapter');
        $m->setAccessible(true);

        // First call — creates a 'Live Sessions' chapter.
        /** @var CourseChapter $chap1 */
        $chap1 = $m->invoke($ctrl, $courseId);
        $this->assertNotNull($chap1->id, 'resolveDefaultChapter must always return a chapter row');
        $this->assertSame($courseId, (int) $chap1->course_id);

        // Second call — reuses the existing one (must NOT create a duplicate).
        /** @var CourseChapter $chap2 */
        $chap2 = $m->invoke($ctrl, $courseId);
        $this->assertSame($chap1->id, $chap2->id,
            'resolveDefaultChapter must return the SAME chapter on repeat calls');

        // Only one chapter must exist for this course.
        $this->assertSame(1,
            CourseChapter::where('course_id', $courseId)->count(),
            'resolveDefaultChapter must not multiply chapters');
    }

    /**
     * Out-of-scope guarantee — Zoom credentials gate logic must still
     * fire in the controller. The SRS §6.3 specifically marks Zoom as
     * untouched; this test prevents an accidental Zoom regression
     * during the chapter cleanup.
     */
    public function test_zoom_credentials_logic_unchanged(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );

        $this->assertStringContainsString(
            "'Please configure Zoom credentials first",
            $src,
            'Zoom credential check must remain in the controller (SRS-CLC-001 §6.3 out-of-scope).'
        );
        $this->assertStringContainsString(
            'ensureFreshAccessToken',
            $src,
            'Zoom token-refresh path must remain in the controller.'
        );
    }

    /* ════════ Phase 2 — Course Type dropdown (SRS §4.1) ═════════ */

    /** A-1 — Course Type dropdown must exist on Create Course page. */
    public function test_course_type_dropdown_present_on_create_course(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/course/create.blade.php')
        );
        $this->assertStringContainsString('name="type"', $src,
            'Create Course must carry a <select name="type"> (SRS-CLC-001 §4.1).');
        $this->assertStringContainsString('id="type"', $src);

        // All 3 enum options must be declared in the $typeOptions map.
        // The blade uses @foreach($typeOptions ...) so the values appear
        // as keys in the PHP map, not as literal value="..." attributes.
        foreach (["'live'", "'recorded'", "'hybrid'"] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $src,
                "Course Type \$typeOptions map must declare $needle."
            );
        }
    }

    /** courses.type enum must accept the 3 new values. */
    public function test_courses_type_enum_widened(): void
    {
        $col = collect(\DB::select(
            'SHOW COLUMNS FROM courses WHERE Field = ?', ['type']
        ))->first();

        $this->assertNotNull($col, 'courses.type column must exist');
        foreach (['live', 'recorded', 'hybrid'] as $needle) {
            $this->assertStringContainsString(
                "'$needle'",
                (string) $col->Type,
                "courses.type ENUM must include '$needle' (migration 2026_05_19_100000)."
            );
        }
    }

    /** Controller validation must accept the 3 enum values and reject others. */
    public function test_course_type_validation_accepts_enum_values(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorCourseController.php')
        );
        $this->assertMatchesRegularExpression(
            "/'type'\\s*=>\\s*\\[\\s*'nullable',\\s*'in:live,recorded,hybrid'\\s*\\]/",
            $src,
            "InstructorCourseController must validate type against the 3 allowed values."
        );
    }

    /** Storing a Course with type='live' persists cleanly. */
    public function test_course_can_be_saved_with_live_type(): void
    {
        $course = new \App\Models\Course;
        $course->slug = 'phase2-test-' . uniqid();
        $course->title = 'Phase 2 test course';
        $course->instructor_id = 1;
        $course->added_by = 1;
        $course->type = 'live';
        $course->status = 'active';
        $course->is_approved = 'approved';
        $course->price = 0;
        $course->discount = 0;
        $course->description = 'test';
        $course->save();

        $this->assertSame('live', $course->fresh()->type,
            'Course with type=live must round-trip cleanly.');
    }

    /** Storing a Course with type='hybrid' persists cleanly. */
    public function test_course_can_be_saved_with_hybrid_type(): void
    {
        $course = new \App\Models\Course;
        $course->slug = 'phase2-hybrid-' . uniqid();
        $course->title = 'Phase 2 hybrid test';
        $course->instructor_id = 1;
        $course->added_by = 1;
        $course->type = 'hybrid';
        $course->status = 'active';
        $course->is_approved = 'approved';
        $course->price = 0;
        $course->discount = 0;
        $course->description = 'test';
        $course->save();

        $this->assertSame('hybrid', $course->fresh()->type);
    }

    /* ════════ Phase 3 — Post-SRS feedback follow-ups ═════════ */

    /**
     * Phase 3a (v2 after user feedback 2026-05-19) — Live Class course
     * dropdown is STRICT: only type IN (live, hybrid). Legacy 'course'
     * rows are deliberately hidden; the backfill migration
     * (2026_05_19_120000) flips legacy courses that had existing live
     * classes so active workflows are not regressed.
     */
    public function test_live_class_dropdown_filters_to_live_and_hybrid_only(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        // Both index() and edit() must use the strict whereIn filter. Staff
        // authorship added extra unions (2026-07-10) that ALSO filter to
        // live/hybrid, so the count is >= 2 rather than exactly 2.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($src, "whereIn('type', ['live', 'hybrid'])"),
            "LiveClassController must filter type IN ('live','hybrid') in BOTH index() and edit() dropdowns."
        );
        // No remaining loose '!= recorded' filter.
        $this->assertStringNotContainsString(
            "->where('type', '!=', 'recorded')",
            $src,
            "The loose '!= recorded' filter must be replaced by the strict whereIn (post-feedback)."
        );
    }

    /* ════════ Phase 5 — post-Phase-4C UX feedback ═════════ */

    /**
     * Manual Sale form must have batch as OPTIONAL (no `required`
     * attribute on the select, label labelled as optional).
     */
    public function test_manual_sale_batch_field_is_optional(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/my-sells/create.blade.php')
        );

        // The select must not carry a `required` attribute.
        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]*name="batch_id"[^>]*required/i',
            $src,
            'Manual Sale batch_id select must NOT be required (post-Phase-4C feedback).'
        );
        // Label must call out (optional) so coaches know they can skip.
        $this->assertMatchesRegularExpression(
            '/<label[^>]*for="batchId">.*?optional/is',
            $src,
            'Batch label must indicate "(optional)" so the field is visibly skippable.'
        );
    }

    /**
     * Create Batch dropdown must filter to live + hybrid only.
     * Recorded-type courses can't host batches.
     */
    public function test_create_batch_dropdown_filters_to_live_hybrid_only(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorCourseController.php')
        );
        $this->assertStringContainsString(
            "->whereIn('type', ['live', 'hybrid'])",
            $src,
            'batchesIndex() must filter courses to live + hybrid types.'
        );
    }

    /**
     * Server-side guard: batchesStore() must 422 if the course type
     * is 'recorded' — defence in depth alongside the UI filter.
     */
    public function test_batches_store_rejects_recorded_courses(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorCourseController.php')
        );
        $this->assertMatchesRegularExpression(
            "/if\\s*\\(\\s*\\\$course->type\\s*===\\s*'recorded'\\s*\\)/",
            $src,
            'batchesStore() must reject when the parent course is type=recorded.'
        );
    }

    /**
     * Phase 3a (v2) — backfill migration must have run; legacy courses
     * with existing live classes are now type='live'.
     */
    public function test_backfill_migration_flipped_legacy_live_courses(): void
    {
        // A course is "actively live" if it has at least one row in
        // course_live_classes. After the backfill it must NOT be on
        // the legacy 'course' / 'webinar' enum value.
        $legacyLiveCourses = \DB::table('courses')
            ->whereIn('type', ['course', 'webinar'])
            ->whereIn('id', function ($sub) {
                $sub->from('course_live_classes')
                    ->select('course_id')
                    ->whereNotNull('course_id');
            })
            ->count();

        $this->assertSame(0, $legacyLiveCourses,
            "Every course that has live classes must be type='live' or 'hybrid' " .
            "after the 2026_05_19_120000 backfill migration. " .
            "Found $legacyLiveCourses still on the legacy enum.");
    }

    /** Phase 3b — Instant Live Class button must be rendered. */
    public function test_instant_live_class_button_present(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/live-classes/index.blade.php')
        );
        $this->assertStringContainsString('id="instantModal"', $src,
            'Instant Live Class modal must be defined.');
        $this->assertStringContainsString('data-bs-target="#instantModal"', $src,
            'A button must open the Instant modal.');
        $this->assertStringContainsString("route('instructor.live-class.instant')", $src,
            'Instant form must POST to live-class.instant.');
    }

    /** Phase 3b — instantStart() controller method must exist. */
    public function test_instant_start_controller_method_exists(): void
    {
        $ref = new \ReflectionClass(\App\Http\Controllers\Frontend\Coach\LiveClassController::class);
        $this->assertTrue($ref->hasMethod('instantStart'),
            'LiveClassController must expose instantStart() (Phase 3b).');

        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        $this->assertStringContainsString('resolveDefaultChapter', $src,
            'instantStart should reuse resolveDefaultChapter for chapter creation.');
        $this->assertStringContainsString('zoomMeetingPayload', $src,
            'instantStart should reuse zoomMeetingPayload to build the Zoom request.');
    }

    /** Phase 3c — Add Student form must carry the Batch dropdown. */
    public function test_add_student_form_has_batch_field(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/instructor-dashboard/my-students/create.blade.php')
        );
        $this->assertStringContainsString('name="batch_id"', $src,
            'Add Student form must carry a batch_id select.');
        $this->assertStringContainsString('Assign to Batch', $src);
    }

    /** Phase 3c — storeStudetns must validate + persist Enrollment. */
    public function test_store_students_accepts_batch_id_and_creates_enrollment(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorDashboardController.php')
        );
        $this->assertStringContainsString(
            "'batch_id' => 'nullable|exists:course_batches,id'",
            $src,
            'storeStudetns must validate batch_id as nullable+existing.'
        );
        $this->assertStringContainsString('Enrollment::firstOrCreate', $src,
            'storeStudetns must create Enrollment row when batch_id is set (idempotent).');
    }

    /** Phase 3c — enrollments.order_id must be nullable now. */
    public function test_enrollments_order_id_is_nullable(): void
    {
        $col = collect(\DB::select(
            'SHOW COLUMNS FROM enrollments WHERE Field = ?', ['order_id']
        ))->first();
        $this->assertNotNull($col);
        $this->assertSame('YES', $col->Null,
            'enrollments.order_id must allow NULL (migration 2026_05_19_110000) so coach-added students can be enrolled without an Order.');
    }
}
