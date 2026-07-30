<?php

namespace App\Console\Commands;

use App\Models\CourseLiveClass;
use App\Models\Course;
use App\Models\User;
use App\Services\ZoomApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Bulk-recreate Zoom meetings for every live class whose stored
 * meeting_id is "ghost" — i.e., the meeting was created under an
 * old Zoom account that the current S2S OAuth credentials can't
 * see, so the SDK launcher returns "The meeting number is not
 * found".
 *
 * Added 2026-05-08 alongside the S2S OAuth migration. Without this,
 * an operator would have to edit-and-save every live class manually
 * to bump start_time and force the LMS update path to recreate the
 * Zoom meeting under the new credentials.
 *
 * For each row of type='zoom':
 *   1. Resolve the course's instructor and their ZoomCredential.
 *   2. Mint an S2S access token via ZoomApiService.
 *   3. POST /v2/users/me/meetings with the no-passcode settings
 *      (mirrors LiveClassController::store / ::update exactly).
 *   4. Replace meeting_id, password, and join_url on the row.
 *
 * Idempotent: running twice in a row creates two distinct Zoom
 * meetings. Use --probe-first to only recreate ghost meetings
 * (skip ones that already work). --dry-run prints the plan
 * without calling Zoom.
 */
class ZoomRecreateMeetings extends Command
{
    protected $signature = 'zoom:recreate-meetings
        {--id=* : Recreate only these CourseLiveClass IDs}
        {--probe-first : Skip rows whose meeting_id is still reachable on Zoom}
        {--dry-run : Show what would happen, do not call Zoom}';

    protected $description = 'Recreate Zoom meetings for live classes whose stored meeting_id is a ghost (created under old credentials).';

    public function handle(ZoomApiService $zoom): int
    {
        $query = CourseLiveClass::query()
            ->where('type', 'zoom')
            ->whereNotNull('meeting_id')
            ->where('meeting_id', '!=', '');

        if ($ids = $this->option('id')) {
            $query->whereIn('id', $ids);
        }

        $rows = $query->orderBy('id')->get();

        if ($rows->isEmpty()) {
            $this->info('No zoom live classes match. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Examining %d live classes…', $rows->count()));
        $tally = ['recreated' => 0, 'skipped_alive' => 0, 'skipped_no_creds' => 0, 'failed' => 0];

        foreach ($rows as $row) {
            $context = $this->resolveContext($row);
            if (!$context) {
                $tally['skipped_no_creds']++;
                $this->warn(sprintf(
                    '  [SKIP] live-class id=%d — no instructor or no Zoom credentials',
                    $row->id
                ));
                continue;
            }
            [$lesson, $course, $instructor, $cred] = $context;

            // Optional probe: skip rows whose meeting_id Zoom can still find.
            if ($this->option('probe-first')) {
                [$status] = $zoom->probeMeeting($cred, (string) $row->meeting_id);
                if ($status === 'ok') {
                    $tally['skipped_alive']++;
                    $this->info(sprintf('  [LIVE] id=%d meeting_id=%s — meeting still on Zoom, skipping', $row->id, $row->meeting_id));
                    continue;
                }
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '  [DRY] id=%d would recreate "%s" for course %d (instructor %d)',
                    $row->id,
                    (string) ($lesson->title ?? ''),
                    (int) $course->id,
                    (int) $instructor->id
                ));
                continue;
            }

            $accessToken = $zoom->ensureFreshAccessToken($cred);
            if (!$accessToken) {
                $tally['skipped_no_creds']++;
                $this->error(sprintf(
                    '  [AUTH] id=%d — Zoom rejected creds for instructor %d',
                    $row->id, $instructor->id
                ));
                continue;
            }

            $startIso = $row->start_time
                ? date('c', strtotime((string) $row->start_time))
                : date('c', strtotime('+1 hour'));
            $duration = (int) ($lesson->duration ?? 60);
            $topic    = (string) ($lesson->title ?? 'Live class');

            try {
                $resp = Http::withToken($accessToken)
                    ->timeout(20)
                    ->post('https://api.zoom.us/v2/users/me/meetings', [
                        'topic'      => $topic,
                        'type'       => 2,
                        'start_time' => $startIso,
                        'duration'   => $duration,
                        'timezone'   => 'Asia/Kolkata',
                        'password'   => '',
                        'settings'   => [
                            'password'                       => false,
                            'meeting_authentication'         => false,
                            'waiting_room'                   => false,
                            'approval_type'                  => 2,
                            'join_before_host'               => true,
                            'jbh_time'                       => 0,
                            'host_video'                     => true,
                            'participant_video'              => true,
                            'mute_upon_entry'                => true,
                            'audio'                          => 'both',
                            'registrants_email_notification' => false,
                            'registration_type'              => 1,
                            'auto_recording'                 => 'none',
                        ],
                    ]);
            } catch (\Throwable $e) {
                $tally['failed']++;
                $this->error(sprintf('  [NET] id=%d — %s', $row->id, substr($e->getMessage(), 0, 180)));
                continue;
            }

            if (!$resp->successful()) {
                $tally['failed']++;
                $body = $resp->json();
                $msg = is_array($body) && isset($body['message']) ? (string) $body['message'] : ('HTTP ' . $resp->status());
                $this->error(sprintf('  [FAIL] id=%d HTTP %d — %s', $row->id, $resp->status(), substr($msg, 0, 180)));
                continue;
            }

            $body = $resp->json();
            $newId   = (string) ($body['id'] ?? '');
            $newJoin = (string) ($body['join_url'] ?? '');
            // Capture whatever passcode Zoom assigned. We request `password: false`
            // in the payload, but accounts under Zoom's "Require one security option"
            // policy (which Basic/free accounts can't disable) get a passcode auto-
            // assigned anyway. The signature endpoint forwards it to client.join()
            // so the launcher can clear Zoom's passcode handshake without exposing
            // the value in HTML source.
            $newPass = (string) ($body['password'] ?? '');

            DB::table('course_live_classes')->where('id', $row->id)->update([
                'meeting_id' => $newId,
                'password'   => $newPass,
                'join_url'   => $newJoin,
                'updated_at' => now(),
            ]);

            $tally['recreated']++;
            $this->info(sprintf(
                '  [OK]   id=%d old=%s new=%s',
                $row->id,
                (string) $row->meeting_id,
                $newId
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: %d recreated, %d skipped (alive), %d skipped (no creds), %d failed.',
            $tally['recreated'], $tally['skipped_alive'], $tally['skipped_no_creds'], $tally['failed']
        ));

        return $tally['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Find the lesson, course, instructor, and Zoom credential for a live-class row.
     * Returns null if any of these are missing.
     *
     * @return array{0: \App\Models\CourseChapterLesson, 1: Course, 2: User, 3: \App\Models\ZoomCredential}|null
     */
    private function resolveContext(CourseLiveClass $row): ?array
    {
        $lesson = $row->lesson;
        if (!$lesson) {
            return null;
        }
        $course = Course::find($lesson->course_id);
        if (!$course) {
            return null;
        }
        $instructor = User::find($course->instructor_id);
        if (!$instructor) {
            return null;
        }
        $cred = $instructor->zoom_credential;
        if (!$cred || !$cred->account_id || !$cred->client_id || !$cred->client_secret) {
            return null;
        }

        return [$lesson, $course, $instructor, $cred];
    }
}
