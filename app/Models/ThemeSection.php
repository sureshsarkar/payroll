<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Section definition within a theme page — typed content block with
 * tokenized default content (e.g. {{brand_name}}). Cloned to
 * landing_sections rows when theme is applied to a coach.
 */
class ThemeSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'theme_page_id', 'section_type', 'section_version',
        'content_json', 'sort_order', 'is_required',
    ];

    protected $casts = [
        'content_json' => 'array',
        'is_required'  => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(ThemePage::class, 'theme_page_id');
    }
}
