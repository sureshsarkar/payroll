<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * UI/UX audit P0-2 — defense-in-depth on the Telescope viewer gate.
 *
 * The previous gate allowed any authenticated admin to view /telescope
 * on any non-local environment when TELESCOPE_ENABLED=true. The new
 * gate requires:
 *
 *   - local env  → open
 *   - APP_DEBUG=true → admins only (intentional debug session)
 *   - APP_DEBUG=false (prod default) → must ALSO have:
 *       TELESCOPE_ALLOW_PROD_VIEW=true
 *       admin email present in TELESCOPE_VIEWERS allowlist
 *
 * Two intentional toggles to reach sensitive data — not one.
 *
 * These tests run the gate directly, no HTTP probe needed.
 */
class DevToolingGateTest extends TestCase
{
    public function test_local_environment_allows_anyone(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->reloadGate();
        $this->assertTrue(Gate::allows('viewTelescope'));
    }

    public function test_non_local_with_no_admin_session_is_denied(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->reloadGate();
        // No actingAs — no admin session — must be denied.
        $this->assertFalse(Gate::allows('viewTelescope'));
    }

    public function test_non_local_with_app_debug_true_allows_admin(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        config(['app.debug' => true]);
        $admin = \App\Models\Admin::firstOrCreate(
            ['email' => 'devtool-gate-test@e2e-factory.test'],
            ['name' => 'DevTool Gate Test', 'password' => bcrypt('test-only')]
        );
        $this->actingAs($admin, 'admin');
        $this->reloadGate();
        $this->assertTrue(Gate::allows('viewTelescope'));
    }

    public function test_prod_with_app_debug_false_denies_admin_without_opt_in(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);
        // No TELESCOPE_ALLOW_PROD_VIEW set → denied even for admin.
        $admin = \App\Models\Admin::firstOrCreate(
            ['email' => 'devtool-gate-test@e2e-factory.test'],
            ['name' => 'DevTool Gate Test', 'password' => bcrypt('test-only')]
        );
        $this->actingAs($admin, 'admin');
        $this->reloadGate();
        $this->assertFalse(Gate::allows('viewTelescope'));
    }

    public function test_prod_with_opt_in_but_email_not_allowlisted_is_denied(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);
        putenv('TELESCOPE_ALLOW_PROD_VIEW=true');
        putenv('TELESCOPE_VIEWERS=approved@example.test');
        $admin = \App\Models\Admin::firstOrCreate(
            ['email' => 'devtool-gate-test@e2e-factory.test'],  // NOT in allowlist
            ['name' => 'DevTool Gate Test', 'password' => bcrypt('test-only')]
        );
        $this->actingAs($admin, 'admin');
        $this->reloadGate();
        $this->assertFalse(Gate::allows('viewTelescope'));

        putenv('TELESCOPE_ALLOW_PROD_VIEW');
        putenv('TELESCOPE_VIEWERS');
    }

    public function test_prod_with_opt_in_and_email_allowlisted_is_allowed(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);
        putenv('TELESCOPE_ALLOW_PROD_VIEW=true');
        putenv('TELESCOPE_VIEWERS=devtool-gate-test@e2e-factory.test');
        $admin = \App\Models\Admin::firstOrCreate(
            ['email' => 'devtool-gate-test@e2e-factory.test'],
            ['name' => 'DevTool Gate Test', 'password' => bcrypt('test-only')]
        );
        $this->actingAs($admin, 'admin');
        $this->reloadGate();
        $this->assertTrue(Gate::allows('viewTelescope'));

        putenv('TELESCOPE_ALLOW_PROD_VIEW');
        putenv('TELESCOPE_VIEWERS');
    }

    /**
     * The TelescopeServiceProvider's gate closure is registered once at
     * boot. To exercise different env states per test we re-register the
     * gate by re-instantiating the provider's gate definition.
     */
    private function reloadGate(): void
    {
        $provider = new \App\Providers\TelescopeServiceProvider($this->app);
        $r = new \ReflectionClass($provider);
        $m = $r->getMethod('gate');
        $m->setAccessible(true);
        $m->invoke($provider);
    }
}
