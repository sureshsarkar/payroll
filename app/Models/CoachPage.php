<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One page in a coach's branded marketing website.
 *
 * A "Site" = the collection of all pages under a single coach's domain
 * (subdomain or verified custom). The Site root record is still
 * coach_landing_pages.{id}  — kept for back-compat and to hold the
 * subdomain + slug. Each CoachPage row is one URL under that Site
 * (e.g. /, /about, /services, /contact, /retreats).
 *
 * Sections (typed JSON content blocks) live in landing_sections and FK
 * via coach_page_id.
 */
class CoachPage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'coach_id',
        'site_id',
        'slug',
        'page_type',
        'title',
        'meta_title',
        'meta_description',
        'og_image',
        'robots',
        'is_published',
        'sort_order',
        // Nav controls (audit 2026-05-25 evening)
        'is_visible_in_nav',
        'nav_label',
        'nav_external_url',
        // Global footer per-page opt-out (2026-06-17)
        'use_global_footer',
    ];

    protected $casts = [
        'is_published'      => 'boolean',
        'is_visible_in_nav' => 'boolean',
        'use_global_footer' => 'boolean',
    ];

    /**
     * Allowed page archetypes. The 'home' archetype is special — a coach
     * must have exactly one published Home page before any other page
     * can be published.
     */
    public const TYPES = [
        'home', 'about', 'services', 'pricing',
        'testimonials', 'contact', 'blog_index', 'custom',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(CoachLandingPage::class, 'site_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CoachPageSection::class, 'coach_page_id')
            ->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CoachPageVersion::class, 'coach_page_id')
            ->orderByDesc('id');
    }

    public function scopePublished($q)
    {
        return $q->where('is_published', true);
    }

    public function scopeForCoach($q, int $coachId)
    {
        return $q->where('coach_id', $coachId);
    }

    /**
     * Generate a unique slug within this coach's site. Used by create/rename.
     */
    public static function generateUniqueSlug(int $coachId, string $base): string
    {
        $slug = \Illuminate\Support\Str::slug($base) ?: 'page';
        $original = $slug;
        $i = 1;
        while (self::where('coach_id', $coachId)->where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }
        return $slug;
    }
}
