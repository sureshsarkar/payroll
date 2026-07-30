<?php

namespace Tests\Feature\Domain;

use App\Models\CoachStaff;
use App\Models\CoachStaffPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 13-July-Changes.docx — regression guards for the deterministic items.
 *
 *   #1 Dynamic browser-tab titles: every listed coach/student page must get a
 *      descriptive module title, never the generic "Coach Dashboard" /
 *      "Student Dashboard" (the reported bug was that panelModuleTitle()
 *      returned the panel label BEFORE the per-module fallback ran, so every
 *      unmapped instructor/student page showed the generic label).
 *
 *   #3 Staff Order create/edit permission: the Create + Edit gates use the
 *      granular catalog slugs coach-orders-create / coach-orders-edit. A staff
 *      member holding "Create Order" must pass; one without must be denied; a
 *      real coach always passes.
 *
 * The view-level items (#2 Talk to Us, #4 referral /register?ref=, #6 sidebar
 * labels) are covered by the Playwright specs + manual render verification;
 * #5 (enquiry coach-SMTP) was already shipped and routes through CoachMailer.
 */
class July13ChangesTest extends TestCase
{
    use DatabaseTransactions;

    /** Bind a request at $path so panelModuleTitle() reads it. */
    private function titleFor(string $path): string
    {
        $this->app->instance('request', Request::create('http://localhost/' . ltrim($path, '/'), 'GET'));
        return panelModuleTitle();
    }

    public function test_listed_pages_get_descriptive_tab_titles(): void
    {
        $expected = [
            'instructor/certificate-builder'    => 'Certificate Builder',
            'instructor/fees'                   => 'Fees',
            'instructor/offline-payments'       => 'Offline Payments',
            'instructor/my-plan'                => 'Plan & Billing',
            'instructor/zoom-setting'           => 'Zoom Settings',
            'instructor/youtube-setting'        => 'YouTube Settings',
            'instructor/staff-role'             => 'Staff Roles',
            'instructor/staff-permission'       => 'Staff Permissions',
            'instructor/brand-settings'         => 'Settings',
            'instructor/web-page'               => 'Website Builder',
            'instructor/subscription-histories' => 'Subscription History',
            'instructor/tax-settings'           => 'Tax Settings',
            'referral'                          => 'Referrals',
            'student/attendance'                => 'Attendance',
            'student/fees'                      => 'Fees',
        ];

        foreach ($expected as $path => $title) {
            $this->assertSame($title, $this->titleFor($path), "tab title for /$path");
            $this->assertNotContains($this->titleFor($path), ['Coach Dashboard', 'Student Dashboard'], "/$path must not fall back to the generic panel label");
        }
    }

    public function test_bare_dashboards_keep_the_panel_label(): void
    {
        $this->assertSame('Coach Dashboard', $this->titleFor('instructor'));
        $this->assertSame('Coach Dashboard', $this->titleFor('instructor/dashboard'));
        $this->assertSame('Student Dashboard', $this->titleFor('student'));
        $this->assertSame('Student Dashboard', $this->titleFor('student/dashboard'));
    }

    public function test_explicit_title_wins(): void
    {
        $this->assertSame('Custom Page', panelModuleTitle('Custom Page'));
    }

    // ── #3 Staff Order create/edit permission ────────────────────────────

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

    public function test_staff_with_create_order_permission_passes_create_gate(): void
    {
        $coach = $this->coach();
        $staff = $this->staffWith($coach, ['coach-orders', 'coach-orders-create']);
        Auth::guard('web')->login($staff);

        $this->assertSame(1, checkPermission('coach-orders'),            'can view the orders list');
        $this->assertSame(1, checkPermission('coach-orders', 'create'),  'create → coach-orders-create (held)');
        $this->assertSame(1, checkPermission('coach-orders', 'store'),   'store → coach-orders-create (held)');
        Auth::guard('web')->logout();
    }

    public function test_staff_without_create_order_permission_is_denied(): void
    {
        $coach = $this->coach();
        // Can VIEW orders but was NOT granted create.
        $staff = $this->staffWith($coach, ['coach-orders']);
        Auth::guard('web')->login($staff);

        $this->assertSame(1, checkPermission('coach-orders'),           'viewing is allowed');
        $this->assertSame(0, checkPermission('coach-orders', 'create'), 'create must be denied without coach-orders-create');
        $this->assertSame(0, checkPermission('coach-orders', 'store'),  'store must be denied without coach-orders-create');
        Auth::guard('web')->logout();
    }

    public function test_staff_edit_order_gate_uses_granular_edit_slug(): void
    {
        $coach = $this->coach();
        $staff = $this->staffWith($coach, ['coach-orders', 'coach-orders-edit']);
        Auth::guard('web')->login($staff);

        $this->assertSame(1, checkPermission('coach-orders', 'edit'),   'edit → coach-orders-edit (held)');
        $this->assertSame(1, checkPermission('coach-orders', 'update'), 'update → coach-orders-edit (held)');
        $this->assertSame(0, checkPermission('coach-orders', 'create'), 'edit-only staff cannot create');
        Auth::guard('web')->logout();
    }

    // ── #4 Referral URL (accessor used by the Affiliate page) ────────────

    public function test_referral_url_accessor_points_at_register(): void
    {
        config(['app.url' => 'https://example.test']);
        $u = $this->coach();
        $u->referral_code = 'abc123';
        $this->assertSame('https://example.test/register?ref=abc123', $u->referral_url);
        $this->assertStringNotContainsString('/?ref=', $u->referral_url);
    }

    public function test_real_coach_passes_every_order_gate(): void
    {
        $coach = $this->coach();
        Auth::guard('web')->login($coach);

        $this->assertSame(1, checkPermission('coach-orders'));
        $this->assertSame(1, checkPermission('coach-orders', 'create'));
        $this->assertSame(1, checkPermission('coach-orders', 'update'));
        Auth::guard('web')->logout();
    }
}
