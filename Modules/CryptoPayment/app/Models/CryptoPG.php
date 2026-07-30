<?php

namespace Modules\CryptoPayment\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class CryptoPG extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['key', 'value'];
    protected $table = 'crypto_p_g';

    /**
     * Audit 2026-05-18 phase 5 — mirror of the BkashPGModel pattern.
     * Without these hooks the `cryptoConfig` rememberForever cache stays
     * stale after admin edits, producing "Undefined property: $crypto_*"
     * spam in the log when new keys are added to the row set.
     */
    public static function boot(): void
    {
        parent::boot();

        $invalidate = fn () => Cache::forget('cryptoConfig');
        static::saved($invalidate);
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }
}
