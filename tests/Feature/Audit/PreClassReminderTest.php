<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — pre-class reminder hardening (#8, 2026-05-12).
 *
 * The `prenotification:live` console command emails enrolled students
 * ahead of a live class starting. Previously cache-deduped, which
 * silently re-fired on `cache:clear`. Hardened to use a DB ledger
 * (`live_class_reminders_sent`) so dedup is authoritative.
 */
class PreClassReminderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reminder_ledger_table_exists_with_unique_constraint(): void
    {
        $this->assertTrue(
            Schema::hasTable('live_class_reminders_sent'),
            'live_class_reminders_sent ledger missing — duplicate reminders cannot be prevented'
        );
        foreach (['course_live_class_id', 'user_id', 'sent_at'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('live_class_reminders_sent', $col),
                "live_class_reminders_sent.$col missing"
            );
        }
        // Verify the unique constraint exists — without it, concurrent
        // crons could double-insert and double-send.
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM live_class_reminders_sent WHERE Key_name = 'lcrs_class_user_uniq'"
        );
        $this->assertNotEmpty(
            $indexes,
            'live_class_reminders_sent missing the lcrs_class_user_uniq unique index — concurrent cron runs can double-insert'
        );
    }

    public function test_command_uses_db_ledger_not_cache_for_dedup(): void
    {
        $src = (string) file_get_contents(
            app_path('Console/Commands/PreNotification.php')
        );

        // The hardened command must read the ledger BEFORE notifying,
        // and write a row AFTER notifying. The old cache-based dedup
        // was fragile because cache:clear wiped it.
        $this->assertStringContainsString(
            'live_class_reminders_sent',
            $src,
            'PreNotification must consult the DB ledger — cache dedup re-fires on cache:clear'
        );
        $this->assertStringContainsString(
            'insertOrIgnore',
            $src,
            'PreNotification must use insertOrIgnore on the ledger to handle concurrent cron races'
        );
        // We deliberately want NO \Cache::has() guard left over from the
        // old implementation, otherwise we'd run two dedup paths and
        // either could let a duplicate through.
        $offsetHandle = strpos($src, 'public function handle');
        $handleBody   = substr($src, $offsetHandle, 5000);
        $this->assertDoesNotMatchRegularExpression(
            '/\\\\?Cache::has\\(/',
            $handleBody,
            'PreNotification still has the old \Cache::has() dedup — remove it; DB ledger is authoritative'
        );
    }

    public function test_command_widens_lookahead_window_beyond_cron_period(): void
    {
        // The cron runs every 5 min. If the look-ahead window is only 5
        // min wide, a class scheduled at T+6m can fall through the gap
        // (T → T+5 misses it; next run T+5 → T+10 sees it... but with
        // a 5m window even that's fragile). Widening to 2× cron period
        // gives every class at least one tick where it's in window.
        $src = (string) file_get_contents(
            app_path('Console/Commands/PreNotification.php')
        );
        $this->assertStringContainsString(
            '$cronPeriod = 5',
            $src,
            'PreNotification must define the cron period explicitly so the lookahead window stays in sync'
        );
        $this->assertStringContainsString(
            'max($minutesAhead, $cronPeriod * 2)',
            $src,
            'PreNotification lookahead must be at least 2× the cron period to avoid window-gap misses'
        );
    }

    public function test_schedule_calls_command_every_five_minutes(): void
    {
        $kernel = (string) file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString(
            "prenotification:live",
            $kernel,
            'Kernel must schedule the prenotification:live command'
        );
        // The window-width contract above assumes a 5-min cron period.
        // If a future refactor changes the period to e.g. ->hourly(),
        // the window math breaks silently. Catch that here.
        $this->assertStringContainsString(
            'everyFiveMinutes',
            $kernel,
            'Kernel must run prenotification:live every 5 minutes — wider cron period breaks the window-gap math'
        );
    }
}
