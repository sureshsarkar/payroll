<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail for a coach custom/subdomain lifecycle.
 *
 * Every meaningful state change — added, verify_attempt, verified,
 * failed, approved, rejected, suspended, resumed, ssl_issued,
 * ssl_failed, removed, renamed — writes one row here. Powers the
 * superadmin timeline + compliance. Never hard-deleted with the
 * domain (the row keeps the hostname + coach_id snapshot).
 */
class CoachDomainEvent extends Model
{
    protected $fillable = [
        'coach_domain_id',
        'coach_id',
        'hostname',
        'event',
        'actor_type',
        'actor_id',
        'ip',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(CoachDomain::class, 'coach_domain_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
