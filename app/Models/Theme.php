<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master theme record — owned by Super Admin, shown to coaches when
 * enabled. Cloned to a coach's tenant scope via ThemeApplicator.
 */
class Theme extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'thumbnail_url',
        'default_colors', 'default_fonts',
        'is_enabled', 'is_premium', 'version', 'sort_order',
        'author_user_id',
    ];

    protected $casts = [
        'default_colors' => 'array',
        'default_fonts'  => 'array',
        'is_enabled'     => 'boolean',
        'is_premium'     => 'boolean',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(ThemePage::class)->orderBy('sort_order');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ThemeCategory::class, 'theme_category_pivot', 'theme_id', 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ThemeApplication::class);
    }

    public function scopeEnabled($q)
    {
        return $q->where('is_enabled', true);
    }

    /** How many coaches are CURRENTLY using this theme (latest application) */
    public function activeUsageCount(): int
    {
        return ThemeApplication::where('theme_id', $this->id)
            ->whereNull('superseded_at')
            ->count();
    }
}
