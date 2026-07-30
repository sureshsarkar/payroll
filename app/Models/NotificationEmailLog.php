<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per mail-channel notification delivery attempt. Written by the
 * LogNotificationEmail listener. coach_id is the brand the email was sent as
 * (null = platform), so a coach can be shown only their own email log.
 */
class NotificationEmailLog extends Model
{
    protected $fillable = [
        'coach_id', 'notifiable_type', 'notifiable_id', 'recipient_email',
        'notification_class', 'title', 'status', 'error',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /** Scope a query to a coach's own email log (tenant-safe). */
    public function scopeForCoach($query, int $coachId)
    {
        return $query->where('coach_id', $coachId);
    }
}
