<?php

namespace Modules\Payroll\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\app\Concerns\BelongsToCompany;

class LoanAdvance extends Model
{
    use BelongsToCompany;

    protected $table = 'loans_advances';

    public const ACTIVE = 'active';
    public const CLOSED = 'closed';

    protected $fillable = [
        'user_id', 'type', 'principal', 'monthly_recovery', 'recovered',
        'status', 'note', 'created_by',
    ];

    protected $casts = [
        'principal'        => 'decimal:2',
        'monthly_recovery' => 'decimal:2',
        'recovered'        => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Amount still to recover. */
    public function outstanding(): float
    {
        return max(0, (float) $this->principal - (float) $this->recovered);
    }

    /** This month's recovery, never exceeding the outstanding balance. */
    public function dueThisMonth(): float
    {
        return round(min((float) $this->monthly_recovery, $this->outstanding()), 2);
    }
}
