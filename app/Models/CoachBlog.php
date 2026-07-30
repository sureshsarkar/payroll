<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A blog post owned by a single coach (users.role = instructor). Separate from
 * the platform Modules\Blog\Blog — these render ONLY on the owning coach's
 * custom website and are managed from the coach panel. Tenant-safe: every
 * query is scoped by coach_id.
 */
class CoachBlog extends Model
{
    use HasFactory;

    protected $table = 'coach_blogs';

    protected $fillable = [
        'coach_id', 'slug', 'title', 'short_description', 'content',
        'image', 'seo_title', 'seo_description', 'status', 'published_at', 'views',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views'        => 'integer',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /** Only published posts (status published AND publish date reached/empty). */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED)
            ->where(function ($w) {
                $w->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /** Scope to a single coach (tenant isolation). */
    public function scopeForCoach(Builder $q, int $coachId): Builder
    {
        return $q->where('coach_id', $coachId);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->published_at === null || $this->published_at->lessThanOrEqualTo(now()));
    }

    /** Featured-image URL with a sensible fallback. */
    public function imageUrl(): string
    {
        return $this->image ? asset($this->image) : asset('frontend/img/blogs.jpg');
    }
}
