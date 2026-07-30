<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (user, live class, session). See
 * 2026_05_07_230000_create_live_class_attendances_table for the data model
 * decisions (rows-per-session over rows-per-user, why duration_seconds is
 * pre-computed, etc.).
 */
class LiveClassAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_live_class_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'duration_seconds',
        'client_ip',
        'user_agent',
        'is_manual',
        'manual_reason',
        'marked_by',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at'   => 'datetime',
        'is_manual' => 'boolean',
    ];

    public function liveClass(): BelongsTo
    {
        return $this->belongsTo(CourseLiveClass::class, 'course_live_class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
