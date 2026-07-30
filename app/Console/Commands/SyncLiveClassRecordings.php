<?php

namespace App\Console\Commands;

use App\Models\CourseLiveClass;
use App\Models\LiveClassRecording;
use App\Services\ZoomApiService;
use Illuminate\Console\Command;

/**
 * Polls Zoom Cloud Recordings for live classes that ended recently and
 * caches the per-file metadata in `live_class_recordings`. The lesson
 * player surfaces these as catch-up content for absent students.
 *
 * Why a cron rather than a webhook: Zoom's "recording.completed" webhook
 * is great when it arrives, but webhook delivery is lossy on free-plan
 * accounts and our deploy may not have the public URL Zoom needs. A 30
 * minute polling cron is conservative + idempotent + matches the rest
 * of our Zoom integration.
 *
 * Window: classes that ended in the last 7 days. Zoom typically has
 * recordings ready within ~10 minutes of meeting end, but processing
 * occasionally takes hours; 7 days is safe headroom.
 *
 * Idempotent: upserts on (course_live_class_id, zoom_recording_id) so
 * re-running the cron updates URLs (which expire/rotate) without
 * creating duplicate rows.
 */
class SyncLiveClassRecordings extends Command
{
    protected $signature = 'recordings:sync
        {--days=7 : Look back this many days for ended classes}
        {--live-class= : Only sync the specified course_live_classes.id}';

    protected $description = 'Pull Zoom Cloud Recordings for recent live classes and cache metadata for the lesson player.';

    public function __construct(private readonly ZoomApiService $zoom)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(1, min(60, (int) $this->option('days')));

        $query = CourseLiveClass::with([
                'lesson:id,course_id',
                'lesson.course:id,instructor_id',
                'lesson.course.instructor:id',
                'lesson.course.instructor.zoom_credential',
            ])
            ->where('type', 'zoom')
            ->whereNotNull('meeting_id')
            ->where('meeting_id', '!=', '')
            ->where('start_time', '>=', now()->subDays($days))
            ->where('start_time', '<=', now()->subMinutes(15));

        if ($id = $this->option('live-class')) {
            $query->where('id', $id);
        }

        $classes = $query->get();
        if ($classes->isEmpty()) {
            $this->info('No live classes in the recording-sync window.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Polling Zoom recordings for %d live classes…', $classes->count()));
        $tally = ['ok' => 0, 'no_recordings' => 0, 'error' => 0, 'skipped' => 0];

        foreach ($classes as $class) {
            $cred = $class->lesson?->course?->instructor?->zoom_credential;
            if (!$cred) {
                $tally['skipped']++;
                continue;
            }

            [$status, $payload] = $this->zoom->fetchMeetingRecordings($cred, (string) $class->meeting_id);
            $tally[$status] = ($tally[$status] ?? 0) + 1;

            if ($status === 'ok' && is_array($payload)) {
                $synced = $this->upsertRecordings($class->id, $payload);
                $this->info(sprintf(
                    '  [OK] live_class=%d meeting=%s — %d files',
                    $class->id, $class->meeting_id, $synced
                ));
                continue;
            }

            $message = is_string($payload) ? $payload : 'no_recordings';
            if ($status === 'error') {
                $this->warn(sprintf('  [ERROR] live_class=%d %s', $class->id, $message));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: %d ok, %d no_recordings, %d error, %d skipped',
            $tally['ok'], $tally['no_recordings'], $tally['error'], $tally['skipped']
        ));

        return self::SUCCESS;
    }

    /**
     * Map Zoom's recording_files[] JSON onto LiveClassRecording rows
     * via upsert keyed on (course_live_class_id, zoom_recording_id).
     */
    private function upsertRecordings(int $classId, array $files): int
    {
        $count = 0;
        foreach ($files as $file) {
            $zoomId = (string) ($file['id'] ?? '');
            if ($zoomId === '') continue;

            LiveClassRecording::updateOrCreate(
                [
                    'course_live_class_id' => $classId,
                    'zoom_recording_id'    => $zoomId,
                ],
                [
                    'file_type'        => substr((string) ($file['recording_type'] ?? $file['file_type'] ?? ''), 0, 32),
                    'file_extension'   => substr((string) ($file['file_extension'] ?? $file['file_type'] ?? ''), 0, 16),
                    'play_url'         => $file['play_url'] ?? null,
                    'download_url'     => $file['download_url'] ?? null,
                    'file_size'        => isset($file['file_size']) ? (int) $file['file_size'] : null,
                    'duration_seconds' => null, // Zoom doesn't return per-file duration in this endpoint
                    'recording_start'  => isset($file['recording_start']) ? \Carbon\Carbon::parse($file['recording_start']) : null,
                    'recording_end'    => isset($file['recording_end'])   ? \Carbon\Carbon::parse($file['recording_end'])   : null,
                ]
            );
            $count++;
        }
        return $count;
    }
}
