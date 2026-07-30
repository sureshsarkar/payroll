<?php

namespace App\Http\Controllers\Frontend\Coach\Traits;

use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\User;

/**
 * Shared helpers for the coach-scoped commerce controllers
 * (CoachCartController, CoachCheckoutController, CoachAuthController,
 * CoachStudentDashboardController).
 *
 * Builds the synthetic $page + $siteNav objects the coach master layout
 * (frontend.coach-site.layouts.master) expects when rendering pages
 * outside the marketing-page render flow.
 */
trait BuildsCoachSiteContext
{
    /**
     * Produce a stdClass with the minimum properties the coach master
     * layout reads (title, meta_*, og_image, robots, slug, coach_id).
     */
    protected function syntheticPage(User $coach, string $slug, string $title): object
    {
        return (object) [
            'id'               => null,
            'coach_id'         => $coach->id,
            'slug'             => $slug,
            'title'            => $title,
            'meta_title'       => null,
            'meta_description' => null,
            'og_image'         => null,
            'robots'           => 'noindex',   // commerce pages shouldn't be SEO-indexed
            'is_published'     => true,
        ];
    }

    /**
     * Build the same site-nav array that CoachSitePublicController uses.
     * Returns published pages for this coach, formatted as [label,url,active]
     * tuples the coach master's <nav> renders.
     */
    protected function siteNavFor(User $coach, string $coachSlug, ?string $activeSlug = null): array
    {
        $pages = CoachPage::forCoach($coach->id)
            ->published()
            ->where(function ($q) {
                $q->whereNull('is_visible_in_nav')->orWhere('is_visible_in_nav', true);
            })
            ->orderByRaw("CASE WHEN page_type='home' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->get(['slug', 'title', 'page_type', 'nav_label', 'nav_external_url']);

        $out = [];
        foreach ($pages as $p) {
            if (! empty($p->nav_external_url)) {
                $out[] = [
                    'label'    => $p->nav_label ?: $p->title,
                    'url'      => $p->nav_external_url,
                    'active'   => false,
                    'external' => true,
                ];
                continue;
            }
            // url() reliably includes the APP_URL base path on every host
            // setup; route() drops it when called inside a synthetic
            // Request that lacks SCRIPT_NAME (which happens in some
            // testing + cli contexts). url() is base-path-safe always.
            $url = $p->page_type === 'home'
                ? url('/coach/' . $coachSlug)
                : url('/coach/' . $coachSlug . '/' . $p->slug);
            $out[] = [
                'label'    => $p->nav_label ?: $p->title,
                'url'      => $url,
                'active'   => $activeSlug !== null && $p->slug === $activeSlug,
                'external' => false,
            ];
        }
        return $out;
    }
}
