<?php

namespace Modules\BasicPayment\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PaymentGateway extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    /**
     * Audit 2026-05-18 phase 5 — invalidate the `payment_setting` cache
     * whenever any row in this table changes. Without this, the
     * Cache::rememberForever('payment_setting', ...) in helper.php stays
     * stale across admin edits — same pattern that already exists on
     * BkashPGModel.
     */
    public static function boot(): void
    {
        parent::boot();

        $invalidate = fn () => Cache::forget('payment_setting');
        static::saved($invalidate);
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }
}
