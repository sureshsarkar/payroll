<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Audit 2026-05-18 — explicit cast for `sent_at` so views can call
     * ->format() without manual Carbon::parse(). Status remains a plain
     * string ('active'|'inactive') — no enum cast needed.
     */
    protected $casts = [
        'sent_at'      => 'datetime',
        'scheduled_at' => 'datetime',  // Audit 2026-05-18 phase 3
        'delivered_at' => 'datetime',
        'is_pinned'    => 'boolean',
    ];

    public function course() {
       return $this->belongsTo(Course::class);
    }

    public function instructor(): BelongsTo {
        return $this->belongsTo(User::class, 'instructor_id', 'id');
    }

    /**
     * Audit 2026-05-18 — primary batch (denormalized).
     * NULL → course-wide. For multi-batch, this equals the FIRST
     * selected batch; the full list lives on batches().
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'batch_id', 'id');
    }

    /**
     * Audit 2026-05-18 — full set of target batches via the
     * announcement_batches pivot. Used for multi-batch fanout +
     * visibility scope. For single-batch and course-wide rows the
     * relation has 1 and 0 rows respectively.
     */
    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(
            CourseBatch::class,
            'announcement_batches',
            'announcement_id',
            'batch_id'
        )->withTimestamps();
    }

    /**
     * Audit 2026-05-18 phase 3 — file attachments.
     */
    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AnnouncementAttachment::class);
    }

    /**
     * Audit 2026-05-18 phase 3 — student users who have opened this
     * announcement. Pivot keyed by announcement_reads.
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'announcement_reads',
            'announcement_id',
            'user_id'
        )->withPivot('read_at')->withTimestamps();
    }

    /**
     * Mark this announcement as read by the given user id.
     * Idempotent — UNIQUE(announcement_id, user_id) is enforced
     * by the migration. Returns true if a new row was inserted.
     */
    public function markReadBy(int $userId): bool
    {
        try {
            \DB::table('announcement_reads')->insert([
                'announcement_id' => $this->id,
                'user_id'         => $userId,
                'read_at'         => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate (already read) — silently ignore.
            return false;
        }
    }

    // ---------- Scopes ----------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * Visible to a student who is in $batchId of $courseId.
     *
     * Visibility rule (after 2026-05-18-16:00 pivot migration):
     *   - status = 'active' AND
     *   - course_id = $courseId AND
     *   - (
     *        announcement has NO batch targets at all (course-wide), OR
     *        $batchId IS in announcement_batches pivot
     *     )
     *
     * Legacy rows that only set announcements.batch_id are auto-handled
     * because the pivot migration backfilled them.
     */
    public function scopeVisibleToBatchStudent($query, int $courseId, ?int $batchId)
    {
        return $query->where('course_id', $courseId)
            ->where('status', 'active')
            ->where(function ($q) use ($batchId) {
                // (a) course-wide: no pivot rows AND no legacy batch_id
                $q->where(function ($noTarget) {
                    $noTarget->whereNull('batch_id')
                        ->whereDoesntHave('batches');
                });

                if ($batchId !== null) {
                    // (b) batch in pivot list
                    $q->orWhereHas('batches', fn ($b) => $b->where('course_batches.id', $batchId));
                    // (c) legacy single batch_id match (defensive)
                    $q->orWhere('batch_id', $batchId);
                }
            });
    }

    /**
     * Audit 2026-05-20 — generalised visibility scope.
     *
     * The previous `scopeVisibleToBatchStudent` required (course_id,
     * batch_id) parameters and could only ever return a course-scoped
     * subset. With the audience_type column added, students see
     * announcements via THREE paths:
     *
     *   1. audience_type='all_students'        → reaches every student
     *   2. audience_type='batch_specific' AND
     *      (a) the student is enrolled in any batch in the pivot list, OR
     *      (b) legacy single batch_id matches a batch the student is in
     *   3. (legacy) course_id matches a course the student is enrolled in
     *      AND there are no batch targets at all (course-wide
     *      announcement under the old model)
     *
     * status must be 'active' (= published) for all three paths.
     * Draft (status='inactive') rows are hidden from students.
     */
    public function scopeVisibleToStudent($query, \App\Models\User $student)
    {
        // Resolve student's enrolled course + batch ids ONCE so the
        // sub-clauses don't each hit the DB.
        // 2026-06-02 — resolve the student's course/batch membership WITHOUT a
        // has_access=1 filter. A fee-pending member (has_access=0) is still a
        // batch member and must see that batch's announcements. has_access
        // gates course CONTENT, not announcement visibility (same distinction
        // as the fee-visibility fix).
        $enrollments = \Modules\Order\app\Models\Enrollment::query()
            ->where('user_id', $student->id)
            ->get(['course_id', 'batch_id']);

        $courseIds = $enrollments->pluck('course_id')->filter()->unique()->values()->all();
        $batchIds  = $enrollments->pluck('batch_id')->filter()->unique()->values()->all();
        // 2026-06-03 — courses the student is enrolled in with NO batch assigned.
        // Such a "batchless" member must still see the course's batch-specific
        // announcements (parity with AnnouncementNotifier::fanOut); otherwise the
        // widespread NULL batch_id hides almost every announcement from them.
        $noBatchCourseIds = $enrollments->whereNull('batch_id')
            ->pluck('course_id')->filter()->unique()->values()->all();

        return $query
            ->where('status', 'active')
            ->where(function ($q) use ($courseIds, $batchIds, $noBatchCourseIds) {
                // PATH 1 — Platform-wide announcement.
                $q->where('audience_type', 'all_students');

                // PATH 2 — Batch-specific announcement, student is in
                // one of the target batches.
                if (!empty($batchIds)) {
                    $q->orWhere(function ($batch) use ($batchIds) {
                        $batch->where('audience_type', 'batch_specific')
                              ->where(function ($inner) use ($batchIds) {
                                  // (a) batch in the multi-batch pivot
                                  $inner->whereHas('batches', function ($b) use ($batchIds) {
                                      $b->whereIn('course_batches.id', $batchIds);
                                  });
                                  // (b) legacy single batch_id match
                                  $inner->orWhereIn('batch_id', $batchIds);
                              });
                    });
                }

                // PATH 2b — Batchless course member: sees EVERY batch-specific
                // announcement for a course they're enrolled in with no batch.
                if (!empty($noBatchCourseIds)) {
                    $q->orWhere(function ($cw) use ($noBatchCourseIds) {
                        $cw->where('audience_type', 'batch_specific')
                           ->whereIn('course_id', $noBatchCourseIds);
                    });
                }

                // PATH 3 — Legacy course-wide announcement (no batch
                // target at all, but tied to a course the student is in).
                if (!empty($courseIds)) {
                    $q->orWhere(function ($courseWide) use ($courseIds) {
                        $courseWide->where('audience_type', 'batch_specific')
                                   ->whereIn('course_id', $courseIds)
                                   ->whereNull('batch_id')
                                   ->whereDoesntHave('batches');
                    });
                }
            });
    }

    /**
     * Convenience: is the row sent to ALL students?
     */
    public function isAllStudents(): bool
    {
        return $this->audience_type === 'all_students';
    }

    /**
     * Convenience: is the row sent to specific batch(es)?
     */
    public function isBatchSpecific(): bool
    {
        return $this->audience_type === 'batch_specific';
    }
}
