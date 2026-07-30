<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only note on a LandingPageEnquiry.
 *
 * One row = one note. Notes are never edited from the UI — the
 * timestamp is the truth-of-record. If a coach needs to correct a
 * note, they post a new one referencing the prior one. Hard delete is
 * intentionally not exposed in the controller; if anyone needs to
 * remove a note (e.g. PII leaked into the body), it has to go through
 * a DB admin so there's a paper trail.
 */
class LeadNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'enquiry_id',
        'author_id',
        'body',
    ];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(LandingPageEnquiry::class, 'enquiry_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withDefault();
    }
}
