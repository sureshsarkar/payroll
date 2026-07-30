<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bonus extends Model
{
    protected $fillable = [
        'user_id', 'title', 'amount', 'year', 'month', 'is_applied', 'created_by',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'is_applied' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
