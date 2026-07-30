<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — Zoom Cloud Recording sync added on 2026-05-07.
 * The cron polls /v2/meetings/{id}/recordings every 30 minutes for
 * recently-ended live classes and caches metadata so the lesson player
 * can surface catch-up content for absent students.
 */
class LiveClassRecordingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_live_class_recordings_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('live_class_recordings'));
        foreach ([
            'course_live_class_id', 'zoom_recording_id',
            'file_type', 'file_extension',
            'play_url', 'download_url',
            'file_size', 'duration_seconds',
            'recording_start', 'recording_end',
        ] as $col) {
            $this->assertTrue(
                Schema::hasColumn('live_class_recordings', $col),
                "live_class_recordings.$col missing"
            );
        }
    }

    public function test_recordings_unique_per_meeting_file(): void
    {
        // Privacy + correctness: zoom returns the same file_id on a re-poll.
        // Without the unique we'd accumulate duplicate rows that the lesson
        // player would render multiple times.
        $row = \DB::selectOne(<<<SQL
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_NAME = 'live_class_recordings'
              AND TABLE_SCHEMA = DATABASE()
              AND NON_UNIQUE = 0
              AND INDEX_NAME = 'uq_lcr_meeting_file'
            LIMIT 1
        SQL);
        $this->assertNotNull(
            $row,
            'Unique index uq_lcr_meeting_file missing — recording sync would duplicate rows'
        );
    }

    public function test_recordings_sync_command_is_registered(): void
    {
        $commands = array_keys(Artisan::all());
        $this->assertContains(
            'recordings:sync',
            $commands,
            'recordings:sync artisan command missing — see app/Console/Commands/SyncLiveClassRecordings.php'
        );
    }

    public function test_recordings_sync_runs_cleanly_on_empty_window(): void
    {
        // No live classes that ended recently → command should exit 0
        // with the empty-window summary.
        $exit = Artisan::call('recordings:sync', ['--days' => 1]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString(
            'No live classes',
            Artisan::output(),
            'recordings:sync should print a clear message when there\'s nothing to sync'
        );
    }

    public function test_recordings_sync_scheduled(): void
    {
        $kernel = (string) file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString(
            'SyncLiveClassRecordings::class',
            $kernel,
            'SyncLiveClassRecordings not registered in Kernel::$commands array'
        );
        $this->assertStringContainsString(
            'recordings:sync',
            $kernel,
            'recordings:sync schedule entry missing'
        );
    }

    public function test_zoom_api_service_can_fetch_recordings(): void
    {
        $src = (string) file_get_contents(app_path('Services/ZoomApiService.php'));
        $this->assertStringContainsString(
            'fetchMeetingRecordings',
            $src,
            'ZoomApiService::fetchMeetingRecordings() missing — recordings:sync has nothing to call'
        );
        $this->assertStringContainsString(
            "/recordings",
            $src,
            'ZoomApiService should hit the /v2/meetings/{id}/recordings endpoint'
        );
    }
}
