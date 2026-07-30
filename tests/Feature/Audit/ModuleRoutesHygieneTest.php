<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * White-label hygiene: prove the module-route safety net works.
 *
 * Three contracts under test:
 *
 *   1. modules:verify-routes exits 0 on a clean repo. If this fails
 *      in CI, somebody pushed a module that ships without one of the
 *      required route files — fix before merge.
 *
 *   2. modules:verify-routes exits non-zero when a required route
 *      file is missing. Proves the deploy gate actually gates.
 *
 *   3. Every Modules/<x>/app/Providers/RouteServiceProvider extends
 *      App\Providers\BaseModuleRouteServiceProvider. Catches the case
 *      where someone scaffolds a new module via php artisan module:make
 *      and forgets to switch the parent class — the new module would
 *      ship without the missing-file guard.
 */
class ModuleRoutesHygieneTest extends TestCase
{
    /* ──────────────── verify-routes command ──────────────── */

    public function test_verify_routes_command_passes_on_clean_repo(): void
    {
        $exit = Artisan::call('modules:verify-routes');
        $this->assertSame(0, $exit,
            'modules:verify-routes failed on a clean repo — at least one ' .
            'enabled module is missing routes/web.php or routes/api.php. ' .
            'Run the command locally to see which.'
        );
    }

    public function test_verify_routes_command_fails_when_a_required_file_is_missing(): void
    {
        // Pick the first enabled module + temporarily move its api.php
        // aside. The command MUST exit non-zero, then we restore.
        $statuses = json_decode(file_get_contents(base_path('modules_statuses.json')), true);
        $victim = collect($statuses)->filter()->keys()->first();
        $this->assertNotNull($victim, 'No enabled module to victimise');

        $apiPath = base_path("Modules/$victim/routes/api.php");
        $bakPath = base_path("Modules/$victim/routes/.api.php.test-bak");

        if (! file_exists($apiPath)) {
            $this->markTestSkipped("Module $victim has no api.php — pick a different victim");
        }

        rename($apiPath, $bakPath);
        try {
            $exit = Artisan::call('modules:verify-routes');
            $this->assertSame(1, $exit, 'verify-routes did not fail on a missing api.php');
        } finally {
            rename($bakPath, $apiPath);
        }
    }

    /* ──────────────── BaseModuleRouteServiceProvider ──────────────── */

    public function test_every_module_route_provider_extends_the_shared_base(): void
    {
        $providers = glob(base_path('Modules/*/app/Providers/RouteServiceProvider.php'));
        $this->assertNotEmpty($providers, 'No module route providers found — wrong glob path?');

        $offenders = [];
        foreach ($providers as $file) {
            $src = file_get_contents($file);
            if (! str_contains($src, 'BaseModuleRouteServiceProvider')) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertEmpty(
            $offenders,
            "These module RouteServiceProviders do NOT extend " .
            "App\\Providers\\BaseModuleRouteServiceProvider — they ship " .
            "without the missing-file guard. Update them to extend the base " .
            "or risk a single missing route file taking down the whole app:\n  - " .
            implode("\n  - ", $offenders)
        );
    }

    public function test_base_provider_guard_logs_a_warning_on_missing_file(): void
    {
        // Pick a real enabled module + temporarily hide its web.php.
        // The base provider's mapWebRoutes() must (a) NOT throw and
        // (b) log a warning with our agreed shape so ops can grep.
        $statuses = json_decode(file_get_contents(base_path('modules_statuses.json')), true);
        $victim = collect($statuses)->filter()->keys()->first();
        $webPath = base_path("Modules/$victim/routes/web.php");
        $bakPath = base_path("Modules/$victim/routes/.web.php.test-bak");

        if (! file_exists($webPath)) {
            $this->markTestSkipped("Module $victim has no web.php — pick a different victim");
        }

        rename($webPath, $bakPath);
        try {
            Log::spy();

            $stub = new class(app()) extends \App\Providers\BaseModuleRouteServiceProvider {
                public string $moduleName      = ''; // set below
                public string $moduleNamespace = ''; // set below
            };
            $stub->moduleName      = $victim;
            $stub->moduleNamespace = "Modules\\$victim\\app\\Http\\Controllers";

            $ref = new \ReflectionMethod($stub, 'mapWebRoutes');
            $ref->setAccessible(true);
            $ref->invoke($stub); // must not throw

            Log::shouldHaveReceived('warning')
                ->with('module-route-missing', \Mockery::on(function ($ctx) use ($victim) {
                    return is_array($ctx)
                        && ($ctx['module'] ?? null) === $victim
                        && ($ctx['channel'] ?? null) === 'web'
                        && str_contains((string) ($ctx['path'] ?? ''), 'web.php');
                }))
                ->once();
        } finally {
            rename($bakPath, $webPath);
        }
    }
}
