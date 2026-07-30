<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit event for LandingPageEnquiry mutations.
 *
 * Conventions:
 *   - event ∈ { 'status_changed', 'assigned', 'note_added',
 *               'email_sent', 'imported' }
 *   - from_value / to_value capture the BEFORE and AFTER for `event=status_changed`
 *     and `event=assigned`. Null on creation events.
 *   - meta is flexible JSON; e.g. for `email_sent` we store the subject.
 *
 * We deliberately do NOT use $timestamps = true on this table — there
 * is no updated_at because rows are immutable.
 */
class LeadAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'enquiry_id',
        'actor_id',
        'event',
        'from_value',
        'to_value',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withDefault();
    }
}
