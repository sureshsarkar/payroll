<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Taxonomy tag for themes — yoga / business / fitness / mental-health
 * / generic. Drives the category filter in the coach onboarding gallery.
 */
class ThemeCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon', 'sort_order'];

    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class, 'theme_category_pivot', 'category_id', 'theme_id');
    }
}
