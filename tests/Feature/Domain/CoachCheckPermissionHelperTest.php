<?php

namespace Tests\Feature\Domain;

use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guard for the checkPermission() helper.
 *
 * 2026-07-04 bug: the required permission slug was derived from the REQUEST URL's
 * last segment, so a module whose permission slug differed from its route path
 * (e.g. slug `coach-coupons` on URL `/instructor/coupons`) built the bogus slug
 * `coach-coupons-coupons` and DENIED a staff member who actually held the
 * permission — the coach panel showed an error page even with access granted.
 *
 * The fix derives the slug from ($pageName, controller-action) — URL-independent.
 * These tests pin that: the same $pageName must resolve identically no matter
 * what the request URL happens to be.
 */
class CoachCheckPermissionHelperTest extends TestCase
{
    use DatabaseTransactions;

    private function coach(): User
    {
        return User::find(DB::table('users')->insertGetId([
            'role' => 'instructor', 'name' => 'Coach', 'email' => 'c' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no', 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function staffWith(User $coach, array $slugs): CoachStaff
    {
        $staff = CoachStaff::find(DB::table('users')->insertGetId([
            'role' => 'Manager', 'name' => 'Staff', 'email' => 's' . uniqid() . '@t.local',
            'password' => bcrypt('x'), 'status' => 'active', 'is_banned' => 'no',
            'coach_id' => $coach->id, 'added_by' => $coach->id, 'created_at' => now(), 'updated_at' => now(),
        ]));
        $ids = collect($slugs)->map(fn ($s) => CoachStaffPermission::firstOrCreate(['slug' => $s], ['name' => ucfirst($s)])->id)->all();
        $staff->permissions()->sync($ids);
        return $staff->fresh();
    }

    public function test_real_coach_always_passes(): void
    {
        $coach = $this->coach();
        Auth::guard('web')->login($coach);
        $this->assertSame(1, checkPermission('coach-coupons'));
        $this->assertSame(1, checkPermission('anything-at-all'));
        Auth::guard('web')->logout();
    }

    public function test_staff_with_bare_slug_can_access_index_regardless_of_url(): void
    {
        $coach = $this->coach();
        $staff = $this->staffWith($coach, ['coach-coupons']);
        Auth::guard('web')->login($staff);

        // The OLD bug: on URL /instructor/coupons this built `coach-coupons-coupons`
        // and returned 0. The fix must return 1 for the bare page slug — whatever
        // the request URL is.
        $_SERVER['REQUEST_URI'] = '/instructor/coupons';
        $this->assertSame(1, checkPermission('coach-coupons'), 'staff holding coach-coupons must access the coupons index');

        $_SERVER['REQUEST_URI'] = '/instructor/staff-role';
        $this->assertSame(1, checkPermission('coach-coupons'), 'result must be URL-independent');
        Auth::guard('web')->logout();
    }

    public function test_staff_without_slug_is_denied(): void
    {
        $coach = $this->coach();
        $staff = $this->staffWith($coach, ['courses']);
        Auth::guard('web')->login($staff);
        $this->assertSame(0, checkPermission('coach-coupons'), 'staff without coach-coupons must be denied');
        Auth::guard('web')->logout();
    }

    public function test_action_maps_to_granular_suffix(): void
    {
        $coach = $this->coach();
        // Has view + delete, NOT create/edit.
        $staff = $this->staffWith($coach, ['courses', 'courses-delete']);
        Auth::guard('web')->login($staff);

        $this->assertSame(1, checkPermission('courses'),               'index → bare slug');
        $this->assertSame(1, checkPermission('courses', 'index'),      'explicit index → bare slug');
        $this->assertSame(1, checkPermission('courses', 'destroy'),    'destroy → -delete (held)');
        $this->assertSame(1, checkPermission('courses', 'delete'),     'delete alias → -delete');
        $this->assertSame(0, checkPermission('courses', 'create'),     'create → -create (not held)');
        $this->assertSame(0, checkPermission('courses', 'store'),      'store → -create (not held)');
        $this->assertSame(0, checkPermission('courses', 'edit'),       'edit → -edit (not held)');
        $this->assertSame(0, checkPermission('courses', 'update'),     'update → -edit (not held)');
        Auth::guard('web')->logout();
    }
}
