<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ZoomCredential extends Model
{
    use HasFactory;
    protected $fillable = [
        'instructor_id', 'account_id', 'client_id', 'client_secret',
        'sdk_key', 'sdk_secret',
        'zoom_access_token', 'zoom_token_expires_at',
        'health_status', 'health_message', 'last_health_check_at',
    ];

    /**
     * Sensitive fields encrypted at rest. Eloquent transparently encrypts on
     * save and decrypts on read. As of the 2026-05-08 S2S OAuth migration we
     * cache the access_token in DB to avoid hitting Zoom's token endpoint on
     * every API call (token TTL is ~1h); refresh tokens are gone because
     * Server-to-Server OAuth doesn't use them.
     *
     * `client_id` (a public-style SDK key) is NOT encrypted — it's safe to
     * expose in client traffic. `account_id` IS encrypted: while not strictly
     * a secret on its own, leaking it together with client_id/secret reduces
     * the work an attacker needs to forge tokens.
     */
    protected $casts = [
        'account_id'            => 'encrypted',
        'client_secret'         => 'encrypted',
        'sdk_secret'            => 'encrypted',
        'zoom_access_token'     => 'encrypted',
        'zoom_token_expires_at' => 'datetime',
        'last_health_check_at'  => 'datetime',
    ];

    /**
     * Defense in depth: even if a controller forgets and ships a model
     * instance through ->toArray() / ->toJson(), these fields are stripped.
     */
    protected $hidden = [
        'account_id',
        'client_secret',
        'sdk_secret',
        'zoom_access_token',
    ];

    function instructor(): BelongsTo {
        return $this->belongsTo(User::class, 'instructor_id', 'id')->withDefault();
    }
}
