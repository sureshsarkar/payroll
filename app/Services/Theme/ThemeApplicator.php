<?php

namespace App\Services\Theme;

use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\CoachSiteSettings;
use App\Models\Theme;
use App\Models\ThemeApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clones a Theme (master) into a Coach's tenant scope. After applying,
 * the coach owns its OWN copy of every page + section — subsequent
 * coach edits do NOT mutate the theme.
 *
 * Algorithm:
 *  1. Snapshot the coach's current site (so we can revert)
 *  2. Wipe existing CoachPage rows (full reset) OR merge (smart mode)
 *  3. Walk every theme_page → clone to coach_pages
 *  4. Walk every theme_section → resolve tokens → clone to landing_sections
 *  5. Apply theme's default colours/fonts to coach_site_settings if blank
 *  6. Record a row in theme_applications
 *  7. Mark old applications as superseded
 *  8. Stamp users.onboarding_theme_chosen_at if first time
 *
 * The whole thing runs in a DB transaction — partial application
 * is never observed by the public renderer.
 */
class ThemeApplicator
{
    public function __construct(
        protected readonly ThemeTokenResolver $tokens,
    ) {
    }

    /**
     * @param  string $mode  'replace' (wipe coach's pages first) | 'append' (add theme pages
     *                       alongside existing ones — Phase 2 use case)
     * @return array{ok:bool, pages_created:int, sections_created:int, application_id:int}
     */
    public function applyToCoach(Theme $theme, User $coach, string $mode = 'replace'): array
    {
        return DB::transaction(function () use ($theme, $coach, $mode) {

            // 1. Ensure the coach has a site root
            $site = CoachLandingPage::firstOrCreate(
                ['added_by' => $coach->id],
                [
                    'website_name' => $coach->name ?? 'My Coaching Site',
                    'slug'         => Str::slug($coach->name ?? ('coach-' . $coach->id)),
                    'subdomain'    => Str::slug($coach->name ?? ('coach-' . $coach->id)) . '.' . config('app.coach_domain'),
                    'is_published' => 0,
                    'title'        => $coach->name ?? 'My Coaching Site',
                ]
            );

            // 2. Snapshot existing pages (so coach can revert via theme_applications)
            //    Snapshot is captured by PageManager::snapshot per page — we leverage
            //    coach_page_versions which already exists.
            $existingPages = CoachPage::withTrashed()->forCoach($coach->id)->get();

            // 2026-06-16 — was the site LIVE before this switch? (any published,
            // non-trashed page). If so, a 'replace' must keep it live: otherwise
            // every new theme page was created as a draft (is_published=false)
            // and the whole public site 404'd until the coach re-published each
            // page by hand — the reported "theme change → 404 everywhere" bug.
            $wasLive = $existingPages->contains(fn ($p) => ! $p->trashed() && (bool) $p->is_published);

            if ($mode === 'replace') {
                // 2026-06-16 — DATA-LOSS FIX. The old code forceDelete()'d the
                // coach's pages AND hard-deleted their sections, permanently
                // destroying all custom text/images. Recovery was impossible:
                // the per-page version snapshots point at the now-deleted page
                // id, so restoreVersion()'s CoachPage::find() returns null.
                //
                // Instead we SOFT-delete each page (archiving its slug to free
                // the (coach_id, slug) unique key for the new theme) and LEAVE
                // its sections attached. The previous site is then fully
                // recoverable via ThemeApplicator::revertToPreviousSite().
                foreach ($existingPages as $p) {
                    if ($p->trashed()) {
                        continue; // already archived in an earlier switch
                    }
                    app(\App\Services\Site\PageManager::class)->snapshot($p, $coach->id);
                    $p->update([
                        'is_published' => false,
                        'slug'         => $this->archivedSlug($p->slug, $p->id),
                    ]);
                    $p->delete(); // soft-delete; sections stay attached for recovery
                }
            }

            // 3. Walk theme pages → clone to coach_pages
            $pageCount = 0;
            $sectionCount = 0;
            foreach ($theme->pages as $themePage) {
                $coachPage = CoachPage::create([
                    'coach_id'         => $coach->id,
                    'site_id'          => $site->id,
                    'slug'             => $themePage->slug,
                    'page_type'        => $themePage->page_type,
                    'title'            => $themePage->title,
                    'meta_title'       => $themePage->meta_title,
                    'meta_description' => $themePage->meta_description,
                    // Preserve the live state on a theme SWITCH (replace) so the
                    // public site doesn't 404. First-time/onboarding (no prior
                    // published site) stays draft so the coach reviews + publishes.
                    'is_published'     => ($mode === 'replace') ? $wasLive : false,
                    'is_visible_in_nav' => true,
                    'sort_order'       => $themePage->sort_order,
                ]);
                $pageCount++;

                // 4. Walk theme sections → token-resolve → clone
                foreach ($themePage->sections as $themeSection) {
                    $resolved = $this->tokens->resolve(
                        (array) ($themeSection->content_json ?? []),
                        $coach,
                        ['theme_name' => $theme->name]
                    );
                    CoachPageSection::create([
                        'coach_page_id'           => $coachPage->id,
                        'landing_page_id'         => null,
                        'section_type'            => $themeSection->section_type,
                        'section_version'         => $themeSection->section_version,
                        'content_json'            => $resolved,
                        'sort_order'              => $themeSection->sort_order,
                        'is_visible'              => true,
                        'theme_section_origin_id' => $themeSection->id,
                    ]);
                    $sectionCount++;
                }
            }

            // 5. Apply theme's default colors to CoachBrandSetting (only if blank).
            // CoachSiteSettings has no color columns — branding lives on CoachBrandSetting.
            CoachSiteSettings::firstOrCreate(['coach_id' => $coach->id]);
            $colors = $theme->default_colors ?? [];
            if (! empty($colors)) {
                try {
                    $brand = \App\Models\CoachBrandSetting::firstOrCreateForCoach($coach->id);
                    $dirty = false;
                    if (empty($brand->primary_color) && ! empty($colors['primary'])) {
                        $brand->primary_color = $colors['primary'];
                        $dirty = true;
                    }
                    if (empty($brand->accent_color) && ! empty($colors['accent'])) {
                        $brand->accent_color = $colors['accent'];
                        $dirty = true;
                    }
                    if ($dirty) $brand->save();
                } catch (\Throwable $e) {}
            }

            // 6. Update coach_landing_pages with theme reference
            $site->update([
                'theme_id'         => $theme->id,
                'theme_applied_at' => now(),
                'theme_version'    => $theme->version,
            ]);

            // 7. Mark previous applications superseded; create a new one
            ThemeApplication::where('coach_id', $coach->id)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);
            $application = ThemeApplication::create([
                'coach_id'   => $coach->id,
                'theme_id'   => $theme->id,
                'version'    => $theme->version,
                'applied_at' => now(),
            ]);

            // 8. First-time onboarding stamp
            if (empty($coach->onboarding_theme_chosen_at)) {
                $coach->update(['onboarding_theme_chosen_at' => now()]);
            }

            return [
                'ok'                => true,
                'pages_created'     => $pageCount,
                'sections_created'  => $sectionCount,
                'application_id'    => $application->id,
            ];
        });
    }

    /**
     * ENTERPRISE theme SWITCH (2026-06-16) — APPEARANCE ONLY, zero content change.
     *
     * Switches the coach's active theme by updating the theme REFERENCE and
     * applying the theme's PRESENTATION (colors) to coach_brand_settings. It
     * snapshots the outgoing theme's styling and restores any styling the coach
     * previously saved for the incoming theme (switch-back fidelity). It NEVER
     * touches coach_pages / landing_sections / SEO / menus / media / courses —
     * all core website data is preserved. Transaction-safe; tenant-isolated by
     * $coach->id. This REPLACES the destructive 'replace' default for switches.
     */
    public function restyleForCoach(Theme $theme, User $coach): array
    {
        return DB::transaction(function () use ($theme, $coach) {
            $site = CoachLandingPage::firstOrCreate(
                ['added_by' => $coach->id],
                [
                    'website_name' => $coach->name ?? 'My Coaching Site',
                    'slug'         => Str::slug($coach->name ?? ('coach-' . $coach->id)),
                    'subdomain'    => Str::slug($coach->name ?? ('coach-' . $coach->id)) . '.' . config('app.coach_domain'),
                    'is_published' => 0,
                    'title'        => $coach->name ?? 'My Coaching Site',
                ]
            );
            $previousThemeId = $site->theme_id;

            CoachSiteSettings::firstOrCreate(['coach_id' => $coach->id]);
            $brand = \App\Models\CoachBrandSetting::firstOrCreateForCoach($coach->id);

            // 1. Snapshot the OUTGOING theme's presentation → restorable later.
            if ($previousThemeId && (int) $previousThemeId !== (int) $theme->id) {
                $this->snapshotThemeStyling($coach->id, (int) $previousThemeId, $brand);
            }

            // 2. Active-theme REFERENCE — the only "theme state" mutation.
            $site->update([
                'theme_id'         => $theme->id,
                'theme_applied_at' => now(),
                'theme_version'    => $theme->version,
            ]);

            // 3. Presentation: restore this coach's saved styling for the new
            //    theme, else apply the theme defaults. CONTENT IS NOT TOUCHED.
            $this->applyThemeStyling($coach->id, $theme, $brand);

            // 4. Active/inactive audit trail (supersede old, record new active).
            ThemeApplication::where('coach_id', $coach->id)->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);
            $application = ThemeApplication::create([
                'coach_id' => $coach->id, 'theme_id' => $theme->id,
                'version'  => $theme->version, 'applied_at' => now(),
            ]);

            return [
                'ok'                => true,
                'mode'              => 'restyle',
                'previous_theme_id' => $previousThemeId,
                'new_theme_id'      => $theme->id,
                'application_id'    => $application->id,
            ];
        });
    }

    /** Store the coach's CURRENT presentation under a theme (for switch-back). */
    private function snapshotThemeStyling(int $coachId, int $themeId, $brand): void
    {
        \App\Models\CoachThemeSetting::updateOrCreate(
            ['coach_id' => $coachId, 'theme_id' => $themeId],
            ['settings_json' => [
                'primary_color' => $brand->primary_color,
                'accent_color'  => $brand->accent_color,
            ]]
        );
    }

    /** Restore the coach's saved styling for $theme, else apply theme defaults. */
    private function applyThemeStyling(int $coachId, Theme $theme, $brand): void
    {
        $saved = \App\Models\CoachThemeSetting::where('coach_id', $coachId)
            ->where('theme_id', $theme->id)->value('settings_json');

        if (! empty($saved)) {
            $brand->primary_color = $saved['primary_color'] ?? $brand->primary_color;
            $brand->accent_color  = $saved['accent_color']  ?? $brand->accent_color;
        } else {
            $colors = $theme->default_colors ?? [];
            if (! empty($colors['primary'])) $brand->primary_color = $colors['primary'];
            if (! empty($colors['accent']))  $brand->accent_color  = $colors['accent'];
        }
        $brand->save();
    }

    /**
     * Free a page's (coach_id, slug) unique key on archive by suffixing the slug
     * with __prev_<id> (idempotent + length-safe for the varchar(120) column).
     */
    private function archivedSlug(string $slug, int $id): string
    {
        $base   = preg_replace('/__prev_\d+$/', '', $slug);
        $suffix = '__prev_' . $id;

        return substr($base, 0, max(1, 120 - strlen($suffix))) . $suffix;
    }

    /**
     * 2026-06-16 — undo the most recent theme switch: discard the current
     * (post-switch) pages and bring back the previous site that was soft-deleted
     * during the switch (restoring each page's original slug). Returns the number
     * of pages restored (0 if there's nothing to revert).
     */
    public function revertToPreviousSite(User $coach): int
    {
        return DB::transaction(function () use ($coach) {
            // The latest soft-deleted batch = the site as it was just before the
            // last switch. Group by the deleted_at timestamp of that switch.
            $latest = CoachPage::onlyTrashed()->forCoach($coach->id)->max('deleted_at');
            if (! $latest) {
                return 0;
            }
            $previous = CoachPage::onlyTrashed()->forCoach($coach->id)
                ->where('deleted_at', $latest)->get();
            if ($previous->isEmpty()) {
                return 0;
            }

            // Remove the current theme's live pages (+ their sections) to free
            // the slugs the restored pages need.
            $currentIds = CoachPage::forCoach($coach->id)->pluck('id');
            if ($currentIds->isNotEmpty()) {
                CoachPageSection::whereIn('coach_page_id', $currentIds)->delete();
                CoachPage::whereIn('id', $currentIds)->forceDelete();
            }

            // Restore the previous pages: un-delete + restore the original slug.
            foreach ($previous as $p) {
                $original = preg_replace('/__prev_\d+$/', '', $p->slug);
                $p->restore();
                $p->update(['slug' => $original]);
            }

            return $previous->count();
        });
    }
}
