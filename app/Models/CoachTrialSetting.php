<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-coach configuration for the "Book Your Trial Session" popup.
 * Exactly one row per coach (unique coach_id). All display copy and pricing is
 * coach-editable; the price here is the ONLY source of truth for the charge.
 */
class CoachTrialSetting extends Model
{
    protected $fillable = [
        'coach_id', 'is_enabled', 'title', 'subtitle', 'success_message',
        'price', 'currency', 'currency_icon', 'require_payment', 'payment_gateway',
        'auto_show', 'show_delay_seconds', 'show_frequency',
    ];

    protected $casts = [
        'is_enabled'         => 'boolean',
        'require_payment'    => 'boolean',
        'auto_show'          => 'boolean',
        'price'              => 'decimal:2',
        'show_delay_seconds' => 'integer',
    ];

    public const DEFAULT_TITLE    = 'Book Your Trial Session';
    public const DEFAULT_SUBTITLE = '1 Day Trial at just ₹51';

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /** Resolve (or lazily default) the settings row for a coach. */
    public static function forCoach(int $coachId): self
    {
        return static::firstOrNew(['coach_id' => $coachId]);
    }

    public function displayTitle(): string
    {
        return $this->title ?: self::DEFAULT_TITLE;
    }

    public function displaySubtitle(): string
    {
        return $this->subtitle ?: self::DEFAULT_SUBTITLE;
    }

    /** True when a payment must be collected before the trial is confirmed. */
    public function chargesMoney(): bool
    {
        return $this->require_payment && (float) $this->price > 0;
    }
}
