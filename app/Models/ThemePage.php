<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Page definition within a theme — Home, About, Services etc.
 * Cloned to coach_pages when theme is applied.
 */
class ThemePage extends Model
{
    use HasFactory;

    protected $fillable = [
        'theme_id', 'slug', 'page_type', 'title',
        'meta_title', 'meta_description', 'sort_order', 'is_required',
    ];

    protected $casts = ['is_required' => 'boolean'];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ThemeSection::class)->orderBy('sort_order');
    }
}
