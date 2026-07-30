<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-coach tax configuration (Phase 1, 2026-06-13).
 *
 * One row per coach. Tax is OFF (is_enabled = false) until the coach turns it
 * on, so the platform default for everyone is "no tax" — see [[TaxService]].
 */
class TaxProfile extends Model
{
    protected $fillable = [
        'coach_id', 'is_enabled', 'mode', 'legal_name',
        'registration_label', 'registration_number', 'country', 'state', 'invoice_note',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'coach_id', 'coach_id');
    }

    /** A compliance string like "GSTIN 22AAAAA0000A1Z5" (or '' when unset). */
    public function getRegistrationDisplayAttribute(): string
    {
        if (! $this->registration_number) {
            return '';
        }

        return trim(($this->registration_label ?: 'Tax ID') . ' ' . $this->registration_number);
    }
}
