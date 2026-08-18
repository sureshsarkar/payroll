<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\app\Concerns\BelongsToCompany;

class Bonus extends Model
{
    use BelongsToCompany;

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
