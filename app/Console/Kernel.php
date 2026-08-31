<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * Commands are auto-discovered from app/Console/Commands by commands()
     * below, so this list only needs entries that live elsewhere. LMS removal
     * phase 2 (2026-08-27) emptied it: every command it named
     * (PreNotification, PaymentDueReminder, GrantCoachTrials,
     * TrialExpiringReminder, AuditSmoke, AuditRefundIntegrity, ZoomHealthCheck,
     * ZoomRecreateMeetings, VerifyUpcomingLiveClasses, SyncLiveClassRecordings)
     * was LMS-only and has been deleted, and the two that survive
     * (MigrateUploadsToS3, MailTestSmtp) are discovered anyway.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Production cron entry that drives this:
        //   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1

        // LMS removal phase 2 (2026-08-27) — removed every LMS-driven job:
        // live-class reminders + verification + recording sync + stale-meeting
        // release, Zoom OAuth health probes, scheduled course announcements,
        // batch attendance verification and the low-attendance alert,
        // order payment-due reminders, CRM lead follow-ups, coach custom-domain
        // rechecks, membership expiry sweep, trial-expiring reminders, and the
        // audit:smoke / audit:refund-integrity health checks (both commands
        // were order- and course-shaped).

        // Drain queued jobs (mail sends, broadcasts, etc.). --stop-when-empty so a single
        // batch finishes within a minute instead of running as a daemon.
        $schedule->command('queue:work --tries=3 --stop-when-empty --max-time=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Heartbeat — last successful schedule:run timestamp lives in cache and the
        // admin dashboard can show "cron last ran X minutes ago" using this. If the
        // cache key is older than 5 minutes, cron has stopped running.
        $schedule->call(function () {
            Cache::put('cron_last_run', now()->toIso8601String(), now()->addDays(7));
        })->everyMinute()->name('heartbeat')->withoutOverlapping();

        // Daily housekeeping (3am server time)
        // - Clear expired DB sessions if SESSION_DRIVER=database
        // - Prune soft-deleted records older than 30 days (uses Prunable trait if added to models)
        // - Clean up old failed jobs
        $schedule->command('auth:clear-resets')->daily();
        $schedule->command('model:prune')->daily();
        $schedule->command('queue:prune-failed --hours=720')->daily(); // keep 30 days of failed jobs

        // Telescope: keep last 24h of entries; without this the telescope_entries
        // table grows unbounded and slows down unrelated queries.
        $schedule->command('telescope:prune --hours=24')->daily();

        // Weekly: prune old log files (storage/logs is daily-rotating; remove >30 days)
        $schedule->call(function () {
            $logsDir = storage_path('logs');
            if (!is_dir($logsDir)) {
                return;
            }
            foreach (glob($logsDir . '/laravel-*.log') as $log) {
                if (filemtime($log) < strtotime('-30 days')) {
                    @unlink($log);
                }
            }
        })->weeklyOn(1, '4:00')->name('logs-prune')->withoutOverlapping();

        // === Backups (spatie/laravel-backup) ===
        // Daily: full DB + selected file backup at 02:00 server time
        $schedule->command('backup:clean')->daily()->at('01:30');
        $schedule->command('backup:run')->daily()->at('02:00');
        // Hourly: monitor backup health (alerts via mail if backups go missing/stale)
        $schedule->command('backup:monitor')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
