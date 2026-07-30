<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCoach;
use Illuminate\Database\Eloquent\Model;

/**
 * The coach's single, page-independent GLOBAL FOOTER (2026-06-17).
 *
 * content_json uses the SAME shape as a footer_v1 section
 * (tagline / show_social / copyright / link_groups), so it renders through the
 * existing footer_v1.blade.php with no template duplication. One row per coach
 * (enforced by a unique index). is_enabled toggles the whole global footer; a
 * page may still opt out individually via coach_pages.use_global_footer.
 */
class CoachSiteFooter extends Model
{
    use BelongsToCoach;

    protected $table = 'coach_site_footers';

    protected $fillable = [
        'coach_id',
        'content_json',
        'is_enabled',
        'section_version',
    ];

    protected $casts = [
        'content_json' => 'array',
        'is_enabled'   => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Fetch (or lazily build an unsaved default) the global footer for a coach.
     * Always returns a model instance so callers can read content_json safely.
     */
    public static function forCoachOrNew(int $coachId): self
    {
        return static::firstOrNew(
            ['coach_id' => $coachId],
            ['is_enabled' => true, 'section_version' => 'v1', 'content_json' => self::defaultContent()]
        );
    }

    /** Default footer content (mirrors SectionRegistry footer_v1 defaults). */
    public static function defaultContent(): array
    {
        return [
            'tagline'     => '',
            'show_social' => true,
            'copyright'   => 'All rights reserved.',
            'link_groups' => [],
        ];
    }
}
