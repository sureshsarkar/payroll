<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — the daily Zoom OAuth health probe + admin
 * dashboard added on 2026-05-07 in response to the silent
 * invalid_grant failure that broke instructor 1079's live classes
 * for 30 days.
 *
 * Fails loudly if any of the load-bearing parts are removed:
 *  - the schema columns that store the probe result
 *  - the artisan command + its registration
 *  - the admin dashboard route
 *  - the schedule entry that runs the probe daily
 */
class ZoomHealthMonitorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zoom_credentials_has_health_columns(): void
    {
        $cols = Schema::getColumnListing('zoom_credentials');
        foreach (['health_status', 'health_message', 'last_health_check_at'] as $col) {
            $this->assertContains(
                $col,
                $cols,
                "zoom_credentials.$col missing — health monitor relies on it"
            );
        }
    }

    public function test_zoom_health_check_command_is_registered(): void
    {
        $commands = array_keys(Artisan::all());
        $this->assertContains(
            'zoom:health-check',
            $commands,
            'zoom:health-check artisan command missing — see app/Console/Commands/ZoomHealthCheck.php'
        );
    }

    public function test_zoom_health_check_runs_without_error_on_empty_input(): void
    {
        // Run the command against an instructor that doesn't exist; it should
        // simply report "No zoom_credentials rows to check." and exit 0.
        $exitCode = Artisan::call('zoom:health-check', ['--instructor' => -1]);
        $this->assertSame(0, $exitCode, 'zoom:health-check failed on empty input');
        $this->assertStringContainsString('No zoom_credentials rows', Artisan::output());
    }

    public function test_admin_zoom_health_routes_are_registered(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ([
            'admin.zoom-health.index',
            'admin.zoom-health.probe-all',
            'admin.zoom-health.probe-one',
        ] as $name) {
            $this->assertContains(
                $name,
                $names,
                "Route '$name' missing — admin Zoom-health dashboard won't work"
            );
        }
    }

    public function test_zoom_health_check_is_in_kernel_commands_array(): void
    {
        $kernelSrc = (string) file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString(
            'ZoomHealthCheck::class',
            $kernelSrc,
            'ZoomHealthCheck not registered in Kernel::$commands array — schedule entry will silently no-op'
        );
        $this->assertStringContainsString(
            "zoom:health-check",
            $kernelSrc,
            'zoom:health-check schedule entry missing from Kernel.php'
        );
    }

    public function test_zoom_reconnect_required_notification_class_exists(): void
    {
        $this->assertTrue(
            class_exists(\App\Notifications\ZoomReconnectRequired::class),
            'ZoomReconnectRequired notification class missing — instructor wouldn\'t be told their token died'
        );
    }
}
