<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCoach;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A coach's navigation menu (2026-06-17). One row per coach per location
 * (default 'primary'). Owns coach_menu_items; the public renderer builds the
 * site nav from the active menu's items, falling back to page-derived nav when
 * a coach has not configured one.
 */
class CoachMenu extends Model
{
    use BelongsToCoach;

    protected $table = 'coach_menus';

    protected $fillable = [
        'coach_id',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CoachMenuItem::class, 'menu_id')->orderBy('sort_order');
    }

    /** Top-level items only (parent_id IS NULL), each with its children. */
    public function topItems(): HasMany
    {
        return $this->hasMany(CoachMenuItem::class, 'menu_id')
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    /** Fetch (or build an unsaved default) the primary menu for a coach. */
    public static function primaryForCoach(int $coachId): self
    {
        return static::firstOrNew(
            ['coach_id' => $coachId, 'location' => 'primary'],
            ['is_active' => true]
        );
    }
}
