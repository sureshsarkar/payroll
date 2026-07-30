<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trainer session-package (2026-07-15) — "N sessions · validity · price".
 * Scoped to a trainer AND its coach (coach_id for a cheap tenant gate). Price
 * and validity are server-authoritative; the booking flow re-reads them here.
 */
class TrainerSessionPackage extends Model
{
    protected $table = 'trainer_session_packages';

    protected $fillable = [
        'trainer_id', 'coach_id', 'name', 'sessions', 'validity_value',
        'validity_unit', 'price', 'currency', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'sessions'       => 'integer',
        'validity_value' => 'integer',
        'price'          => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(CoachTrainer::class, 'trainer_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /** THE tenant gate. */
    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** "5 Sessions · 15 Days · ₹7,000" — the internal / coach-panel option label. */
    public function label(): string
    {
        $sym = ($this->currency ?: 'INR') === 'INR' ? '₹' : (($this->currency ?: 'INR') . ' ');
        $unit = ucfirst((string) $this->validity_unit);
        return trim($this->name)
            ? $this->name . ' · ' . $sym . number_format((float) $this->price, 0)
            : $this->sessions . ' Session' . ($this->sessions == 1 ? '' : 's')
                . ' · ' . $this->validity_value . ' ' . $unit
                . ' · ' . $sym . number_format((float) $this->price, 0);
    }

    /**
     * "5 Session Validity 10 days – ₹6000" — the public detail-page + booking-form
     * format (matches the coach's design doc). No thousands separator, lower-case
     * unit, en-dash before the price.
     */
    public function sessionLabel(): string
    {
        $sym = ($this->currency ?: 'INR') === 'INR' ? '₹' : (($this->currency ?: 'INR') . ' ');
        return $this->sessions . ' Session Validity ' . $this->validity_value . ' '
            . strtolower((string) $this->validity_unit) . ' – ' . $sym . number_format((float) $this->price, 0);
    }
}
