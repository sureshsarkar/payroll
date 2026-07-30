<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only snapshot of a CoachPage + its sections at a point in time.
 * Used for the "undo last 20 saves" UX. Newest-first via the page's
 * versions() relation.
 */
class CoachPageVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_page_id',
        'snapshot',
        'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CoachPage::class, 'coach_page_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
