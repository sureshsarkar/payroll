<?php

namespace Modules\PaymentWithdraw\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\PaymentWithdraw\Database\factories\WithdrawRequestFactory;

class WithdrawRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'withdraw_amount',
        'method',
        'account_info',
        'status',
        'current_amount'
    ];

    /**
     * `account_info` holds bank/UPI/payout details. Stored encrypted at rest;
     * Eloquent transparently encrypts on save and decrypts on read.
     *
     * NOTE: when adding to a table with existing plaintext rows, run a one-off
     * migration first that wraps each existing row's value with Crypt::encryptString.
     * This table currently has 0 rows so no backfill is needed.
     */
    protected $casts = [
        'account_info' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->select('id', 'name', 'email', 'image');
    }
}
