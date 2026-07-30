<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail: every theme application (or re-application) by a coach.
 * The CURRENT theme per coach is the most-recent row where
 * superseded_at IS NULL.
 */
class ThemeApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id', 'theme_id', 'version',
        'applied_at', 'superseded_at',
    ];

    protected $casts = [
        'applied_at'    => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function scopeActive($q)
    {
        return $q->whereNull('superseded_at');
    }
}
