<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A per-coach trainer profile (2026-07-15). Tenant-safe: every query goes
 * through scopeForCoach; a coach only ever manages their own trainers. The
 * public Trainer Detail Page is keyed by (coach, slug).
 */
class CoachTrainer extends Model
{
    protected $table = 'coach_trainers';

    protected $fillable = [
        'coach_id', 'website_id', 'name', 'slug', 'photo', 'specialisation',
        'experience', 'bio', 'tags', 'is_active', 'sort_order',
        // 2026-07-15 (Phase 5) — COACH DETAIL block + booking-form taxonomy.
        'certificate_date', 'certificate_number', 'plan_types', 'course_types', 'reasons',
    ];

    protected $casts = [
        'tags'         => 'array',
        'plan_types'   => 'array',
        'course_types' => 'array',
        'reasons'      => 'array',
        'is_active'    => 'boolean',
    ];

    /** Sensible white-label defaults so the booking form works before a coach edits it. */
    public const DEFAULT_PLAN_TYPES   = ['Online', 'Offline'];
    public const DEFAULT_COURSE_TYPES = ['Individual Plan', 'Couple Plan'];
    public const DEFAULT_REASONS      = ['Fitness', 'Weight Loss', 'Flexibility', 'Problem'];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TrainerSessionPackage::class, 'trainer_id');
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

    /** Active packages for the public page, in display order. */
    public function activePackages()
    {
        return $this->packages()->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /* ── booking-form taxonomy (coach-editable, defaulted) ── */

    /** @return array<int,string> */
    public function planTypeOptions(): array
    {
        return $this->cleanList($this->plan_types) ?: self::DEFAULT_PLAN_TYPES;
    }

    /** @return array<int,string> */
    public function courseTypeOptions(): array
    {
        return $this->cleanList($this->course_types) ?: self::DEFAULT_COURSE_TYPES;
    }

    /** @return array<int,string> */
    public function reasonOptions(): array
    {
        return $this->cleanList($this->reasons) ?: self::DEFAULT_REASONS;
    }

    private function cleanList($raw): array
    {
        return is_array($raw) ? array_values(array_filter(array_map('trim', $raw))) : [];
    }

    /**
     * A unique slug within the coach's namespace. Appends -2, -3… on collision.
     * Ignores $ignoreId so editing a trainer keeps its own slug.
     */
    public static function uniqueSlug(int $coachId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'trainer';
        $slug = $base;
        $n = 2;
        while (static::forCoach($coachId)->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }
}
