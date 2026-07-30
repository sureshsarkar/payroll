<?php

namespace Modules\Currency\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MultiCurrency extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('allCurrencies');
            if (session()->has('currency_code')) {
                session()->forget('currency_code');
                session()->forget('currency_position');
                session()->forget('currency_icon');
                session()->forget('currency_rate');
            }
            Cache::forget('getSessionCurrency');
        });

        static::created(function () {
            Cache::forget('allCurrencies');
            if (session()->has('currency_code')) {
                session()->forget('currency_code');
                session()->forget('currency_position');
                session()->forget('currency_icon');
                session()->forget('currency_rate');
            }
            Cache::forget('getSessionCurrency');
        });

        static::updated(function () {
            Cache::forget('allCurrencies');
            if (session()->has('currency_code')) {
                session()->forget('currency_code');
                session()->forget('currency_position');
                session()->forget('currency_icon');
                session()->forget('currency_rate');
            }
            Cache::forget('getSessionCurrency');
        });

        static::deleted(function () {
            Cache::forget('allCurrencies');
            if (session()->has('currency_code')) {
                session()->forget('currency_code');
                session()->forget('currency_position');
                session()->forget('currency_icon');
                session()->forget('currency_rate');
            }
            Cache::forget('getSessionCurrency');
        });
    }
}
