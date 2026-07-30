<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — pre-class meeting verification cron added on
 * 2026-05-07 to catch Zoom-side meeting deletions before students hit
 * a stuck "Joining Meeting…" splash. Fails loudly if any of the
 * load-bearing parts are removed.
 */
class PreClassVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_course_live_classes_has_verification_columns(): void
    {
        $cols = Schema::getColumnListing('course_live_classes');
        foreach (['verification_status', 'verification_message', 'last_verified_at'] as $col) {
            $this->assertContains(
                $col,
                $cols,
                "course_live_classes.$col missing — pre-class verifier relies on it"
            );
        }
    }

    public function test_live_class_verify_command_is_registered(): void
    {
        $commands = array_keys(Artisan::all());
        $this->assertContains(
            'live-class:verify',
            $commands,
            'live-class:verify artisan command missing — see app/Console/Commands/VerifyUpcomingLiveClasses.php'
        );
    }

    public function test_live_class_verify_runs_cleanly_on_empty_window(): void
    {
        // No live classes scheduled in the next 0 minutes → command should
        // exit 0 with "No live classes need verification…" and not error.
        $exitCode = Artisan::call('live-class:verify', ['--window' => 0]);
        $this->assertSame(0, $exitCode, 'live-class:verify failed on empty input');
        $this->assertStringContainsString('No live classes need verification', Artisan::output());
    }

    public function test_live_class_verify_is_in_kernel_commands_array_and_scheduled(): void
    {
        $kernelSrc = (string) file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString(
            'VerifyUpcomingLiveClasses::class',
            $kernelSrc,
            'VerifyUpcomingLiveClasses not registered in Kernel::$commands array'
        );
        $this->assertStringContainsString(
            "live-class:verify",
            $kernelSrc,
            'live-class:verify schedule entry missing from Kernel.php'
        );
    }

    public function test_zoom_meeting_missing_notification_class_exists(): void
    {
        $this->assertTrue(
            class_exists(\App\Notifications\ZoomMeetingMissingForUpcomingClass::class),
            'ZoomMeetingMissingForUpcomingClass notification class missing'
        );
    }

    public function test_zoom_api_service_class_exists(): void
    {
        $this->assertTrue(
            class_exists(\App\Services\ZoomApiService::class),
            'ZoomApiService missing — verifier and (future) callers depend on it'
        );
    }
}
