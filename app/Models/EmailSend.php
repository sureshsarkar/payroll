<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per outbound email triggered from a lead's detail page.
 *
 * Snapshots the rendered subject + body at send-time so a coach
 * looking back later sees exactly what was sent, not whatever the
 * template later evolved into.
 */
class EmailSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'enquiry_id',
        'coach_id',     // 2026-07-10 (#8.2) — tenant of the lead, for the audit log
        'sender_id',
        'to_email',
        'subject',
        'body',
        'smtp_source',  // 2026-07-10 (#8.2) — 'coach' (coach SMTP) | 'mbsguru' (default)
        'status',
        'error',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withDefault();
    }
}
