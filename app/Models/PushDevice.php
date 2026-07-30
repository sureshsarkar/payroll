<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (user, device) push-notification target.
 *
 * `token` is the FCM registration token (Android) or the APNs device
 * token bridged via Firebase (iOS). The token is rotated by the OS in
 * normal circumstances; the mobile app re-registers on every cold start,
 * and the controller upserts on the unique-by-token index — so we
 * naturally dedupe and the row tracks `last_seen_at` for cleanup.
 */
class PushDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
