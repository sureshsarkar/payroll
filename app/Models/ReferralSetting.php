<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ReferralSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'enabled'                         => 'boolean',
        'reward_when_referred_is_student' => 'decimal:2',
        'reward_when_referred_is_coach'   => 'decimal:2',
        'min_membership_amount'           => 'decimal:2',
        'block_self_referral'             => 'boolean',
        'block_same_ip_referral'          => 'boolean',
        'require_admin_approval'          => 'boolean',
    ];

    /**
     * Cached fetch of the singleton settings row. Cache invalidated on save.
     */
    public static function current(): self
    {
        return Cache::remember('referral_settings', 3600, function () {
            return self::firstOrCreate([], [
                'enabled' => true,
                'reward_when_referred_is_student' => 5.00,
                'reward_when_referred_is_coach'   => 10.00,
            ]);
        });
    }

    protected static function booted()
    {
        static::saved(fn () => Cache::forget('referral_settings'));
    }
}
