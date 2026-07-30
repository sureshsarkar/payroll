<?php

namespace Tests\Feature\Domain;

use App\Http\Middleware\CoachPermission;
use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;
use App\Models\User;
use App\Services\CoachPermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 5 — the remaining Step-14 scenarios: cross-coach isolation of roles &
 * staff (the exact ownership scope the controllers rely on) and API-level denial
 * (the middleware returns a JSON 403 for an AJAX/API request, not an HTML page).
 */
class CoachRbacPhase5Test extends TestCase
{
    use DatabaseTransactions;

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

    private function role(User $coach): CoachStaffRole
    {
        return CoachStaffRole::create(['role_name' => 'R' . uniqid(), 'role_slug' => 'r' . uniqid(), 'added_by' => $coach->id, 'status' => 1]);
    }

    /* ───────────── cross-coach isolation ───────────── */

    public function test_coach_cannot_resolve_another_coachs_staff(): void
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $staffB = $this->staffFor($coachB);

        // The exact ownership scope findOwnedStaffOrFail() uses.
        $this->assertNull(CoachStaff::where('added_by', $coachA->id)->where('id', $staffB->id)->first());
        $this->assertNotNull(CoachStaff::where('added_by', $coachB->id)->where('id', $staffB->id)->first());
    }

    public function test_coach_cannot_resolve_another_coachs_role(): void
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $roleB  = $this->role($coachB);

        // The exact ownership scope findOwnedRoleOrFail() uses.
        $this->assertNull(CoachStaffRole::where('added_by', $coachA->id)->where('id', $roleB->id)->first());
        $this->assertNotNull(CoachStaffRole::where('added_by', $coachB->id)->where('id', $roleB->id)->first());
    }

    public function test_staff_of_coach_a_cannot_resolve_coach_b_permission(): void
    {
        $svc = app(CoachPermissionService::class);
        $coachA = $this->coach();
        $coachB = $this->coach();
        $permB  = CoachStaffPermission::create(['name' => 'B secret', 'slug' => 'b-secret-' . uniqid()]);
        $roleB  = $this->role($coachB);
        $roleB->permissions()->attach([$permB->id]);
        $staffB = $this->staffFor($coachB);
        $svc->syncStaff($staffB, $roleB->id, [], [], $coachB->id);

        // Coach A's staff never resolves Coach B's permission.
        $staffA = $this->staffFor($coachA);
        $this->assertTrue($svc->can($staffB->fresh(), $permB->slug));
        $this->assertFalse($svc->can($staffA->fresh(), $permB->slug));
    }

    public function test_deleting_a_role_revokes_its_permissions_from_staff(): void
    {
        $svc = app(CoachPermissionService::class);
        $coach = $this->coach();
        $perm = CoachStaffPermission::create(['name' => 'Modx', 'slug' => 'modx-' . uniqid()]);
        $role = $this->role($coach);
        $role->permissions()->attach([$perm->id]);
        $staff = $this->staffFor($coach);
        $svc->syncStaff($staff, $role->id, [], [], $coach->id);
        $this->assertTrue($svc->can($staff->fresh(), $perm->slug), 'precondition: staff has the role perm');

        // Drive the REAL controller destroy() (route context so
        // getActionMethod() === 'destroy' and checkPermission passes).
        $this->actingAs($coach, 'web');
        $ctrl  = app(\App\Http\Controllers\Frontend\Coach\CoachStaffRoleController::class);
        $route = new \Illuminate\Routing\Route('DELETE', '_', ['uses' => \App\Http\Controllers\Frontend\Coach\CoachStaffRoleController::class . '@destroy']);
        request()->setRouteResolver(fn () => $route);
        $ctrl->destroy($role->id);

        $this->assertNull(CoachStaffRole::find($role->id), 'role should be deleted');
        $this->assertFalse($svc->can($staff->fresh(), $perm->slug), 'staff must LOSE the deleted role\'s permission');
    }

    public function test_deleting_a_role_preserves_staff_own_grant_overrides(): void
    {
        $svc = app(CoachPermissionService::class);
        $coach = $this->coach();
        $roleP  = CoachStaffPermission::create(['name' => 'RoleP', 'slug' => 'rp-' . uniqid()]);
        $grantP = CoachStaffPermission::create(['name' => 'GrantP', 'slug' => 'gp-' . uniqid()]);
        $role = $this->role($coach);
        $role->permissions()->attach([$roleP->id]);
        $staff = $this->staffFor($coach);
        // Staff has the role perm PLUS an explicit grant beyond the role.
        $svc->syncStaff($staff, $role->id, [$grantP->id], [], $coach->id);

        $this->actingAs($coach, 'web');
        $ctrl  = app(\App\Http\Controllers\Frontend\Coach\CoachStaffRoleController::class);
        $route = new \Illuminate\Routing\Route('DELETE', '_', ['uses' => \App\Http\Controllers\Frontend\Coach\CoachStaffRoleController::class . '@destroy']);
        request()->setRouteResolver(fn () => $route);
        $ctrl->destroy($role->id);

        $this->assertFalse($svc->can($staff->fresh(), $roleP->slug), 'role perm gone');
        $this->assertTrue($svc->can($staff->fresh(), $grantP->slug), 'explicit grant survives role deletion');
    }

    /* ───────────── API-level denial ───────────── */

    public function test_middleware_returns_json_403_for_api_requests(): void
    {
        $coach = $this->coach();
        $staff = $this->staffFor($coach);
        $this->actingAs($staff, 'web');

        // An AJAX/API request (Accept: application/json) → JSON 403, not an HTML page.
        $req = Request::create('/instructor/x', 'POST');
        $req->headers->set('Accept', 'application/json');
        $res = app(CoachPermission::class)->handle($req, fn () => response('ok'), 'coach-coupons-create');

        $this->assertInstanceOf(JsonResponse::class, $res);
        $this->assertSame(403, $res->getStatusCode());
        $this->assertStringContainsStringIgnoringCase('not authorized', (string) $res->getContent());
    }

    public function test_middleware_allows_json_when_staff_has_permission(): void
    {
        $svc = app(CoachPermissionService::class);
        $coach = $this->coach();
        $perm = CoachStaffPermission::create(['name' => 'Coupons', 'slug' => 'coach-coupons-' . uniqid()]);
        $role = $this->role($coach);
        $role->permissions()->attach([$perm->id]);
        $staff = $this->staffFor($coach);
        $svc->syncStaff($staff, $role->id, [], [], $coach->id);

        $this->actingAs($staff->fresh(), 'web');
        $req = Request::create('/instructor/x', 'POST');
        $req->headers->set('Accept', 'application/json');
        $res = app(CoachPermission::class)->handle($req, fn () => response('PASS'), $perm->slug);
        $this->assertSame('PASS', $res->getContent());
    }
}
