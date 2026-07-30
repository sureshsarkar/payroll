<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per coach while a live meeting is running. UNIQUE(coach_id) enforces
 * the one-active-meeting rule at the database level. Managed by LiveMeetingGuard.
 */
class CoachActiveMeeting extends Model
{
    protected $fillable = [
        'coach_id', 'course_live_class_id', 'instant_meeting_id', 'started_at', 'expires_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
