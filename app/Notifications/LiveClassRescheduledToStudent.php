<?php

namespace App\Notifications;

use App\Models\CourseLiveClass;

/**
 * 2026-06-22 — sent to the eligible BATCH students when a coach/staff changes
 * the start time of an existing live class. Coach-branded; in-app always,
 * email gated by the student's 'live_class_scheduled' mail preference. Batch
 * scoping is enforced by LiveClassNotificationService::recipients().
 */
class LiveClassRescheduledToStudent extends InAppNotification
{
    protected string $event = 'live_class_scheduled';
    protected string $emailCtaLabel = 'Open live classes';

    private CourseLiveClass $liveClass;
    private string $courseTitle;
    private string $batchName;
    private ?string $previousStartTime;

    public function __construct(CourseLiveClass $liveClass, string $courseTitle, string $batchName = '', ?string $previousStartTime = null)
    {
        $this->liveClass = $liveClass;
        $this->courseTitle = $courseTitle;
        $this->batchName = $batchName;
        $this->previousStartTime = $previousStartTime;

        $this->coachId = ((int) \App\Models\Course::where('id', $liveClass->course_id ?: optional($liveClass->lesson)->course_id)
            ->value('instructor_id')) ?: null;

        $newTime = formattedDateTime($liveClass->start_time);

        $this->title = 'Live class rescheduled';
        $this->body = '"' . \Str::limit($courseTitle, 50) . '"'
            . ($batchName ? ' (' . \Str::limit($batchName, 30) . ')' : '')
            . ' has a new time: ' . $newTime
            . ($previousStartTime ? ' (was ' . formattedDateTime($previousStartTime) . ')' : '');
        $this->icon = 'fa-calendar';
        $this->iconColor = '#f59e0b';
        $this->url = route('student.live-classes.index');
    }

    protected function placeholders(object $notifiable): array
    {
        return [
            'user_name'    => $notifiable->name ?? '',
            'course_title' => $this->courseTitle,
            'batch_title'  => $this->batchName,
            'new_time'     => formattedDateTime($this->liveClass->start_time),
            'old_time'     => $this->previousStartTime ? formattedDateTime($this->previousStartTime) : '',
            'classes_url'  => $this->url,
        ];
    }
}
