<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A date-specific temporary batch slot (2026-07-15). The student's primary batch
 * (enrollments.batch_id) is untouched; this only affects the single slot_date.
 * Tenant-scoped by coach_id. See [[TemporarySlotService]].
 */
class StudentTemporarySlot extends Model
{
    protected $table = 'student_temporary_slots';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'coach_id', 'student_id', 'course_id', 'primary_batch_id', 'target_batch_id',
        'slot_date', 'reason', 'status', 'created_by',
    ];

    protected $casts = [
        'slot_date' => 'date',
    ];

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_SCHEDULED);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function primaryBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'primary_batch_id');
    }

    public function targetBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'target_batch_id');
    }
}
