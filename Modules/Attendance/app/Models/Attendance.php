<?php

namespace Modules\Attendance\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** Canonical status values. */
    public const PRESENT  = 'Present';
    public const ABSENT   = 'Absent';
    public const HALF_DAY = 'HalfDay';
    public const LEAVE    = 'Leave';
    public const HOLIDAY  = 'Holiday';
    public const WFH      = 'WFH';

    public const STATUSES = [
        self::PRESENT, self::ABSENT, self::HALF_DAY,
        self::LEAVE, self::HOLIDAY, self::WFH,
    ];

    /** Statuses that count as a full paid working day. */
    public const PAID_FULL = [self::PRESENT, self::HOLIDAY, self::WFH];

    protected $fillable = [
        'user_id', 'attendance_date', 'status', 'check_in', 'check_out',
        'worked_minutes', 'source', 'marked_by', 'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /** Loss-of-pay weight for a status: 1 = full LOP, 0.5 = half, 0 = paid. */
    public function lopWeight(): float
    {
        return match ($this->status) {
            self::ABSENT   => 1.0,
            self::HALF_DAY => 0.5,
            default        => 0.0,
        };
    }
}
