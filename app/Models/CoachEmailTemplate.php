<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A coach's tenant-specific override of a notification email template. When a
 * coach has a row for a given template key, the notification pipeline uses it
 * instead of the platform `email_templates` default. Tenant-safe: every query
 * is scoped by coach_id.
 */
class CoachEmailTemplate extends Model
{
    protected $table = 'coach_email_templates';

    protected $fillable = ['coach_id', 'name', 'subject', 'message'];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    /**
     * The coach's override for a template key, or null if they haven't set one
     * (caller then falls back to the platform default). Swallows DB errors so a
     * missing table (pre-migration) can never break email rendering.
     */
    public static function override(?int $coachId, string $name): ?self
    {
        if (! $coachId) {
            return null;
        }
        try {
            return static::where('coach_id', $coachId)->where('name', $name)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
