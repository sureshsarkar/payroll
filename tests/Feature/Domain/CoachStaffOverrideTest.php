<?php

namespace Tests\Feature\Domain;

use App\Http\Controllers\Frontend\Coach\CoachStaffController;
use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;
use App\Models\User;
use App\Services\CoachPermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3 — the coach-staff Update flow records per-staff OVERRIDES (diffed from
 * the role) and re-materialises the live gate, without mutating the role. Drives
 * the real controller so validation + resolver + persistence are all exercised.
 */
class CoachStaffOverrideTest extends TestCase
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

    private function perm(string $slug): CoachStaffPermission
    {
        return CoachStaffPermission::create(['name' => ucfirst($slug), 'slug' => $slug . '-' . uniqid()]);
    }

    public function test_update_records_overrides_and_leaves_role_untouched(): void
    {
        $svc   = app(CoachPermissionService::class);
        $coach = $this->coach();
        $A = $this->perm('courses');
        $B = $this->perm('coach-students');
        $C = $this->perm('payout');
        $D = $this->perm('reports-export');     // NOT in the role

        $role = CoachStaffRole::create(['role_name' => 'Mgr' . uniqid(), 'role_slug' => 'mgr' . uniqid(), 'added_by' => $coach->id, 'status' => 1]);
        $role->permissions()->attach([$A->id, $B->id, $C->id]);
        $staff = $this->staffFor($coach);

        $this->actingAs($coach, 'web');

        // Coach submits: keep A, drop B & C, add D → grant D, revoke B & C.
        $req = Request::create('/instructor/coach-staff/update/' . $staff->id, 'PUT', [
            'name'        => 'Priya',
            'email'       => $staff->email,
            'status'      => 'active',
            'role_id'     => $role->id,
            'permissions' => [$A->id, $D->id],
        ]);
        app()->instance('request', $req);
        app(CoachStaffController::class)->update($staff->id, $req);

        // Overrides recorded correctly.
        $this->assertDatabaseHas('staff_permission_overrides', ['user_id' => $staff->id, 'coach_staff_permission_id' => $D->id, 'effect' => 'grant']);
        $this->assertDatabaseHas('staff_permission_overrides', ['user_id' => $staff->id, 'coach_staff_permission_id' => $B->id, 'effect' => 'revoke']);
        $this->assertDatabaseHas('staff_permission_overrides', ['user_id' => $staff->id, 'coach_staff_permission_id' => $C->id, 'effect' => 'revoke']);

        // Effective (materialised) gate reflects override > role.
        $s = $staff->fresh();
        $this->assertTrue($svc->can($s, $A->slug));   // kept
        $this->assertFalse($svc->can($s, $B->slug));  // revoked
        $this->assertFalse($svc->can($s, $C->slug));  // revoked
        $this->assertTrue($svc->can($s, $D->slug));   // granted beyond role

        // The ROLE itself is unchanged (still has A, B, C).
        $roleIds = $role->fresh()->permissions->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$A->id, $B->id, $C->id], $roleIds);
    }

    public function test_reset_to_role_removes_overrides(): void
    {
        $svc   = app(CoachPermissionService::class);
        $coach = $this->coach();
        $A = $this->perm('courses');
        $B = $this->perm('coach-students');
        $role = CoachStaffRole::create(['role_name' => 'R' . uniqid(), 'role_slug' => 'r' . uniqid(), 'added_by' => $coach->id, 'status' => 1]);
        $role->permissions()->attach([$A->id, $B->id]);
        $staff = $this->staffFor($coach);
        $this->actingAs($coach, 'web');

        // First: revoke B (override).
        $r1 = Request::create('/x', 'PUT', ['name' => 'X', 'email' => $staff->email, 'status' => 'active', 'role_id' => $role->id, 'permissions' => [$A->id]]);
        app()->instance('request', $r1);
        app(CoachStaffController::class)->update($staff->id, $r1);
        $this->assertFalse($svc->can($staff->fresh(), $B->slug));

        // Then: submit the full role default (reset) → override gone, B restored.
        $r2 = Request::create('/x', 'PUT', ['name' => 'X', 'email' => $staff->email, 'status' => 'active', 'role_id' => $role->id, 'permissions' => [$A->id, $B->id]]);
        app()->instance('request', $r2);
        app(CoachStaffController::class)->update($staff->id, $r2);

        $this->assertTrue($svc->can($staff->fresh(), $B->slug));
        $this->assertSame(0, DB::table('staff_permission_overrides')->where('user_id', $staff->id)->count());
    }
}
