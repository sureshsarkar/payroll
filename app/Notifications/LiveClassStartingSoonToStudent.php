<?php

namespace App\Notifications;

use App\Models\CourseLiveClass;

/**
 * Stored-in-bell version of the LiveClassStartingSoon broadcast event.
 * Triggered by the prenotification:live command N minutes before start
 * (lead time = the `live_mail_send` setting; set to 15 for a 15-minute
 * reminder). Batch-scoped recipients + once-per-(class,student) dedupe live in
 * the command; this class only renders the (coach-branded) email + bell entry.
 *
 * 2026-06-26 (Feature 3) — content enriched to carry every field the spec
 * requires: live class title, course name, batch name, date & time, meeting
 * link and coach/instructor name. Derived from the live class so the existing
 * call site (liveClass, courseTitle, minutesAhead) stays unchanged.
 */
class LiveClassStartingSoonToStudent extends InAppNotification
{
    protected string $event = 'live_class_starting_soon';
    protected string $emailTemplate = 'notif_live_class_starting_soon';
    protected string $emailCtaLabel = 'Join live class';

    private string $courseTitle;
    private int $minutesAhead;
    private CourseLiveClass $liveClass;
    private string $classTitle;
    private string $batchName;
    private string $coachName;
    private string $startsAt;

    public function __construct(CourseLiveClass $liveClass, string $courseTitle, int $minutesAhead)
    {
        $this->liveClass = $liveClass;
        $this->coachId = ((int) \App\Models\Course::where("id", $liveClass->course_id)->value("instructor_id")) ?: null;
        $this->courseTitle = $courseTitle;
        $this->minutesAhead = $minutesAhead;

        // Enrich content from the live class (cheap lookups; runs at dispatch).
        $this->classTitle = trim((string) (optional($liveClass->lesson)->title ?: $courseTitle));
        $this->batchName  = $liveClass->batch_id
            ? (string) (\App\Models\CourseBatch::where('id', $liveClass->batch_id)->value('title') ?: '')
            : '';
        $this->coachName  = $this->coachId
            ? (string) (\App\Models\User::where('id', $this->coachId)->value('name') ?: '')
            : '';
        try {
            $this->startsAt = $liveClass->start_time
                ? \Illuminate\Support\Carbon::parse($liveClass->start_time)->format('d M Y, h:i A')
                : '';
        } catch (\Throwable $e) {
            $this->startsAt = (string) $liveClass->start_time;
        }

        $this->title = 'Live class in ' . $minutesAhead . ' min';
        $this->body = '"' . \Str::limit($courseTitle, 60) . '" — join now from your dashboard.';
        $this->icon = 'fa-video';
        $this->iconColor = '#ef4444';
        $this->url = $liveClass->join_url ?: route('student.live-classes.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'    => $notifiable->name ?? '',
            'course_title' => $this->courseTitle,
            'class_title'  => $this->classTitle,
            'batch_name'   => $this->batchName,
            'coach_name'   => $this->coachName,
            'starts_at'    => $this->startsAt,
            'minutes'      => (string) $this->minutesAhead,
            'join_url'     => $this->url,
        ];
    }
}
