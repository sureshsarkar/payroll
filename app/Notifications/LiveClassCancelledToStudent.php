<?php

namespace App\Notifications;

use App\Models\CourseLiveClass;

/**
 * 2026-06-22 — sent to the eligible BATCH students when a coach/staff cancels
 * (deletes) a scheduled live class. Coach-branded; batch scoping enforced by
 * LiveClassNotificationService::recipients(). Must be dispatched BEFORE the
 * live class row is deleted.
 */
class LiveClassCancelledToStudent extends InAppNotification
{
    protected string $event = 'live_class_scheduled';
    protected string $emailCtaLabel = 'Open live classes';

    private CourseLiveClass $liveClass;
    private string $courseTitle;
    private string $batchName;

    public function __construct(CourseLiveClass $liveClass, string $courseTitle, string $batchName = '')
    {
        $this->liveClass   = $liveClass;
        $this->courseTitle = $courseTitle;
        $this->batchName   = $batchName;

        $this->coachId = ((int) \App\Models\Course::where('id', $liveClass->course_id ?: optional($liveClass->lesson)->course_id)
            ->value('instructor_id')) ?: null;

        $when = $liveClass->start_time ? formattedDateTime($liveClass->start_time) : '';

        $this->title = 'Live class cancelled';
        $this->body = '"' . \Str::limit($courseTitle, 50) . '"'
            . ($batchName ? ' (' . \Str::limit($batchName, 30) . ')' : '')
            . ($when ? ' scheduled for ' . $when : '')
            . ' has been cancelled.';
        $this->icon = 'fa-calendar-xmark';
        $this->iconColor = '#ef4444';

        try {
            $this->url = route('student.live-classes.index');
        } catch (\Throwable $e) {
            $this->url = null;
        }
    }
}
