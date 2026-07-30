<?php

namespace Modules\BkashPG\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BkashPGModel extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['key', 'value'];

    public static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('bkashConfig');
        });

        static::created(function () {
            Cache::forget('bkashConfig');
        });

        static::updated(function () {
            Cache::forget('bkashConfig');
        });

        static::deleted(function () {
            Cache::forget('bkashConfig');
        });
    }

}
