<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\InstructorAnnouncementController;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tripwire for the 2026-05-20 bug:
 *
 *   GET /instructor/announcements/{id} returned 500 because
 *   Route::resource() auto-binds a `show` action but the controller
 *   never defined it. Anyone with a bookmarked URL hit
 *   "Method show does not exist".
 *
 * Fix shipped: show() now redirects to edit() — same data, same
 * IDOR guard. These tests pin both:
 *   (a) the show() method exists and returns a 302 to the edit URL,
 *   (b) the edit() method's existing instructor_id scope still fires.
 *
 * We exercise the controller methods directly rather than via HTTP
 * because the bare mbs_test settings table doesn't seed every key the
 * master layout reads (logo / maintenance_mode / etc.) — view
 * rendering is a separate concern from the routing fix here.
 */
class InstructorAnnouncementsRouteTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Route::resource declares a `show` route. Without our fix, calling
     * it raised BadMethodCallException at the kernel level. Pin the
     * method's existence so a future "trim down resource" can't
     * silently remove it.
     */
    public function test_show_method_exists_on_controller(): void
    {
        $ref = new \ReflectionClass(InstructorAnnouncementController::class);
        $this->assertTrue($ref->hasMethod('show'),
            'InstructorAnnouncementController must expose a show() method. ' .
            'Route::resource auto-generates the show route; without this ' .
            'method, /instructor/announcements/{id} returns HTTP 500.');
    }

    /**
     * Show must redirect to the edit URL for the same id — that's the
     * contract the fix shipped.
     */
    public function test_show_redirects_to_edit_url(): void
    {
        $coach = $this->makeCoach();
        $ann   = $this->makeAnnouncementFor($coach);

        Auth::guard('web')->loginUsingId($coach->id);

        $ctrl = app(InstructorAnnouncementController::class);
        $resp = $ctrl->show((string) $ann->id);

        $this->assertInstanceOf(RedirectResponse::class, $resp,
            'show() must return a RedirectResponse.');
        $this->assertStringContainsString(
            "/instructor/announcements/{$ann->id}/edit",
            $resp->getTargetUrl(),
            'show() must redirect to /instructor/announcements/{id}/edit.'
        );
        $this->assertSame(302, $resp->getStatusCode(),
            'Redirect must use the default 302 status.');
    }

    /**
     * Pre-existing IDOR contract: edit() scopes by instructor_id, so
     * a coach who doesn't own the announcement gets a 404 instead of
     * seeing someone else's data. Pin this so the show→edit redirect
     * doesn't accidentally widen the attack surface.
     */
    public function test_edit_throws_for_non_owner_announcement(): void
    {
        $owner    = $this->makeCoach('Owner');
        $stranger = $this->makeCoach('Stranger');
        $ann      = $this->makeAnnouncementFor($owner);

        Auth::guard('web')->loginUsingId($stranger->id);
        $ctrl = app(InstructorAnnouncementController::class);

        // firstOrFail() throws ModelNotFoundException → 404 in the
        // kernel. Test the same condition at the controller layer.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $ctrl->edit((string) $ann->id);
    }

    /**
     * Owner of the announcement reaches edit() cleanly.
     * Returns a View instance; we don't assert on the rendered HTML
     * because the master layout depends on global settings cache.
     */
    public function test_edit_returns_view_for_owner(): void
    {
        $coach = $this->makeCoach();
        $ann   = $this->makeAnnouncementFor($coach);

        Auth::guard('web')->loginUsingId($coach->id);
        $ctrl = app(InstructorAnnouncementController::class);

        $view = $ctrl->edit((string) $ann->id);
        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
        $this->assertSame('frontend.instructor-dashboard.announcement.edit', $view->name());
        $this->assertSame($ann->id, $view->getData()['announcement']->id);
    }

    /* ─────────────────── helpers ─────────────────── */

    private function makeCoach(string $tag = 'Coach'): User
    {
        return User::factory()->create([
            'role' => 'instructor',
            'name' => $tag . ' ' . uniqid(),
        ]);
    }

    private function makeAnnouncementFor(User $coach): Announcement
    {
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Ann test ' . uniqid(),
            'slug'  => 'ann-' . uniqid(),
            'instructor_id' => $coach->id,
            'added_by'      => $coach->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'type'          => 'live',
            'price' => 0, 'discount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Announcement::create([
            'instructor_id' => $coach->id,
            'course_id'     => $courseId,
            'title'         => 'Test announcement ' . uniqid(),
            'announcement'  => 'Body',
            'status'        => 'active',
            'sent_at'       => now(),
        ]);
    }
}
