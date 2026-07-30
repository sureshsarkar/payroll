<?php

namespace Tests\Feature\Domain;

use App\Http\Middleware\CoachPermission;
use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;
use App\Models\StaffPermissionOverride;
use App\Models\User;
use App\Services\CoachPermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase 1 backbone — CoachPermissionService priority resolver + `permission`
 * middleware. Covers the Step-14 scenarios: no perms, role-only, override
 * remove, override add, inactive role, deleted role, cross-coach isolation,
 * real-coach bypass, and middleware allow/deny.
 */
class CoachPermissionResolverTest extends TestCase
{
    use DatabaseTransactions;

    private CoachPermissionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(CoachPermissionService::class);
    }

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach ' . uniqid(), 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function staffFor(User $coach): CoachStaff
    {
        return CoachStaff::find(DB::table('users')->insertGetId([
            'role' => 'Manager', 'name' => 'Staff ' . uniqid(), 'email' => 's' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'coach_id' => $coach->id, 'added_by' => $coach->id, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function perm(string $slug): CoachStaffPermission
    {
        return CoachStaffPermission::create(['name' => ucfirst($slug), 'slug' => $slug . '-' . uniqid()]);
    }

    private function role(User $coach, array $permIds, int $status = 1): CoachStaffRole
    {
        $r = CoachStaffRole::create(['role_name' => 'R' . uniqid(), 'role_slug' => 'r' . uniqid(), 'added_by' => $coach->id, 'status' => $status]);
        $r->permissions()->attach($permIds);
        return $r;
    }

    /* ───────────── resolver priority ───────────── */

    public function test_real_coach_has_full_access(): void
    {
        $coach = $this->coach();
        $this->assertTrue($this->svc->isRealCoach($coach));
        $this->assertTrue($this->svc->can($coach, 'anything-at-all'));
    }

    public function test_staff_with_no_permissions_is_denied(): void
    {
        $coach = $this->coach();
        $staff = $this->staffFor($coach);
        $this->assertFalse($this->svc->can($staff, 'courses'));
        $this->assertSame([], $this->svc->effectivePermissionIds($staff));
    }

    public function test_role_only_grants_role_permissions(): void
    {
        $coach = $this->coach();
        $view  = $this->perm('courses');
        $edit  = $this->perm('courses-edit');
        $role  = $this->role($coach, [$view->id, $edit->id]);
        $staff = $this->staffFor($coach);

        $this->svc->syncStaff($staff, $role->id, [], [], $coach->id);

        $this->assertTrue($this->svc->can($staff->fresh(), $view->slug));
        $this->assertTrue($this->svc->can($staff->fresh(), $edit->slug));
        $this->assertEqualsCanonicalizing([$view->id, $edit->id], $this->svc->effectivePermissionIds($staff->fresh()));
    }

    public function test_override_revoke_removes_one_permission_only(): void
    {
        $coach = $this->coach();
        $view  = $this->perm('students');
        $del   = $this->perm('students-delete');
        $role  = $this->role($coach, [$view->id, $del->id]);
        $staff = $this->staffFor($coach);

        // Revoke only the delete permission.
        $this->svc->syncStaff($staff, $role->id, [], [$del->id], $coach->id);

        $this->assertTrue($this->svc->can($staff->fresh(), $view->slug));   // kept
        $this->assertFalse($this->svc->can($staff->fresh(), $del->slug));   // revoked
        $this->assertDatabaseHas('staff_permission_overrides', ['user_id' => $staff->id, 'coach_staff_permission_id' => $del->id, 'effect' => 'revoke']);
    }

    public function test_override_grant_adds_permission_beyond_role(): void
    {
        $coach = $this->coach();
        $view  = $this->perm('reports');
        $export = $this->perm('reports-export');   // NOT in the role
        $role  = $this->role($coach, [$view->id]);
        $staff = $this->staffFor($coach);

        $this->svc->syncStaff($staff, $role->id, [$export->id], [], $coach->id);

        $this->assertTrue($this->svc->can($staff->fresh(), $view->slug));
        $this->assertTrue($this->svc->can($staff->fresh(), $export->slug)); // granted beyond role
    }

    public function test_grant_beats_revoke_conflict(): void
    {
        $coach = $this->coach();
        $p     = $this->perm('payouts');
        $role  = $this->role($coach, [$p->id]);
        $staff = $this->staffFor($coach);

        // Same permission in grant + revoke → grant wins, revoke skipped.
        $this->svc->syncStaff($staff, $role->id, [$p->id], [$p->id], $coach->id);

        $this->assertTrue($this->svc->can($staff->fresh(), $p->slug));
        $this->assertDatabaseMissing('staff_permission_overrides', ['user_id' => $staff->id, 'coach_staff_permission_id' => $p->id, 'effect' => 'revoke']);
    }

    public function test_inactive_role_grants_nothing(): void
    {
        $coach = $this->coach();
        $p     = $this->perm('courses');
        $role  = $this->role($coach, [$p->id], status: 0);   // inactive
        $staff = $this->staffFor($coach);

        $this->svc->syncStaff($staff, $role->id, [], [], $coach->id);
        $this->assertFalse($this->svc->can($staff->fresh(), $p->slug));
        $this->assertSame([], $this->svc->effectivePermissionIds($staff->fresh()));
    }

    public function test_deleted_role_leaves_no_role_permissions(): void
    {
        $coach = $this->coach();
        $p     = $this->perm('courses');
        $role  = $this->role($coach, [$p->id]);
        $staff = $this->staffFor($coach);
        $this->svc->syncStaff($staff, $role->id, [], [], $coach->id);
        $this->assertTrue($this->svc->can($staff->fresh(), $p->slug));

        // Role deleted → staff's role link gone → resolver yields nothing from role.
        $staff->roles()->detach();
        $role->delete();
        $this->assertSame([], $this->svc->rolePermissionIds($staff->fresh()));
    }

    public function test_cross_coach_isolation(): void
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $pA = $this->perm('coachA-secret');
        $roleA = $this->role($coachA, [$pA->id]);
        $staffA = $this->staffFor($coachA);
        $this->svc->syncStaff($staffA, $roleA->id, [], [], $coachA->id);

        // Coach B (and its staff) must never resolve Coach A's role permission.
        $this->assertTrue($this->svc->can($staffA->fresh(), $pA->slug));
        $staffB = $this->staffFor($coachB);
        $this->assertFalse($this->svc->can($staffB->fresh(), $pA->slug));
        $this->assertSame($coachA->id, $this->svc->coachIdFor($staffA));
        $this->assertSame($coachB->id, $this->svc->coachIdFor($staffB));
    }

    /* ───────────── middleware ───────────── */

    private function runMiddleware($user, string $slug): string
    {
        $this->actingAs($user, 'web');
        $req = Request::create('/instructor/x', 'GET');
        $res = app(CoachPermission::class)->handle($req, fn () => response('PASS'), $slug);
        return $res->getContent();
    }

    public function test_middleware_allows_permitted_staff_and_blocks_others(): void
    {
        $coach = $this->coach();
        $p     = $this->perm('coupons');
        $role  = $this->role($coach, [$p->id]);
        $staff = $this->staffFor($coach);
        $this->svc->syncStaff($staff, $role->id, [], [], $coach->id);

        // Real coach → always passes.
        $this->assertSame('PASS', $this->runMiddleware($coach, 'coupons'));
        // Staff WITH the slug → passes.
        $this->assertSame('PASS', $this->runMiddleware($staff->fresh(), $p->slug));
        // Staff WITHOUT the slug → 403.
        $this->assertThrows(fn () => $this->runMiddleware($staff->fresh(), 'some-other-slug'), HttpException::class);
    }
}
