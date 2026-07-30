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
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\PreNotification::class,
        \App\Console\Commands\MigrateUploadsToS3::class,
        \App\Console\Commands\PaymentDueReminder::class,
        \App\Console\Commands\GrantCoachTrials::class,
        \App\Console\Commands\TrialExpiringReminder::class,
        \App\Console\Commands\AuditSmoke::class,
        \App\Console\Commands\AuditRefundIntegrity::class,
        \App\Console\Commands\MailTestSmtp::class,
        \App\Console\Commands\ZoomHealthCheck::class,
        \App\Console\Commands\ZoomRecreateMeetings::class,
        \App\Console\Commands\VerifyUpcomingLiveClasses::class,
        \App\Console\Commands\SyncLiveClassRecordings::class,
    ];
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Production cron entry that drives this:
        //   * * * * * cd /home/codesecu/lalagdukan.com/yoga && php artisan schedule:run >> /dev/null 2>&1

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

        // Live-class reminder emails. Lead time (minutes before start) is admin-
        // configurable via Settings → live_mail_send; default 15 min per spec.
        // 2026-06-26 — the command now runs every 5 minutes regardless of the
        // lead time (previously the cron frequency was coupled to it), so the
        // reminder lands close to the configured lead time. Per-(class,student)
        // dedupe (live_class_reminders_sent) guarantees exactly one send.
        $schedule->command('prenotification:live')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Audit 2026-05-18 phase 3 — fire scheduled announcements whose
        // scheduled_at is past. Every minute is overkill but cheap; the
        // command exits 0 immediately when nothing is due.
        $schedule->command('announcements:dispatch-scheduled')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Audit 2026-05-18 phase 4 — verify attendance for classes whose
        // scheduled end has passed. Runs every 5 minutes. Idempotent —
        // already-finalised classes (ended_at set) are skipped.
        $schedule->command('attendance:verify-ended-classes --grace=5')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // 2026-06-09 — auto-recheck pending/failed coach custom domains and
        // activate those that now point at us (handles DNS propagation lag
        // without the coach re-clicking Verify). Capped by verify_attempts.
        $schedule->command('domains:recheck-pending')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Audit 2026-05-18 phase 3 — daily 18:30 low-attendance alert.
        // After typical class hours; alerts the coach if any batch had
        // a class today with < 50% attendance.
        // Runs AFTER the every-5-min verifier so the numbers it reads
        // reflect verified state, not just "joined".
        $schedule->command('attendance:low-alert --threshold=50')
            ->dailyAt('18:30')
            ->withoutOverlapping();

        // Payment-due reminders for orders pending payment (1, 3, 7 days after creation).
        // Runs daily at 09:00 — student gets the prompt during their morning rather than overnight.
        $schedule->command('notify:payment-due')->dailyAt('09:00')->withoutOverlapping();

        // CRM: remind staff/coach about leads whose follow-up time has arrived.
        // Every 15 min so reminders are timely without spamming the worker.
        $schedule->command('leads:follow-up-reminders')->everyFifteenMinutes()->withoutOverlapping();

        // Free stuck "one coach = one active meeting" slots (TTL-expired / ended).
        $schedule->command('liveclass:release-stale')->everyFiveMinutes()->withoutOverlapping();

        // Membership expiry sweep — at 00:30 daily, mark any active memberships
        // whose expires_at is in the past as 'expired'. Wallet credit / referral
        // attribution stays intact; users can buy a new plan to reactivate.
        $schedule->call(function () {
            $count = app(\App\Services\MembershipService::class)->expirePastDue();
            if ($count > 0) {
                Cache::put('membership_last_expiry_count', $count, now()->addDays(7));
                \Log::info("Membership expiry sweep: marked $count memberships expired");
            }
        })->dailyAt('00:30')->name('membership-expire')->withoutOverlapping();

        // Trial-expiring reminders — runs at 09:00 (after the morning sweep,
        // before the coach starts their day) so they see the email + bell entry
        // when they sit down at their desk.
        $schedule->command('notify:trial-expiring')->dailyAt('09:00')->withoutOverlapping();

        // Zoom OAuth health probe — daily at 03:30, exchanges each instructor's
        // refresh token to detect dead/expiring credentials before students
        // hit a stuck "Joining Meeting…". State changes (ok → expiring/dead)
        // notify the instructor; daily nags are deliberately suppressed.
        $schedule->command('zoom:health-check --quiet-on-ok')->dailyAt('03:30')->withoutOverlapping();

        // Pre-class meeting verification — every 5 minutes, probe Zoom for
        // each upcoming live class (next 30 min) to confirm the meeting still
        // exists. Catches the "Zoom auto-deleted my 60-day-old scheduled
        // meeting" failure mode before students hit a stuck join. Notifies
        // the instructor once per row (state-change only).
        $schedule->command('live-class:verify')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Cloud Recording sync — every 30 minutes, pull recording metadata
        // for any live class that ended in the last 7 days. Idempotent
        // upsert so URL rotation (Zoom rotates signed cloudfront URLs)
        // refreshes the cache in place rather than duplicating rows.
        $schedule->command('recordings:sync')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

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
            if (!is_dir($logsDir)) return;
            foreach (glob($logsDir . '/laravel-*.log') as $log) {
                if (filemtime($log) < strtotime('-30 days')) {
                    @unlink($log);
                }
            }
        })->weeklyOn(1, '4:00')->name('logs-prune')->withoutOverlapping();

        // === Audit health checks (audit 2026-05-22) ===
        //
        // audit:smoke — 129 fast structural checks (idempotent, <30s on warm cache).
        // Daily at 07:00 so a failure email lands in the inbox before the workday.
        // Exit code mirrors pass/fail; failure goes to laravel.log + the mail
        // channel configured in config/logging.php if you wire one up.
        $schedule->command('audit:smoke')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/audit-smoke.log'));

        // audit:refund-integrity — verifies every refunded order in the last
        // 2 days has a matching wallet_reversed marker + correct decrement
        // amount. Daily at 06:30 (before the smoke audit so the operator
        // sees money-related issues first). Non-zero exit on discrepancy.
        $schedule->command('audit:refund-integrity --since=2')
            ->dailyAt('06:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/audit-refund-integrity.log'));

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
