<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryRevision extends Model
{
    protected $fillable = [
        'user_id', 'old_ctc', 'new_ctc', 'effective_from', 'reason', 'created_by',
    ];

    protected $casts = [
        'old_ctc'        => 'decimal:2',
        'new_ctc'        => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
