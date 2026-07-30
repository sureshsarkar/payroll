<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A typed content block within a CoachPage. Maps to the `landing_sections`
 * table (legacy name kept; coach_page_id is the new FK, landing_page_id
 * is the legacy fallback that stays nullable for back-compat).
 *
 * section_type drives:
 *   - schema validation (via SectionRegistry)
 *   - the Blade partial that renders it (resources/views/frontend/coach-site/sections/{type}.blade.php)
 *   - the editor form (resources/views/frontend/coach-site/forms/{type}.blade.php)
 *
 * section_version lets us ship _v2 without breaking _v1 sites.
 */
class CoachPageSection extends Model
{
    use HasFactory;

    protected $table = 'landing_sections';

    protected $fillable = [
        'coach_page_id',
        'landing_page_id',
        'section_type',
        'section_version',
        'content_json',
        'sort_order',
        'is_visible',
        'theme_section_origin_id',   // Theme phase 1 (audit 2026-05-25)
    ];

    protected $casts = [
        'content_json' => 'array',
        'is_visible'   => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CoachPage::class, 'coach_page_id');
    }
}
