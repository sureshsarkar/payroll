<?php

namespace Tests\Feature\Domain;

use App\Http\Middleware\CoachPermission;
use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Role Permission Test" doc (2026-07-06). Each menu / button / route that was
 * showing to staff WITHOUT the matching permission now checks it. These tests
 * pin every issue in the doc:
 *   A  analytics + my-plan gated (menu + route)
 *   B  announcements create/edit/delete buttons gated
 *   C  batch edit/delete buttons gated
 *   D  Instant Meeting NOT surfaced by the live-classes permission
 *   blog create/edit/delete buttons gated
 *   Quick Add items gated
 * checkPermissionView() drives the menu/button visibility; the CoachPermission
 * middleware drives the analytics/my-plan route gate.
 */
class RolePermissionDocFixesTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    /** Staff holding exactly $slugs (materialised into the live users_permissions gate). */
    private function staffWith(User $coach, array $slugs): CoachStaff
    {
        $staff = CoachStaff::find(DB::table('users')->insertGetId([
            'role' => 'Manager', 'name' => 'Staff', 'email' => 's' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'coach_id' => $coach->id, 'added_by' => $coach->id, 'created_at' => now(), 'updated_at' => now(),
        ]));
        $ids = collect($slugs)->map(fn ($s) => CoachStaffPermission::where('slug', $s)->value('id'))->filter()->all();
        $staff->permissions()->sync($ids);
        return $staff->fresh();
    }

    private function asStaff(CoachStaff $s): void { Auth::guard('web')->login($s); }
    private function logout(): void { Auth::guard('web')->logout(); }

    /* ── A · analytics + my-plan are gated ───────────────────────────── */

    public function test_A_analytics_and_my_plan_gated_by_their_own_permission(): void
    {
        $coach = $this->coach();

        $withA = $this->staffWith($coach, ['analytics']);
        $this->asStaff($withA);
        $this->assertSame(1, checkPermissionView('analytics'), 'staff w/ analytics sees Analytics');
        $this->assertSame(0, checkPermissionView('my-plan'), 'staff w/o my-plan does NOT see My Plan');
        $this->logout();

        $none = $this->staffWith($coach, ['courses']);
        $this->asStaff($none);
        $this->assertSame(0, checkPermissionView('analytics'), 'staff w/o analytics does NOT see Analytics');
        $this->assertSame(0, checkPermissionView('my-plan'));
        $this->logout();
    }

    public function test_A_my_plan_slug_exists_in_catalog(): void
    {
        $this->assertTrue(CoachStaffPermission::where('slug', 'my-plan')->exists(), 'my-plan must be seeded so a coach can grant it');
    }

    public function test_A_analytics_route_blocked_for_staff_without_permission(): void
    {
        // JSON request → the middleware returns a testable 403 instead of abort().
        $req = \Illuminate\Http\Request::create('/instructor/analytics', 'GET');
        $req->headers->set('Accept', 'application/json');

        $coach = $this->coach();
        $staff = $this->staffWith($coach, ['courses']);
        $this->asStaff($staff);
        $res = app(CoachPermission::class)->handle($req, fn () => response('OK'), 'analytics');
        $this->assertSame(403, $res->getStatusCode(), 'staff w/o analytics must be blocked from the analytics route');
        $this->logout();

        $ok = $this->staffWith($coach, ['analytics']);
        $this->asStaff($ok);
        $res2 = app(CoachPermission::class)->handle($req, fn () => response('OK'), 'analytics');
        $this->assertSame('OK', $res2->getContent(), 'staff w/ analytics passes the analytics route');
        $this->logout();
    }

    /* ── B(server) · announcement endpoints enforce permission ───────── */

    public function test_B_announcement_endpoints_enforce_permission_server_side(): void
    {
        // JSON request → middleware returns a testable 403 instead of abort().
        $req = \Illuminate\Http\Request::create('/instructor/announcements', 'GET');
        $req->headers->set('Accept', 'application/json');
        $next = fn () => response('OK');
        $coach = $this->coach();

        // Staff WITHOUT the module → blocked from the list (was 200 before the fix).
        $none = $this->staffWith($coach, ['courses']);
        $this->asStaff($none);
        $this->assertSame(403, app(CoachPermission::class)->handle($req, $next, 'announcements')->getStatusCode());
        $this->logout();

        // View-only staff → can open the list, but NOT create/edit/delete.
        $viewOnly = $this->staffWith($coach, ['announcements']);
        $this->asStaff($viewOnly);
        $this->assertSame('OK', app(CoachPermission::class)->handle($req, $next, 'announcements')->getContent());
        $this->assertSame(403, app(CoachPermission::class)->handle($req, $next, 'announcements-create')->getStatusCode());
        $this->assertSame(403, app(CoachPermission::class)->handle($req, $next, 'announcements-edit')->getStatusCode());
        $this->assertSame(403, app(CoachPermission::class)->handle($req, $next, 'announcements-delete')->getStatusCode());
        $this->logout();

        // Real coach → passes every action.
        Auth::guard('web')->login($coach);
        foreach (['announcements', 'announcements-create', 'announcements-edit', 'announcements-delete'] as $slug) {
            $this->assertSame('OK', app(CoachPermission::class)->handle($req, $next, $slug)->getContent(), "coach passes $slug");
        }
        Auth::guard('web')->logout();
    }

    public function test_blog_and_batch_write_enforced_server_side(): void
    {
        $req = \Illuminate\Http\Request::create('/x', 'GET');
        $req->headers->set('Accept', 'application/json');
        $next = fn () => response('OK');
        $coach = $this->coach();

        // Blog (middleware-gated like announcements): view-only staff can list
        // but cannot create; a coach passes both.
        $blogViewer = $this->staffWith($coach, ['blogs']);
        $this->asStaff($blogViewer);
        $this->assertSame('OK', app(CoachPermission::class)->handle($req, $next, 'blogs')->getContent());
        $this->assertSame(403, app(CoachPermission::class)->handle($req, $next, 'blogs-create')->getStatusCode());
        $this->logout();

        // Batch writes are gated in-method via checkPermission($page,$action).
        $batchViewer = $this->staffWith($coach, ['course-batches']);
        $this->asStaff($batchViewer);
        $this->assertSame(0, checkPermission('course-batches', 'store'),   'view-only staff cannot create a batch');
        $this->assertSame(0, checkPermission('course-batches', 'update'),  'view-only staff cannot edit a batch');
        $this->assertSame(0, checkPermission('course-batches', 'destroy'), 'view-only staff cannot delete a batch');
        $this->logout();

        $batchCreator = $this->staffWith($coach, ['course-batches', 'course-batches-create']);
        $this->asStaff($batchCreator);
        $this->assertSame(1, checkPermission('course-batches', 'store'), 'granted staff can create a batch');
        $this->logout();

        Auth::guard('web')->login($coach);
        $this->assertSame(1, checkPermission('course-batches', 'destroy'), 'coach can always delete');
        Auth::guard('web')->logout();
    }

    /* ── D · Instant Meeting is not surfaced by live-classes ─────────── */

    public function test_D_instant_meeting_not_granted_by_live_classes(): void
    {
        $coach = $this->coach();
        $staff = $this->staffWith($coach, ['live-classes']);   // ONLY live classes
        $this->asStaff($staff);
        $this->assertSame(1, checkPermissionView('live-classes'), 'Live Classes visible');
        $this->assertSame(0, checkPermissionView('instant-meetings'), 'Instant Meeting must NOT show just because Live Classes is granted');
        $this->logout();

        $both = $this->staffWith($coach, ['live-classes', 'instant-meetings']);
        $this->asStaff($both);
        $this->assertSame(1, checkPermissionView('instant-meetings'), 'Instant Meeting shows when granted');
        $this->logout();
    }

    /* ── B / C / blog · granular action buttons ──────────────────────── */

    public function test_B_announcement_action_buttons_gated(): void
    {
        $coach = $this->coach();
        // Has the module (can view the list) but NOT create/edit/delete.
        $viewOnly = $this->staffWith($coach, ['announcements']);
        $this->asStaff($viewOnly);
        $this->assertSame(1, checkPermissionView('announcements'));
        $this->assertSame(0, checkPermissionView('announcements-create'), 'no Create button');
        $this->assertSame(0, checkPermissionView('announcements-edit'),   'no Edit button');
        $this->assertSame(0, checkPermissionView('announcements-delete'), 'no Delete button');
        $this->logout();

        $editor = $this->staffWith($coach, ['announcements', 'announcements-edit']);
        $this->asStaff($editor);
        $this->assertSame(1, checkPermissionView('announcements-edit'), 'Edit button shows when granted');
        $this->assertSame(0, checkPermissionView('announcements-delete'));
        $this->logout();
    }

    public function test_C_batch_action_buttons_gated(): void
    {
        $coach = $this->coach();
        $viewOnly = $this->staffWith($coach, ['course-batches']);
        $this->asStaff($viewOnly);
        $this->assertSame(0, checkPermissionView('course-batches-edit'));
        $this->assertSame(0, checkPermissionView('course-batches-delete'));
        $this->logout();
    }

    public function test_blog_action_buttons_gated(): void
    {
        $coach = $this->coach();
        $viewOnly = $this->staffWith($coach, ['blogs']);
        $this->asStaff($viewOnly);
        $this->assertSame(0, checkPermissionView('blogs-create'));
        $this->assertSame(0, checkPermissionView('blogs-edit'));
        $this->assertSame(0, checkPermissionView('blogs-delete'));
        $this->logout();
    }

    /* ── Quick Add · items gated ─────────────────────────────────────── */

    public function test_quick_add_items_gated(): void
    {
        $coach = $this->coach();
        $staff = $this->staffWith($coach, ['courses']);   // only courses
        $this->asStaff($staff);
        $this->assertSame(1, checkPermissionView('courses'),        'Add Course shortcut visible');
        $this->assertSame(0, checkPermissionView('live-classes'),   'Start Live Class shortcut hidden');
        $this->assertSame(0, checkPermissionView('fees'),           'Add Fee shortcut hidden');
        $this->assertSame(0, checkPermissionView('coach-students'), 'Add Student shortcut hidden');
        $this->logout();
    }

    /* ── Real coach bypasses everything ──────────────────────────────── */

    public function test_real_coach_sees_all_gated_items(): void
    {
        $coach = $this->coach();
        Auth::guard('web')->login($coach);
        foreach (['analytics', 'my-plan', 'instant-meetings', 'announcements-create', 'course-batches-edit', 'blogs-delete'] as $slug) {
            $this->assertSame(1, checkPermissionView($slug), "real coach sees $slug");
        }
        Auth::guard('web')->logout();
    }
}
