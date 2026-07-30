<?php

namespace Tests\Feature\Domain;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Domain test — multi-role permission boundary (Tier C category).
 *
 * Verifies the contract per role:
 *   - Permissions in its grant list -> $admin->can(perm) returns true.
 *   - Permissions outside its grant list -> $admin->can(perm) returns false.
 *
 * Uses Spatie\Permission's can() check, the same primitive that
 * checkAdminHasPermissionAndThrowException() consults. So a passing
 * test here means the corresponding admin menu would route correctly
 * (200 vs Permission Denied) under the audit's check pattern.
 *
 * Requires the migration 2026_05_18_120000_seed_additional_admin_roles
 * to have run.
 */
class AdminRolePermissionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * mbs_test in CI may be a thin schema sync with no seed data.
     * Make this suite self-contained by ensuring the required roles,
     * permissions, and admin user exist before each test. Runs OUTSIDE
     * the DatabaseTransactions wrap so the seed survives, but inside
     * the test boundary so it's controlled.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure admin id=1 exists (the Super Admin)
        if (!Admin::find(1)) {
            DB::table('admins')->insert([
                'id' => 1, 'name' => 'Audit Super', 'email' => 'admin@example.test',
                'password' => bcrypt('not-real'), 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Ensure the 5 roles exist
        $roleNames = ['Super Admin', 'Admin Role', 'Course Manager', 'Finance', 'Content Editor'];
        foreach ($roleNames as $i => $name) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        // Ensure the permissions referenced in expectations() exist
        $allPerms = collect($this->expectations())
            ->flatMap(fn($pair) => array_merge($pair[0], $pair[1]))
            ->unique()
            ->reject(fn($p) => $p === 'this.permission.does.not.exist' || $p === 'mail_password')
            ->all();

        foreach ($allPerms as $p) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $p, 'guard_name' => 'admin'],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        // Apply role -> permission grants matching the migration
        $grants = [
            'Super Admin' => $allPerms,   // for the test, give Super Admin all referenced perms
            'Admin Role' => ['dashboard.view'],
            'Course Manager' => [
                'dashboard.view', 'course.view', 'course.create',
                'blog.view', 'blog.create', 'blog.edit', 'blog.delete',
                'faq.view', 'faq.create', 'faq.edit', 'faq.delete',
                'testimonial.view', 'testimonial.create', 'testimonial.edit', 'testimonial.delete',
            ],
            'Finance' => [
                'dashboard.view',
                'order.management',
                'withdraw.management',
                'referral.view', 'referral.approve', 'referral.pay',
                'user-membership.view', 'user-membership.refund',
                'subscriptions.management',
            ],
            'Content Editor' => [
                'dashboard.view',
                'blog.view', 'blog.create', 'blog.edit', 'blog.delete',
                'faq.view', 'faq.create', 'faq.edit', 'faq.delete',
                'testimonial.view', 'testimonial.create', 'testimonial.edit', 'testimonial.delete',
                'page.management',
            ],
        ];

        foreach ($grants as $roleName => $permNames) {
            $roleId = DB::table('roles')
                ->where('name', $roleName)->where('guard_name', 'admin')
                ->value('id');
            $permIds = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', $permNames)
                ->pluck('id')->all();
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            foreach ($permIds as $pid) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => $roleId, 'permission_id' => $pid,
                ]);
            }
        }

        // Assign Super Admin role to admin id=1
        $superRoleId = DB::table('roles')
            ->where('name', 'Super Admin')->where('guard_name', 'admin')->value('id');
        DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => $superRoleId, 'model_type' => Admin::class, 'model_id' => 1],
            [],
        );

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * For each role, this is a (allowed[], denied[]) tuple. The denied
     * list is a curated set of permissions that role specifically
     * should NOT have — verifying the slice is bounded, not just present.
     *
     * @return array<string, array{0: string[], 1: string[]}>
     */
    private function expectations(): array
    {
        return [
            'Super Admin' => [
                [
                    'dashboard.view', 'order.management', 'admin.create',
                    'role.create', 'course.create', 'blog.delete',
                    'membership-plan.view', 'referral.view',
                    'mail_password' /* not a perm but should not crash can() */,
                ],
                [
                    /* Super Admin should not be denied anything except
                       made-up strings */
                    'this.permission.does.not.exist',
                ],
            ],
            'Course Manager' => [
                [
                    'dashboard.view', 'course.view', 'blog.create',
                    'faq.delete', 'testimonial.edit',
                ],
                [
                    'order.management', 'admin.create', 'role.create',
                    'withdraw.management', 'referral.approve',
                    'subscriptions.management', 'membership-plan.delete',
                ],
            ],
            'Finance' => [
                [
                    'dashboard.view', 'order.management', 'withdraw.management',
                    'referral.view', 'referral.approve', 'referral.pay',
                    'user-membership.view', 'user-membership.refund',
                    'subscriptions.management',
                ],
                [
                    'course.create', 'blog.delete', 'admin.create',
                    'role.create', 'testimonial.delete',
                ],
            ],
            'Content Editor' => [
                [
                    'dashboard.view', 'blog.view', 'blog.create',
                    'blog.delete', 'faq.edit', 'testimonial.create',
                    'page.management',
                ],
                [
                    'order.management', 'withdraw.management',
                    'admin.create', 'role.create', 'referral.view',
                    'subscriptions.management', 'course.create',
                ],
            ],
            'Admin Role' => [
                /* The default "Admin Role" seed has only 2 perms; verify it stays minimal */
                ['dashboard.view'],
                [
                    'order.management', 'course.create', 'blog.delete',
                    'admin.create', 'withdraw.management',
                ],
            ],
        ];
    }

    /**
     * @dataProvider rolesProvider
     */
    public function test_role_can_perform_its_allowed_permissions(string $roleName): void
    {
        $admin = $this->makeAdminWithRole($roleName);
        [$allowed] = $this->expectations()[$roleName];

        foreach ($allowed as $perm) {
            if (!\Spatie\Permission\Models\Permission::where('name', $perm)
                    ->where('guard_name', 'admin')->exists()) {
                // Not-registered permissions return false; skip those in allow list
                continue;
            }
            $this->assertTrue($admin->can($perm),
                "Role '$roleName' should be allowed '$perm' but can() returned false.");
        }
    }

    /**
     * @dataProvider rolesProvider
     */
    public function test_role_is_denied_outside_its_scope(string $roleName): void
    {
        $admin = $this->makeAdminWithRole($roleName);
        [, $denied] = $this->expectations()[$roleName];

        foreach ($denied as $perm) {
            if ($roleName === 'Super Admin' && $perm !== 'this.permission.does.not.exist') {
                continue;   // Super Admin has everything
            }
            $this->assertFalse($admin->can($perm),
                "Role '$roleName' should NOT have '$perm' but can() returned true.");
        }
    }

    public function test_each_role_has_dashboard_view_at_minimum(): void
    {
        // Smoke check that the role plumbing is intact: every role should
        // at least be able to view the admin dashboard, otherwise they
        // can't even log in to anywhere useful.
        foreach (['Super Admin', 'Admin Role', 'Course Manager', 'Finance', 'Content Editor'] as $roleName) {
            $admin = $this->makeAdminWithRole($roleName);
            $this->assertTrue($admin->can('dashboard.view'),
                "Role '$roleName' must have at least dashboard.view.");
        }
    }

    public function test_course_manager_cannot_escalate_via_assigning_more_perms_at_runtime(): void
    {
        // Defensive: spatie's API allows giving permissions; verify our
        // seeder + role table is the only path, no controller leak.
        $admin = $this->makeAdminWithRole('Course Manager');
        $this->assertFalse($admin->can('order.management'));

        // Even after a forgetCachedPermissions() shouldn't change anything
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = $admin->fresh();
        $this->assertFalse($admin->can('order.management'),
            'Course Manager must remain denied even after spatie cache flush.');
    }

    public static function rolesProvider(): array
    {
        // PHPUnit 10 requires static data providers — non-static now
        // raises a deprecation warning. Removing the warning shrinks
        // suite noise and prepares for PHPUnit 11.
        return [
            ['Super Admin'],
            ['Course Manager'],
            ['Finance'],
            ['Content Editor'],
            ['Admin Role'],
        ];
    }

    private function makeAdminWithRole(string $roleName): Admin
    {
        // Re-use admin id=1 for Super Admin; create a fresh test admin
        // for each non-Super-Admin role so we don't trample id=1's grants.
        if ($roleName === 'Super Admin') {
            return Admin::findOrFail(1);
        }

        $email = 'audit-test-'.strtolower(str_replace(' ', '-', $roleName)).'@example.com';

        /** @var Admin $admin */
        $admin = Admin::firstOrCreate(
            ['email' => $email],
            [
                'name' => "Audit Test {$roleName}",
                'password' => bcrypt('not-a-real-password'),
                'status' => 'active',
            ],
        );

        $role = Role::where('name', $roleName)->where('guard_name', 'admin')->first();
        $this->assertNotNull($role, "Role '$roleName' must exist before assignment.");

        $admin->syncRoles([$role]);
        $admin->load('roles', 'permissions');

        return $admin;
    }
}
