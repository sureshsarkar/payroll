<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\CoachSitePageView;
use App\Models\User;
use App\Services\BrandResolver;
use App\Services\Site\SectionRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public-facing renderer for a coach's marketing website.
 *
 * Three entry points all funnel through showPage():
 *   1. Subdomain  — Route::domain('{slug}.{coach_domain}')
 *                    → showPage(null, 'home') resolved by sub→site lookup
 *   2. Subdomain + path — Route::domain(...) → showPage(null, $slug)
 *   3. Path fallback (localhost / no-DNS)  — /coach/{site_slug}/{page_slug?}
 *
 * Multi-page navigation between pages auto-builds the nav from
 * coach_pages.where(is_published).
 *
 * Editor preview entry point (auth-only, no auth-redirect — handled by
 * the middleware group): showPreview() renders a specific page id even
 * if unpublished.
 */
class CoachSitePublicController extends Controller
{
    public function __construct(
        protected readonly SectionRenderer $renderer,
    ) {
    }

    /**
     * Resolve by SUBDOMAIN. The {coach_subdomain} placeholder comes
     * from Route::domain('{coach_subdomain}.{coach_domain}'). Optional
     * {page_slug} param routes to a non-home page.
     */
    public function showOnSubdomain(string $coach_subdomain, ?string $page_slug = null)
    {
        // SECURITY (audit 2026-06-12) — honour domain governance on the
        // subdomain surface too. The custom-domain path resolves through
        // CoachDomain::coachIdForHost() (verified + not suspended), but this
        // subdomain entry point read coach_landing_pages directly and so kept
        // serving a coach whose domain an admin had SUSPENDED. If THIS host has
        // a coach_domains governance row, require it to resolve (verified +
        // active); a suspended/unverified one now 404s, consistent with custom
        // domains. Legacy subdomains with NO governance row are unaffected.
        $host = request()->getHost();
        $hasGovernedRow = \App\Models\CoachDomain::where('hostname', \App\Models\CoachDomain::normalise($host))->exists();
        if ($hasGovernedRow && ! \App\Models\CoachDomain::coachIdForHost($host)) {
            abort(404);
        }

        $site = CoachLandingPage::where('slug', $coach_subdomain)
            ->orWhere('subdomain', $coach_subdomain . '.' . config('app.coach_domain'))
            ->first();
        return $this->showPage($site, $page_slug ?? 'home');
    }

    /**
     * Resolve by PATH (/coach/{site_slug}/{page_slug?}). Useful on
     * localhost and as a path-based fallback in production.
     */
    public function showOnPath(string $site_slug, ?string $page_slug = null)
    {
        $site = CoachLandingPage::where('slug', $site_slug)->first();
        return $this->showPage($site, $page_slug ?? 'home');
    }

    /**
     * Resolve by CUSTOM DOMAIN (White-Label G1, 2026-06-04). The coach is
     * resolved from the request's verified custom domain — ResolveCoachByDomain
     * has already stamped 'resolved_coach_id' on the request. Serves the coach's
     * marketing site at the ROOT of their own domain (client.com/, client.com/about),
     * so a full custom domain behaves like the subdomain flow.
     *
     * Returns 404 when no coach is resolved (i.e. not on a coach domain) — so
     * the platform's own domain is never affected.
     */
    public function showOnDomain(?string $page_slug = null)
    {
        $coachId = (int) request()->attributes->get('resolved_coach_id');
        if ($coachId <= 0) {
            abort(404);
        }
        $site = CoachLandingPage::where('added_by', $coachId)->orderBy('id')->first();
        return $this->showPage($site, $page_slug ?: 'home');
    }

    /**
     * Shared resolver: site + page → rendered HTML.
     */
    protected function showPage(?CoachLandingPage $site, string $pageSlug): Response
    {
        if (! $site) {
            abort(404);
        }
        $coachId = (int) $site->added_by;

        // Owner-preview rule (audit 2026-05-25):
        // - PUBLIC visitors only see PUBLISHED pages.
        // - The OWNER (logged-in coach matching this site) + their staff
        //   (coach_id == owner_id) can preview drafts on the same URL.
        // This makes the dashboard's "Preview live site" button work even
        // before the coach publishes (Webflow / Wix style).
        $isOwner = $this->viewerIsOwner($coachId);

        $query = CoachPage::forCoach($coachId)
            ->where('slug', $pageSlug)
            ->with(['sections' => function ($q) { $q->orderBy('sort_order'); }]);
        if (! $isOwner) {
            $query->published();
        }
        $page = $query->first();

        // Fallback: if no page row but legacy html_content exists,
        // synth a virtual page from the legacy blob.
        if (! $page) {
            if ($pageSlug === 'home' && ! empty($site->html_content)) {
                return $this->renderLegacy($site);
            }
            abort(404);
        }

        $coach = User::find($coachId);
        $brand = app(BrandResolver::class)->forCoach($coachId);

        $siteNav = $this->buildSiteNav($coachId, $page, $site, $isOwner);

        $bodyHtml = $this->renderer->renderSections($page, $coach);

        // Footer resolution (2026-06-17 — dedicated GLOBAL FOOTER):
        //   1. A page with its own VISIBLE footer_v1 section overrides everything.
        //   2. Otherwise, if the page opts in (use_global_footer, default true) and
        //      the coach's global footer is enabled, render that — edit once,
        //      reflects on every page (including home).
        //   3. Otherwise fall through to the master config-driven fallback footer.
        $hasFooterSection = $page->sections->contains(function ($s) {
            return $s->section_type === 'footer_v1'
                && (! isset($s->is_visible) || $s->is_visible);
        });

        if (! $hasFooterSection && ($page->use_global_footer ?? true)) {
            $globalFooter = $this->resolveGlobalFooter($coachId);
            if ($globalFooter) {
                $globalFooter->setRelation('page', $page);
                $bodyHtml .= $this->renderer->renderOne($globalFooter, $page, $coach);
                $hasFooterSection = true; // suppress the generic master fallback footer
            }
        }

        $this->recordPageView($coachId, $page->id);

        // Show a "draft preview" banner to the owner so they know this
        // page isn't visible to the public yet.
        $isDraftPreview = $isOwner && ! $page->is_published;

        $response = response()->view('frontend.coach-site.layouts.master', [
            'page'             => $page,
            'site'             => $site,
            'coach'            => $coach,
            'brand'            => $brand,
            'siteNav'          => $siteNav,
            'bodyHtml'         => $bodyHtml,
            'hasFooterSection' => $hasFooterSection,
            'isDraftPreview'   => $isDraftPreview,
        ]);

        // Public pages cache; owner draft preview does NOT (must reflect edits live)
        if ($isOwner) {
            $response->header('Cache-Control', 'no-store, private');
        } else {
            $response->header('Cache-Control', 'public, max-age=60, s-maxage=120');
        }
        return $response;
    }

    /**
     * 2026-06-17 — the coach's DEDICATED GLOBAL FOOTER (coach_site_footers).
     * Returns an unsaved footer_v1 CoachPageSection built from the coach's
     * single global-footer row so renderOne() can render it through the
     * existing footer_v1.blade.php, or null when the coach has no global footer
     * or has disabled it. Edit once → reflects on every page.
     *
     * No static caching here on purpose: the method runs at most once per page
     * render, and a method-level static would leak a coach's footer across
     * requests in a long-lived worker (Octane).
     */
    protected function resolveGlobalFooter(int $coachId): ?CoachPageSection
    {
        $footer = \App\Models\CoachSiteFooter::where('coach_id', $coachId)
            ->where('is_enabled', true)
            ->first();

        if (! $footer || empty($footer->content_json)) {
            return null;
        }

        // Build an unsaved section the renderer understands. No DB write.
        return new CoachPageSection([
            'section_type'    => 'footer_v1',
            'section_version' => $footer->section_version ?: 'v1',
            'content_json'    => $footer->content_json,
            'is_visible'      => true,
        ]);
    }

    /**
     * Is the current viewer the owner of this coach site (or their staff)?
     * Used to decide whether draft pages are visible.
     */
    protected function viewerIsOwner(int $ownerCoachId): bool
    {
        if (! auth()->check()) return false;
        $u = auth()->user();
        // Coach themselves
        if ((int) $u->id === $ownerCoachId) return true;
        // Coach staff (their coach_id points to the owner)
        if (isset($u->coach_id) && (int) $u->coach_id === $ownerCoachId) return true;
        return false;
    }

    /**
     * Authenticated preview of a single page (published or draft).
     * Used by the editor's iframe at /instructor/web-page/preview/{id}.
     */
    public function preview(int $pageId, Request $request)
    {
        $userId = optional(auth()->user())->id;
        if (! $userId) abort(403);
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;

        $page = CoachPage::where('id', $pageId)
            ->where('coach_id', $coachId)
            ->with(['sections' => function ($q) { $q->orderBy('sort_order'); }])
            ->firstOrFail();

        $site = $page->site ?: CoachLandingPage::where('added_by', $coachId)->orderBy('id')->first();
        $coach = User::find($coachId);
        $brand = app(BrandResolver::class)->forCoach($coachId);
        $siteNav = $this->buildSiteNav($coachId, $page, $site, /* includeDrafts */ true);
        $bodyHtml = $this->renderer->renderSections($page, $coach);
        $hasFooterSection = $page->sections->contains(fn ($s) => $s->section_type === 'footer_v1'
            && (! isset($s->is_visible) || $s->is_visible));
        if (! $hasFooterSection && ($page->use_global_footer ?? true)) {
            $globalFooter = $this->resolveGlobalFooter($coachId);
            if ($globalFooter) {
                $globalFooter->setRelation('page', $page);
                $bodyHtml .= $this->renderer->renderOne($globalFooter, $page, $coach);
                $hasFooterSection = true;
            }
        }

        return view('frontend.coach-site.layouts.master', [
            'page'             => $page,
            'site'             => $site,
            'coach'            => $coach,
            'brand'            => $brand,
            'siteNav'          => $siteNav,
            'bodyHtml'         => $bodyHtml,
            'hasFooterSection' => $hasFooterSection,
            'isDraftPreview'   => ! $page->is_published,
        ]);
    }

    /**
     * GET /instructor/web-page/sections/{id}/content — used by the editor's
     * panel to read current section content for the form. JSON only.
     */
    public function sectionContent(int $id)
    {
        $section = CoachPageSection::findOrFail($id);
        $coachId = userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id;
        $owner = CoachPage::where('id', $section->coach_page_id)
            ->where('coach_id', $coachId)
            ->exists();
        if (! $owner) return response()->json(['ok' => false], 403);
        return response()->json(['ok' => true, 'content' => $section->content_json ?? []]);
    }

    /**
     * Sitemap.xml for the coach's site. Lists all published pages.
     */
    public function sitemap(?string $coach_subdomain = null)
    {
        if ($coach_subdomain) {
            $site = CoachLandingPage::where('slug', $coach_subdomain)->orderBy('id')->first();
        } else {
            // F21 (audit 2026-06-26) — the subdomain route calls this with no arg.
            // Previously it fell to CoachLandingPage::first() (coach #1), so EVERY
            // subdomain served coach #1's page slugs (cross-tenant disclosure).
            // Resolve THIS host's coach instead.
            $coachId = (int) request()->attributes->get('resolved_coach_id');
            if ($coachId <= 0) {
                $coachId = (int) (\App\Models\CoachDomain::coachIdForHost(request()->getHost()) ?? 0);
            }
            $site = null;
            if ($coachId <= 0) {
                $host = request()->getHost();
                $coachDomain = (string) config('app.coach_domain');
                if ($coachDomain && str_ends_with($host, '.' . $coachDomain)) {
                    $sub  = substr($host, 0, -1 * (strlen($coachDomain) + 1));
                    $site = CoachLandingPage::where('slug', $sub)->orWhere('subdomain', $host)->orderBy('id')->first();
                }
            } else {
                $site = CoachLandingPage::where('added_by', $coachId)->orderBy('id')->first();
            }
        }
        if (! $site) abort(404);

        $pages = CoachPage::forCoach($site->added_by)
            ->published()
            ->orderBy('sort_order')
            ->get(['slug', 'updated_at']);

        $base = request()->getSchemeAndHttpHost();
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as $p) {
            $loc = $base . ($p->slug === 'home' ? '/' : '/' . $p->slug);
            $xml .= "  <url><loc>{$loc}</loc><lastmod>{$p->updated_at->format('Y-m-d')}</lastmod></url>\n";
        }
        $xml .= '</urlset>';
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Robots.txt for the coach's site.
     */
    public function robots()
    {
        $base = request()->getSchemeAndHttpHost();
        $txt  = "User-agent: *\nAllow: /\nSitemap: {$base}/sitemap.xml\n";
        return response($txt, 200, ['Content-Type' => 'text/plain']);
    }

    // -------------- helpers --------------

    /**
     * Public nav builder for pages that aren't a CoachPage themselves (e.g. the
     * blog detail page) so they can render the SAME global header/menu. No nav
     * item is marked active (a placeholder current with id 0). Reuses the full
     * custom-menu + page-derived logic. Fail-safe to an empty nav.
     */
    public function navForCoach(int $coachId, ?CoachLandingPage $site = null): array
    {
        try {
            $site ??= CoachLandingPage::where('added_by', $coachId)->orderBy('id')->first();
            $current = new CoachPage();
            $current->id = 0; // matches no nav item → nothing active
            return $this->buildSiteNav($coachId, $current, $site, false);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Build top-nav from the coach's published pages, honoring per-page
     * nav controls (is_visible_in_nav, nav_label override, nav_external_url).
     * Home is always first, others in sort_order.
     */
    protected function buildSiteNav(int $coachId, CoachPage $current, ?CoachLandingPage $site, bool $includeDrafts = false): array
    {
        // 2026-06-17 — if the coach configured a custom menu (active + has
        // items), render from that (with dropdowns). Otherwise fall through to
        // the legacy page-derived nav so coaches who never opened the builder
        // are unaffected.
        $menuNav = $this->buildMenuNav($coachId, $current, $site);
        if ($menuNav !== null) {
            return $menuNav;
        }

        // Use route() to build URLs so they pick up the APP_URL path
        // prefix (e.g. /mbsguru1/public on XAMPP). Hand-rolled string
        // concatenation here ("/coach/" . $slug) produced root-relative
        // URLs that 404'd whenever Apache's docroot was a subdirectory.
        $isPathMode = $site && request()->routeIs('coach.site.path*');

        $q = CoachPage::forCoach($coachId)
            ->where(function ($q) {
                $q->whereNull('is_visible_in_nav')->orWhere('is_visible_in_nav', true);
            });
        if (! $includeDrafts) {
            $q->published();
        }
        $pages = $q->orderByRaw("CASE WHEN page_type='home' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'title', 'page_type', 'is_published',
                   'is_visible_in_nav', 'nav_label', 'nav_external_url']);

        $out = [];
        foreach ($pages as $p) {
            // External URL override — opens in new tab
            if (!empty($p->nav_external_url)) {
                $out[] = [
                    'label'    => $p->nav_label ?: $p->title,
                    'url'      => $p->nav_external_url,
                    'active'   => false,
                    'external' => true,
                ];
                continue;
            }
            if ($isPathMode) {
                // url() ALWAYS includes the APP_URL base path (e.g.
                // /mbsguru1/public on XAMPP). route() does the same but
                // only when the current request carries SCRIPT_NAME —
                // url() is base-path-safe in every scenario.
                $url = $p->page_type === 'home'
                    ? url('/coach/' . $site->slug)
                    : url('/coach/' . $site->slug . '/' . $p->slug);
            } else {
                $url = $p->page_type === 'home' ? url('/') : url('/' . $p->slug);
            }
            $out[] = [
                'label'  => $p->nav_label ?: $p->title,
                'url'    => $url,
                'active' => $p->id === $current->id,
            ];
        }
        return $out;
    }

    /**
     * 2026-06-17 — build the nav from the coach's CUSTOM MENU (coach_menus /
     * coach_menu_items) with one level of dropdowns. Returns null when the coach
     * has no active menu or no visible items, so buildSiteNav() falls back to the
     * page-derived nav. Output items: [label, url, active, external, children[]].
     */
    protected function buildMenuNav(int $coachId, CoachPage $current, ?CoachLandingPage $site): ?array
    {
        $menu = \App\Models\CoachMenu::where('coach_id', $coachId)
            ->where('location', 'primary')
            ->where('is_active', true)
            ->first();
        if (! $menu) {
            return null;
        }

        $items = \App\Models\CoachMenuItem::where('menu_id', $menu->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();
        if ($items->isEmpty()) {
            return null;
        }

        $isPathMode = $site && request()->routeIs('coach.site.path*');
        $pageMap = CoachPage::forCoach($coachId)->get()->keyBy('id')->all();

        $build = function (\App\Models\CoachMenuItem $it) use ($pageMap, $site, $isPathMode, $current) {
            return [
                'label'     => $it->label,
                'url'       => $it->resolveUrl($pageMap, $site, $isPathMode),
                'external'  => $it->target === '_blank',
                'link_type' => $it->link_type,                 // 'none' → render as plain heading
                'active'    => in_array($it->link_type, ['page', 'section'], true)
                               && (int) $it->page_id === (int) $current->id,
            ];
        };

        $byParent = $items->groupBy(fn ($i) => $i->parent_id ?? 0);
        $out = [];
        foreach ($byParent->get(0, collect()) as $top) {
            $node = $build($top);
            $node['layout'] = $top->layout ?: 'dropdown';
            $children = [];
            foreach ($byParent->get($top->id, collect()) as $child) {
                $childNode = $build($child);
                // Level 3 — for a mega menu, these are the links inside a column.
                $links = [];
                foreach ($byParent->get($child->id, collect()) as $g) {
                    $links[] = $build($g);
                }
                if ($links) {
                    $childNode['children'] = $links;
                }
                $children[] = $childNode;
            }
            if ($children) {
                $node['children'] = $children;
                // Active if any descendant (column or its links) is active.
                $node['active'] = $node['active']
                    || collect($children)->contains('active', true)
                    || collect($children)->flatMap(fn ($c) => $c['children'] ?? [])->contains('active', true);
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Legacy GrapesJS fallback — only fires when no CoachPage rows exist
     * for this site yet. Should be a vanishing surface once the auto-migration
     * (PageManager::migrateLegacy) runs on first dashboard visit.
     */
    protected function renderLegacy(CoachLandingPage $site)
    {
        $coachId = (int) $site->added_by;
        $coach = User::find($coachId);
        $brand = app(BrandResolver::class)->forCoach($coachId);

        $bodyHtml  = '';
        if (! empty($site->css_content)) {
            $bodyHtml .= '<style>' . $site->css_content . '</style>';
        }
        $bodyHtml .= $site->html_content;

        // Synth a minimal CoachPage stand-in for the layout.
        $page = new CoachPage([
            'title' => $site->website_name ?? 'Home',
            'slug'  => 'home',
            'meta_title' => $site->website_name ?? null,
            'meta_description' => null,
            'robots' => 'index',
            'is_published' => (bool) $site->is_published,
        ]);

        return response()->view('frontend.coach-site.layouts.master', [
            'page'  => $page,
            'site'  => $site,
            'coach' => $coach,
            'brand' => $brand,
            'siteNav' => [],
            'bodyHtml' => $bodyHtml,
            'hasFooterSection' => false,
        ]);
    }

    /**
     * Fire-and-forget pageview event. Bots skipped, queued write so it
     * never blocks the response.
     */
    protected function recordPageView(int $coachId, int $pageId): void
    {
        if ($this->looksLikeBot(request()->userAgent())) return;
        $ip = request()->ip();
        $ua = request()->userAgent() ?? '';
        $day = now()->format('Y-m-d');
        $hash = hash('sha256', $ip . $ua . $day);
        $row = [
            'coach_id'     => $coachId,
            'page_id'      => $pageId,
            'visitor_hash' => $hash,
            'referer'      => substr((string) request()->headers->get('referer'), 0, 500) ?: null,
            'utm_source'   => substr((string) request()->query('utm_source'), 0, 100) ?: null,
            'utm_medium'   => substr((string) request()->query('utm_medium'), 0, 100) ?: null,
            'utm_campaign' => substr((string) request()->query('utm_campaign'), 0, 100) ?: null,
            'created_at'   => now(),
        ];
        // F48 (audit 2026-06-26) — defer the write until AFTER the response is
        // sent (the docblock claimed "queued" but it was a synchronous insert on
        // every public render). app()->terminating runs post-response.
        app()->terminating(function () use ($row) {
            try {
                DB::table('coach_site_page_views')->insert($row);
            } catch (\Throwable $e) {
                Log::debug('coach-page-view-insert-failed', ['err' => $e->getMessage()]);
            }
        });
    }

    protected function looksLikeBot(?string $ua): bool
    {
        if (! $ua) return true;
        return (bool) preg_match('/bot|crawler|spider|slurp|googlebot|bingbot|facebookexternalhit|whatsapp|telegram/i', $ua);
    }
}
