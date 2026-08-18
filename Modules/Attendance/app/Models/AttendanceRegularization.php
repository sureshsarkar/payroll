<?php

namespace Modules\Attendance\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\app\Concerns\BelongsToCompany;

class AttendanceRegularization extends Model
{
    use BelongsToCompany;

    public const PENDING  = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'attendance_date', 'requested_status', 'requested_check_in',
        'requested_check_out', 'reason', 'status', 'reviewed_by', 'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'reviewed_at'     => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
