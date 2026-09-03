<?php

namespace Modules\Leave\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\app\Concerns\BelongsToCompany;

class Leave extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    public const PENDING   = 'pending';
    public const APPROVED  = 'approved';
    public const REJECTED  = 'rejected';
    public const CANCELLED = 'cancelled';

    public const HALF_FIRST  = 'first';
    public const HALF_SECOND = 'second';

    protected $fillable = [
        'user_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'half_session',
        'reason', 'status', 'approved_by', 'review_note', 'reviewed_at',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'days'        => 'decimal:1',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
