<?php

namespace Modules\Order\app\Models;

use App\Models\Course;
use App\Models\CourseBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Database\factories\EnrollmentFactory;

class Enrollment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'user_id',
        'course_id',
        'batch_id',     // Audit 2026-05-18 — added by migration of same date
        'has_access',
    ];

    function course() : BelongsTo{
       return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    /**
     * Audit 2026-05-18 — direct link to the batch the student enrolled in.
     * Nullable: legacy enrollments before the migration may have no batch.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'batch_id', 'id');
    }

}
