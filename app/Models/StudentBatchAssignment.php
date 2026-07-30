<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history/audit row for a student batch assignment or reassignment
 * (2026-07-15). Tenant-scoped by coach_id. The live membership is on
 * enrollments.batch_id; this is the ledger (previous → new batch, reason, who,
 * when). See [[StudentBatchService]].
 */
class StudentBatchAssignment extends Model
{
    protected $table = 'student_batch_assignments';

    public const ACTION_ASSIGN   = 'assign';    // batch-less enrollment → set a batch
    public const ACTION_REASSIGN = 'reassign';  // move from one batch to another (reason required)
    public const ACTION_ADD      = 'add';       // add an extra batch, keep the existing

    protected $fillable = [
        'coach_id', 'student_id', 'course_id',
        'previous_batch_id', 'new_batch_id',
        'action', 'reason', 'changed_by', 'effective_at',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
    ];

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function previousBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'previous_batch_id');
    }

    public function newBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'new_batch_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
