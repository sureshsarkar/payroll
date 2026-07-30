<?php

namespace App\Notifications;

use App\Models\CourseLiveClass;

/**
 * Sent to the eligible BATCH students when a coach/staff schedules a new live
 * class. In-app (bell + push) is always delivered; the email channel is sent
 * only when the creator opted to email students ($sendEmail) AND the student
 * hasn't opted out of "live_class_scheduled" mail.
 */
class LiveClassScheduledToStudent extends InAppNotification
{
    protected string $event = 'live_class_scheduled';
    protected string $emailTemplate = 'notif_live_class_scheduled';
    protected string $emailCtaLabel = 'Open live classes';

    private CourseLiveClass $liveClass;
    private string $courseTitle;
    private ?string $lessonTitle;
    private string $batchName;
    private string $coachName;
    private bool $sendEmail;

    public function __construct(
        CourseLiveClass $liveClass,
        string $courseTitle,
        ?string $lessonTitle = null,
        bool $sendEmail = false,
        string $batchName = '',
        string $coachName = ''
    ) {
        $this->liveClass = $liveClass;
        $this->coachId = ((int) \App\Models\Course::where("id", $liveClass->course_id)->value("instructor_id")) ?: null;
        $this->courseTitle = $courseTitle;
        $this->lessonTitle = $lessonTitle;
        $this->batchName = $batchName;
        $this->coachName = $coachName;
        $this->sendEmail = $sendEmail;
        $this->title = 'New live class scheduled';
        $this->body = '"' . \Str::limit($courseTitle, 50) . '"'
            . ($lessonTitle ? ' — ' . \Str::limit($lessonTitle, 40) : '')
            . ($batchName ? ' (' . \Str::limit($batchName, 30) . ')' : '')
            . ' on ' . formattedDateTime($liveClass->start_time);
        $this->icon = 'fa-video';
        $this->iconColor = '#3b82f6';
        $this->url = route('student.live-classes.index');
    }

    /**
     * In-app (database/broadcast/webpush) is always on. The email (mail)
     * channel is included only when the creator chose to email students — on
     * top of the per-user "live_class_scheduled" preference the base applies.
     * This keeps the coach's "send email" choice authoritative while still
     * respecting a student's opt-out.
     */
    public function via(object $notifiable): array
    {
        $channels = parent::via($notifiable);
        if (! $this->sendEmail) {
            $channels = array_values(array_filter($channels, fn ($c) => $c !== 'mail'));
        }
        return $channels;
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'    => $notifiable->name ?? '',
            'course_title' => $this->courseTitle,
            'lesson_title' => (string) ($this->lessonTitle ?? ''),
            // Phase 4.5: avoid a dangling "Batch:" / "Coach:" label when the
            // caller omitted the value (course-wide class / not passed).
            'batch_name'   => $this->batchName !== '' ? $this->batchName : '—',
            'coach_name'   => $this->coachName !== '' ? $this->coachName
                : (string) ($this->coachId ? (\App\Models\User::where('id', $this->coachId)->value('name') ?? '') : ''),
            'start_time'   => formattedDateTime($this->liveClass->start_time),
            'join_url'     => (string) ($this->liveClass->join_url ?: $this->url),
        ];
    }
}
