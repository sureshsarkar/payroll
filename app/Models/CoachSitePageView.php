<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * First-party pageview event. One row per non-bot public-page request.
 * Append-only; queries aggregate by (coach_id, page_id, day).
 */
class CoachSitePageView extends Model
{
    use HasFactory;

    public $timestamps = false;          // only created_at, no updated_at

    protected $fillable = [
        'coach_id',
        'page_id',
        'visitor_hash',
        'referer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
