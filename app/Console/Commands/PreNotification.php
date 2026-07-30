<?php

namespace App\Console\Commands;

use App\Models\CourseLiveClass;
use App\Services\LiveClassNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PreNotification extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prenotification:live';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Live Class Pre-Notification';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle() {
        $now = Carbon::now();
        // Lead time before start — admin-configurable; default 15 min per spec.
        $minutesAhead = (int) (cache()->get('setting')?->live_mail_send ?? 15);
        $minutesAhead = max(1, min(60, $minutesAhead));   // clamp 1..60

        // The command runs every 5 min (Kernel) and looks ahead by the lead
        // time, so a class is first caught ~lead-time minutes before start and
        // can't slip between runs. Dedup is enforced by the
        // live_class_reminders_sent ledger (#8, 2026-05-12), not the window
        // size, so a slightly wide window is safe (still exactly one send).
        $cronPeriod = 5;                                  // schedule.everyFiveMinutes
        // Window is at least 2× the cron period (10 min) so a class can never
        // fall through the gap between two 5-minute runs. Dedup (the ledger)
        // still guarantees exactly one send per (class, student) (2026-06-30).
        $lookahead  = max($minutesAhead, $cronPeriod * 2);
        $futureTime = $now->copy()->addMinutes($lookahead);

        // Skip cancelled classes — students must never be reminded about a class
        // the coach already cancelled (2026-06-30).
        $liveClasses = CourseLiveClass::whereBetween('start_time', [$now, $futureTime])
            ->whereNull('cancelled_at')
            ->get();

        $recipientService = app(LiveClassNotificationService::class);
        $remindersSent = 0;

        foreach ($liveClasses as $liveClass) {
            // Course title is derived from the live class's own course_id (the
            // authoritative scope used by recipients()), falling back to the
            // lesson's course only if course_id is unset. This keeps the cron
            // self-contained and avoids a hard dependency on the lesson chain.
            $courseId = (int) ($liveClass->course_id ?: optional($liveClass->lesson)->course_id);
            $courseTitle = (string) (\App\Models\Course::where('id', $courseId)->value('title') ?? '');

            // TENANT SAFETY (2026-06-22): recipients are BATCH-SCOPED via the
            // single source of truth, not course-wide. The previous query
            // pulled every enrolled student in the course, so students in a
            // DIFFERENT batch received reminders for a class they can't join.
            // recipients() filters course + batch + has_access + active/unbanned.
            $users = $recipientService->recipients($liveClass);

            if ($users->isEmpty()) {
                continue;
            }

            // Per-user dedup against the DB ledger. The previous
            // implementation used \Cache, which silently re-fires when
            // an admin runs `php artisan cache:clear` — students then
            // get spammed with reminder mails for the same class.
            // The live_class_reminders_sent table is authoritative.
            $alreadySent = \Illuminate\Support\Facades\DB::table('live_class_reminders_sent')
                ->where('course_live_class_id', $liveClass->id)
                ->whereIn('user_id', $users->pluck('id')->all())
                ->pluck('user_id')
                ->all();
            $alreadySentSet = array_flip(array_map('intval', $alreadySent));
            $usersToNotify = $users->reject(fn ($u) => isset($alreadySentSet[(int) $u->id]));

            // The reminder EMAIL is delivered by LiveClassStartingSoonToStudent's
            // mail channel (coach-branded, per-coach SMTP, respects the user's
            // 'live_class_starting_soon' mail preference). The old direct
            // MailSenderService send was removed: it was platform-branded,
            // ignored preferences, and duplicated this notification's email.

            // Real-time toast + persisted bell entry for each enrolled student.
            foreach ($usersToNotify as $user) {
                try {
                    \App\Events\LiveClassStartingSoon::dispatch(
                        (int) $user->id,
                        $liveClass,
                        $courseTitle,
                        $minutesAhead
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Broadcast LiveClassStartingSoon failed: ' . $e->getMessage());
                }

                try {
                    $user->notify(new \App\Notifications\LiveClassStartingSoonToStudent(
                        $liveClass,
                        $courseTitle,
                        $minutesAhead
                    ));
                } catch (\Throwable $e) {
                    \Log::warning('Notify LiveClassStartingSoon (in-app) failed: ' . $e->getMessage());
                }

                // Write the ledger row. Use insertOrIgnore to handle
                // races between concurrent cron invocations (vanishingly
                // unlikely but cheap to defend against).
                \Illuminate\Support\Facades\DB::table('live_class_reminders_sent')->insertOrIgnore([[
                    'course_live_class_id' => $liveClass->id,
                    'user_id'              => $user->id,
                    'sent_at'              => now(),
                ]]);
                $remindersSent++;
            }
        }

        // Always log a one-line summary so the cron is observable in production:
        // - if this line never appears in laravel.log, schedule:run is NOT firing
        //   (server cron problem);
        // - if classes_in_window > 0 but reminders_sent = 0 with "Notify ... failed"
        //   warnings above, it's a mail/SMTP problem, not this command.
        \Log::info('prenotification:live completed', [
            'lookahead_min'    => $lookahead,
            'classes_in_window'=> $liveClasses->count(),
            'reminders_sent'   => $remindersSent,
        ]);
    }
}
