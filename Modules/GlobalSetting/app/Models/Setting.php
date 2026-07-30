<?php

namespace Modules\GlobalSetting\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['key', 'value'];

    /**
     * Audit 2026-05-18 phase 5 — invalidate the `setting` cache whenever
     * any row in this table changes. Without this hook, admin edits take
     * effect only after a manual cache:clear; ServiceProviders using
     * Cache::rememberForever('setting', ...) stay stale otherwise.
     */
    public static function boot(): void
    {
        parent::boot();

        $invalidate = fn () => Cache::forget('setting');
        static::saved($invalidate);
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }
}
