<?php

namespace Modules\Leave\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'user_id', 'leave_type_id', 'year', 'allotted', 'used',
    ];

    protected $casts = [
        'allotted' => 'decimal:1',
        'used'     => 'decimal:1',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function available(): float
    {
        return round((float) $this->allotted - (float) $this->used, 1);
    }
}
