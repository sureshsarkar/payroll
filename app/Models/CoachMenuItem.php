<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCoach;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single navigation menu item (2026-06-17). parent_id gives one level of
 * dropdowns. link_type decides how resolveUrl() builds the href:
 *   page    → an internal coach page (page_id)
 *   section → a coach page + #anchor (page_id + section_anchor); page_id null = current page
 *   url     → an external/custom URL (url)
 *   route   → a named application route (route_name)
 *   home    → the coach's site home
 */
class CoachMenuItem extends Model
{
    use BelongsToCoach;

    protected $table = 'coach_menu_items';

    protected $fillable = [
        'coach_id',
        'menu_id',
        'parent_id',
        'label',
        'link_type',
        'page_id',
        'url',
        'section_anchor',
        'route_name',
        'target',
        'sort_order',
        'is_visible',
        'layout',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    // 'none' = a non-clickable label (used for mega-menu column headings / dividers).
    public const LINK_TYPES = ['page', 'url', 'section', 'route', 'home', 'none'];

    // Top-level layout: a simple dropdown or a wide multi-column mega panel.
    public const LAYOUTS = ['dropdown', 'mega'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(CoachMenu::class, 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CoachPage::class, 'page_id');
    }

    /**
     * Resolve this item's href.
     *
     * @param  array  $pageMap   page_id => CoachPage (slug, page_type), the coach's pages
     * @param  ?CoachLandingPage  $site  the coach site root (for path-mode slug)
     * @param  bool   $isPathMode  true on /coach/{slug}/… surface (XAMPP / no-DNS)
     */
    public function resolveUrl(array $pageMap, ?CoachLandingPage $site, bool $isPathMode): string
    {
        $home = $isPathMode && $site
            ? url('/coach/' . $site->slug)
            : url('/');

        $pageUrl = function (?CoachPage $p) use ($home, $isPathMode, $site): string {
            if (! $p) {
                return $home;
            }
            if ($p->page_type === 'home') {
                return $home;
            }
            return $isPathMode && $site
                ? url('/coach/' . $site->slug . '/' . $p->slug)
                : url('/' . $p->slug);
        };

        switch ($this->link_type) {
            case 'none':
                return ''; // non-clickable (mega column heading / divider)

            case 'home':
                return $home;

            case 'url':
                return (string) ($this->url ?: '#');

            case 'route':
                if ($this->route_name) {
                    try {
                        return route($this->route_name);
                    } catch (\Throwable $e) {
                        return '#';
                    }
                }
                return '#';

            case 'section':
                $anchor = ltrim((string) $this->section_anchor, '#');
                $base   = $this->page_id ? $pageUrl($pageMap[$this->page_id] ?? null) : '';
                return $anchor !== '' ? ($base . '#' . $anchor) : ($base ?: '#');

            case 'page':
            default:
                return $pageUrl($pageMap[$this->page_id] ?? null);
        }
    }
}
