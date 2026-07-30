<?php

namespace Tests\Feature\Domain;

use App\Http\Middleware\CoachPermission;
use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * QA audit (2026-07-07) — server-side RBAC for coach modules whose menus/buttons
 * were hidden but whose ENDPOINTS were left ungated (only requires.membership).
 * A staff member without the permission could POST create/edit/delete directly.
 *
 * Covers the two modules closed in this pass:
 *   • Fee Management        (fees / fees-create / fees-edit / fees-delete)
 *   • Teacher-Batch Assign. (teacher-batches / teacher-batches-edit)
 *
 * Two layers: (1) the route actually CARRIES the permission middleware (wiring),
 * (2) the CoachPermission middleware DENIES staff-without / ALLOWS staff-with /
 * ALLOWS a real coach (behaviour).
 */
class CoachModuleServerSideRbacTest extends TestCase
{
    use DatabaseTransactions;

    private const FEE = \App\Http\Controllers\Frontend\Coach\FeeManagementController::class;
    private const TBA = \App\Http\Controllers\Frontend\Coach\TeacherBatchAssignmentController::class;

    /* ── wiring: route gathers the expected permission middleware ─────── */

    private function assertGated(string $controller, string $method, string $middleware): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getActionName() === $controller . '@' . $method);

        $this->assertNotNull($route, "route for {$method} must exist");
        $this->assertContains(
            $middleware,
            $route->gatherMiddleware(),
            "{$method} must be gated by {$middleware}"
        );
    }

    public function test_fee_endpoints_carry_permission_middleware(): void
    {
        $this->assertGated(self::FEE, 'index', 'permission:fees');
        $this->assertGated(self::FEE, 'storeDemand', 'permission:fees-create');
        $this->assertGated(self::FEE, 'recordPayment', 'permission:fees-edit');
        $this->assertGated(self::FEE, 'refundPayment', 'permission:fees-delete');
    }

    public function test_teacher_batch_endpoints_carry_permission_middleware(): void
    {
        $this->assertGated(self::TBA, 'index', 'permission:teacher-batches');
        $this->assertGated(self::TBA, 'store', 'permission:teacher-batches-edit');
        $this->assertGated(self::TBA, 'update', 'permission:teacher-batches-edit');
        $this->assertGated(self::TBA, 'destroy', 'permission:teacher-batches-edit');
    }

    /* ── behaviour: middleware denies / allows correctly ─────────────── */

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

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

    /** Run CoachPermission for $slug as a JSON request so denial returns a testable 403. */
    private function gate(string $slug): int
    {
        $req = \Illuminate\Http\Request::create('/instructor/x', 'GET');
        $req->headers->set('Accept', 'application/json');
        return app(CoachPermission::class)->handle($req, fn () => response('OK'), $slug)->getStatusCode();
    }

    public function test_staff_without_fee_permission_is_blocked(): void
    {
        $coach = $this->coach();

        Auth::guard('web')->login($this->staffWith($coach, ['courses']));    // no fees
        $this->assertSame(403, $this->gate('fees-create'), 'staff w/o fees-create blocked');
        $this->assertSame(403, $this->gate('fees-delete'), 'staff w/o fees-delete (refund) blocked');
        Auth::guard('web')->logout();

        Auth::guard('web')->login($this->staffWith($coach, ['fees', 'fees-create']));
        $this->assertSame(200, $this->gate('fees-create'), 'staff w/ fees-create passes');
        $this->assertSame(403, $this->gate('fees-delete'), 'but still cannot refund without fees-delete');
        Auth::guard('web')->logout();
    }

    public function test_staff_without_teacher_batch_edit_cannot_self_escalate(): void
    {
        $coach = $this->coach();

        // A teacher with only their teaching perms must NOT be able to hit the
        // write endpoint that could grant themselves more batches.
        Auth::guard('web')->login($this->staffWith($coach, ['live-classes', 'teacher-batches']));
        $this->assertSame(403, $this->gate('teacher-batches-edit'), 'read-only staff cannot mutate assignments');
        Auth::guard('web')->logout();

        Auth::guard('web')->login($this->staffWith($coach, ['teacher-batches', 'teacher-batches-edit']));
        $this->assertSame(200, $this->gate('teacher-batches-edit'), 'explicitly-granted staff may manage');
        Auth::guard('web')->logout();
    }

    public function test_real_coach_passes_every_gate(): void
    {
        Auth::guard('web')->login($this->coach());
        foreach (['fees', 'fees-create', 'fees-edit', 'fees-delete', 'teacher-batches', 'teacher-batches-edit'] as $slug) {
            $this->assertSame(200, $this->gate($slug), "real coach passes {$slug}");
        }
        Auth::guard('web')->logout();
    }
}
