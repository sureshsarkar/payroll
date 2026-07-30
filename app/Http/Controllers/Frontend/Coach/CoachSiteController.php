<?php

namespace App\Http\Controllers\Frontend\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\CoachPageVersion;
use App\Models\CoachSiteFooter;
use App\Models\User;
use App\Services\Site\PageManager;
use App\Services\Site\SectionRegistry;
use App\Services\Site\SectionRenderer;
use App\Services\Site\SiteSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Coach Marketing Website System (Phase 1) — coach-facing editor.
 *
 * Routes (mounted under /instructor — auth + requires.membership):
 *   GET    /instructor/web-page                       site dashboard (replaces old template picker)
 *   POST   /instructor/web-page/site                  create/update site root (subdomain + website name)
 *   POST   /instructor/web-page/pages                 create page
 *   GET    /instructor/web-page/pages/{id}            page editor
 *   PUT    /instructor/web-page/pages/{id}            rename + meta update
 *   POST   /instructor/web-page/pages/{id}/publish    publish/unpublish toggle
 *   DELETE /instructor/web-page/pages/{id}            delete (Home blocked)
 *   POST   /instructor/web-page/pages/{id}/sections   add section
 *   POST   /instructor/web-page/pages/{id}/reorder    reorder sections
 *   PUT    /instructor/web-page/sections/{id}         update section content (autosave)
 *   DELETE /instructor/web-page/sections/{id}         delete section
 *   POST   /instructor/web-page/media                 image upload
 *   POST   /instructor/web-page/youtube-refresh       invalidate YouTube cache (rate-limited)
 *   GET    /instructor/web-page/pages/{id}/versions   list versions
 *   POST   /instructor/web-page/pages/{id}/restore    restore version
 *
 * IDOR safety: every {id} param is resolved via findOwnedPage() / findOwnedSection()
 * which scope to the authenticated coach's coach_id.
 */
class CoachSiteController extends Controller
{
    public function __construct(
        protected readonly PageManager $pages,
        protected readonly SectionRenderer $renderer,
        protected readonly SiteSettingsService $settings,
    ) {
    }

    // ---------------- Dashboard ----------------

    public function index()
    {
        if (! $this->permitted()) {
            return view('errors.403');
        }
        $coachId = $this->coachId();

        // Auto-migrate any legacy GrapesJS site for this coach (idempotent)
        $legacySite = CoachLandingPage::where('added_by', $coachId)->first();
        if ($legacySite) {
            $this->pages->migrateLegacy($legacySite);
        }

        // Bootstrap a Home page if the coach has nothing yet
        $home = $this->pages->ensureHome($coachId, $legacySite);

        $pages = CoachPage::forCoach($coachId)
            ->orderBy('sort_order')
            ->withCount('sections')
            ->get();

        return view('frontend.instructor-dashboard.coach-site.dashboard', [
            'site'  => $legacySite,
            'pages' => $pages,
            'home'  => $home,
        ]);
    }

    /** Update / create the Site root (subdomain + website_name). Reuses CoachLandingPage. */
    public function updateSite(Request $request)
    {
        if (! $this->permitted()) {
            return redirect()->back();
        }
        $coachId = $this->coachId();

        $request->validate([
            'website_name' => ['required', 'string', 'max:80'],
            'subdomain'    => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
        ]);

        $site = CoachLandingPage::where('added_by', $coachId)->first();
        $subFull = $request->subdomain . '.' . config('app.coach_domain');

        if (! $site) {
            $site = CoachLandingPage::create([
                'website_name' => $request->website_name,
                'subdomain'    => $subFull,
                'slug'         => $request->subdomain,
                'added_by'     => $coachId,
                'is_published' => 1,
                'title'        => $request->website_name,
            ]);
        } else {
            $site->update([
                'website_name' => $request->website_name,
                'subdomain'    => $subFull,
                'slug'         => $request->subdomain,
                'title'        => $request->website_name,
            ]);
        }
        return redirect()->route('instructor.web-page.index')
            ->with('success', __('Website settings saved.'));
    }

    // ---------------- Page CRUD ----------------

    public function createPage(Request $request)
    {
        if (! $this->permitted()) return redirect()->back();
        $request->validate([
            'title'     => ['required', 'string', 'max:160'],
            'page_type' => ['required', 'in:' . implode(',', CoachPage::TYPES)],
        ]);
        $site = CoachLandingPage::where('added_by', $this->coachId())->first();
        $page = $this->pages->createPage($this->coachId(), $request->page_type, $request->title, $site);
        return redirect()->route('instructor.web-page.edit', $page->id);
    }

    public function editPage(int $id)
    {
        if (! $this->permitted()) return view('errors.403');
        $page = $this->findOwnedPage($id);
        $page->load('sections');

        $settings = $this->settings->for($this->coachId());

        // All pages of this coach (for Pages tab)
        $allPages = \App\Models\CoachPage::forCoach($this->coachId())
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'page_type', 'title', 'is_published',
                   'is_visible_in_nav', 'nav_label', 'nav_external_url']);

        return view('frontend.instructor-dashboard.coach-site.editor', [
            'page'       => $page,
            'allPages'   => $allPages,
            'settings'   => $settings,
            'registry'   => SectionRegistry::byCategory(),
            'categories' => [
                'hero'       => 'Hero',
                'content'    => 'Content',
                'trust'      => 'Trust',
                'media'      => 'Media',
                'conversion' => 'Conversion',
                'footer'     => 'Footer',
            ],
        ]);
    }

    public function updatePage(Request $request, int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($id);
        $request->validate([
            'title'            => ['sometimes', 'string', 'max:160'],
            'meta_title'       => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'og_image'         => ['nullable', 'string', 'max:500'],
            'robots'           => ['nullable', 'in:index,noindex'],
        ]);
        if ($request->filled('title') && $request->title !== $page->title) {
            $this->pages->renamePage($page, $request->title);
        }
        $page->update($request->only(['meta_title', 'meta_description', 'og_image', 'robots']));
        $this->pages->snapshot($page->fresh());
        return response()->json(['ok' => true, 'page' => $page->fresh()]);
    }

    public function publishPage(int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($id);
        $result = $this->pages->setPublished($page, ! $page->is_published);
        return response()->json($result);
    }

    public function deletePage(int $id)
    {
        if (! $this->permitted()) return redirect()->back();
        $page = $this->findOwnedPage($id);
        $this->pages->deletePage($page);
        return redirect()->route('instructor.web-page.index')->with('success', __('Page deleted.'));
    }

    // ---------------- Section CRUD ----------------

    public function addSection(Request $request, int $pageId)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($pageId);
        $request->validate(['section_type' => ['required', 'string', 'max:60']]);
        if (! SectionRegistry::exists($request->section_type)) {
            return response()->json(['ok' => false, 'error' => 'Unknown section type'], 422);
        }
        // F34 (audit 2026-06-26) — reject HIDDEN section types (e.g. the
        // html_passthrough_v1 raw-HTML migration escape hatch). They're hidden
        // from the drawer; a crafted addSection request must not be able to plant
        // one (it renders unsanitized HTML/CSS = stored XSS).
        if (! empty(SectionRegistry::get($request->section_type)['hidden'])) {
            return response()->json(['ok' => false, 'error' => 'This section type cannot be added'], 422);
        }
        $section = $this->pages->addSection($page, $request->section_type);

        // No bundle auto-stamping anymore. Each YouTube card becomes its
        // own buyable Course on render (see VideoCourseProvisioner-style
        // ensureForVideo() calls in recorded_courses_v1.blade.php).

        return response()->json([
            'ok'      => true,
            'section' => $section->fresh(),
            'html'    => $this->renderer->renderOne($section->fresh(), $page, $this->coachUser()),
        ]);
    }

    public function updateSection(Request $request, int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $section = $this->findOwnedSection($id);
        $content = $request->input('content', []);
        if (! is_array($content)) {
            return response()->json(['ok' => false, 'error' => 'content must be object'], 422);
        }

        // NOTE: we deliberately do NOT auto-stamp linked_course_slug here.
        // Leaving it empty enables the per-video course mode in the partial,
        // so each YouTube card becomes a separately-buyable item. A coach
        // who wants bundle mode types a slug into the section editor.

        $result = $this->pages->updateSection($section, $content);
        if (! $result['ok']) {
            return response()->json($result, 422);
        }
        return response()->json([
            'ok'   => true,
            'html' => $this->renderer->renderOne($section->fresh(), $section->page, $this->coachUser()),
        ]);
    }

    public function deleteSection(int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $section = $this->findOwnedSection($id);
        $this->pages->deleteSection($section);
        return response()->json(['ok' => true]);
    }

    public function reorderSections(Request $request, int $pageId)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($pageId);
        $ids = $request->input('order', []);
        if (! is_array($ids)) {
            return response()->json(['ok' => false], 422);
        }
        $this->pages->reorderSections($page, array_map('intval', $ids));
        return response()->json(['ok' => true]);
    }

    // ---------------- Media + YouTube refresh ----------------

    public function uploadMedia(Request $request)
    {
        if (! $this->permitted()) return response()->json(['error' => 'Forbidden'], 403);
        $request->validate([
            // FT-UPLOAD-1 fix (2026-05-27) — dropped svg. The uploaded
            // media surfaces publicly on the coach's white-label site
            // and in their landing-page sections; an SVG with <script>
            // would execute in every visitor's browser. PNG/WebP cover
            // every realistic use case; vector content can be rendered
            // server-side via the design-token system.
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);
        $path = $request->file('file')->store('coach-site-media', 'public');
        return response()->json(['url' => url('/uploads/store/' . $path)]);
    }

    public function refreshYouTube(Request $request)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $key = 'youtube-refresh:' . $this->coachId();
        if (\Cache::has($key)) {
            return response()->json(['ok' => false, 'error' => 'Try again in an hour'], 429);
        }
        \Cache::put($key, 1, 3600);

        $cred = \App\Models\YoutubeCredential::where('instructor_id', $this->coachId())->first();
        if ($cred && $cred->channel_id) {
            app(\App\Services\Site\YouTubeFetcher::class)->invalidate($cred->channel_id);
        }
        return response()->json(['ok' => true]);
    }

    // ---------------- Versions ----------------

    public function listVersions(int $pageId)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($pageId);
        $versions = $page->versions()
            ->limit(20)
            ->get(['id', 'created_at', 'created_by']);
        return response()->json(['ok' => true, 'versions' => $versions]);
    }

    public function restoreVersion(int $pageId, int $versionId)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($pageId);
        $version = CoachPageVersion::where('coach_page_id', $page->id)
            ->where('id', $versionId)
            ->firstOrFail();
        $this->pages->restoreVersion($version);
        return response()->json(['ok' => true]);
    }

    // ---------------- Theme switching (Phase 4) ----------------

    /**
     * POST /instructor/web-page/change-theme
     * Re-applies a different theme to the coach's site. WIPES current
     * pages (snapshotted to coach_page_versions for revert) and clones
     * the new theme. Runs as a foreground operation (small dataset);
     * a background job variant exists for large coaches via ApplyThemeJob.
     */
    public function changeTheme(Request $request)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        // Validation: theme must be in the ALLOWED (enabled) list; optional mode.
        $request->validate([
            'theme_id' => ['required', 'integer', 'exists:themes,id'],
            'mode'     => ['nullable', 'in:restyle,rebuild'],
        ]);

        $theme = \App\Models\Theme::enabled()->where('id', $request->theme_id)->first();
        if (! $theme) {
            return response()->json(['ok' => false, 'error' => __('Theme unavailable')], 422);
        }

        // TENANT ISOLATION — only ever the authenticated coach's own site.
        $coach = $this->coachUser();
        if (! $coach) return response()->json(['ok' => false], 403);

        $site = \App\Models\CoachLandingPage::where('added_by', $coach->id)->first();
        $previousTheme = $site && $site->theme_id ? \App\Models\Theme::find($site->theme_id) : null;
        $hasPages = \App\Models\CoachPage::forCoach($coach->id)->exists();

        // DEFAULT = RESTYLE (appearance only — website data untouched). 'rebuild'
        // is an explicit opt-in that re-seeds the theme's pages (safe: old pages
        // are soft-deleted + revertible). A brand-new coach with no pages gets
        // the theme's starter pages seeded.
        $applicator = app(\App\Services\Theme\ThemeApplicator::class);
        try {
            $result = ($request->input('mode') === 'rebuild' || ! $hasPages)
                ? $applicator->applyToCoach($theme, $coach, 'replace')   // transaction-safe; rolls back on error
                : $applicator->restyleForCoach($theme, $coach);          // transaction-safe; content preserved
        } catch (\Throwable $e) {
            \Log::error('theme-change-failed', ['coach_id' => $coach->id, 'theme_id' => $theme->id, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => __('Theme change failed — your website is unchanged.')], 500);
        }

        // Audit log (best-effort; the switch is already committed).
        try {
            \App\Services\ActivityLogger::log(
                \App\Models\ActivityLog::UPDATED, 'theme', $coach,
                ['theme_id' => $previousTheme?->id], ['theme_id' => $theme->id],
                'Changed website theme to "' . $theme->name . '"'
            );
        } catch (\Throwable $e) { /* logging must never block the response */ }

        // Email the coach (best-effort).
        try {
            $coach->notify(new \App\Notifications\WebsiteThemeChanged($theme, $previousTheme, $site));
        } catch (\Throwable $e) {
            \Log::warning('theme-change-email-failed', ['coach_id' => $coach->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'ok'                => true,
            'mode'              => $result['mode'] ?? 'rebuild',
            'pages_created'     => $result['pages_created'] ?? 0,
            'sections_created'  => $result['sections_created'] ?? 0,
            'redirect_to'       => route('instructor.web-page.index'),
            'message'           => __('Theme changed to ":name". Your website data is safe.', ['name' => $theme->name]),
        ]);
    }

    /**
     * POST /instructor/web-page/revert-theme
     * 2026-06-16 — undo the last theme switch: discard the new theme and bring
     * back the previous site (its pages + sections are soft-deleted, not
     * destroyed, so nothing is lost on a switch).
     */
    public function revertThemeSwitch()
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $coach = $this->coachUser();
        if (! $coach) return response()->json(['ok' => false], 403);

        $restored = app(\App\Services\Theme\ThemeApplicator::class)->revertToPreviousSite($coach);

        return response()->json([
            'ok'             => $restored > 0,
            'pages_restored' => $restored,
            'message'        => $restored > 0
                ? __(':n pages restored from your previous site.', ['n' => $restored])
                : __('No previous site to restore.'),
            'redirect_to'    => route('instructor.web-page.index'),
        ]);
    }

    /**
     * GET /instructor/web-page/theme-picker — show enabled themes to
     * switch to from the coach panel (post-onboarding).
     */
    public function themePickerInPanel()
    {
        if (! $this->permitted()) return view('errors.403');
        $themes = \App\Models\Theme::enabled()
            ->with('categories')
            ->orderBy('sort_order')
            ->get();
        $categories = \App\Models\ThemeCategory::orderBy('sort_order')->get();

        $currentSite = \App\Models\CoachLandingPage::where('added_by', $this->coachId())->first();
        $currentThemeId = $currentSite?->theme_id;

        return view('frontend.instructor-dashboard.coach-site.theme-picker', [
            'themes'         => $themes,
            'categories'     => $categories,
            'currentThemeId' => $currentThemeId,
        ]);
    }

    // ---------------- Starter pack (one-click add common sections) ----------------

    /**
     * POST /instructor/web-page/pages/{id}/starter-pack
     * Adds Hero + About + Services + Testimonials + Lead Form + Footer
     * to a page in one go. Skips section types already present.
     */
    public function applyStarterPack(int $pageId, Request $request)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($pageId);

        $pack = $request->input('pack', 'default');
        $packs = [
            'default' => ['hero_v1', 'about_v1', 'services_grid_v1', 'testimonials_v1', 'lead_form_v1', 'footer_v1'],
            'landing' => ['hero_v1', 'stats_v1', 'services_grid_v1', 'testimonials_v1', 'cta_banner_v1', 'lead_form_v1', 'footer_v1'],
            'about'   => ['hero_v1', 'about_v1', 'stats_v1', 'testimonials_v1', 'cta_banner_v1'],
            'pricing' => ['hero_v1', 'services_grid_v1', 'faq_v1', 'cta_banner_v1', 'lead_form_v1'],
            'contact' => ['hero_v1', 'contact_v1', 'lead_form_v1'],
        ];
        $types = $packs[$pack] ?? $packs['default'];

        $existing = $page->sections()->pluck('section_type')->toArray();
        $added = 0;
        foreach ($types as $type) {
            if (in_array($type, $existing, true)) continue;   // skip duplicates
            $this->pages->addSection($page, $type);
            $added++;
        }

        return response()->json(['ok' => true, 'added' => $added]);
    }

    // ---------------- Site Settings ----------------

    /**
     * GET /instructor/web-page/settings → site-wide settings JSON.
     */
    public function getSettings()
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $s = $this->settings->for($this->coachId());
        return response()->json(['ok' => true, 'settings' => $s]);
    }

    /**
     * POST /instructor/web-page/settings → save site-wide settings.
     * Accepts any subset of the fillable columns on coach_site_settings.
     */
    public function updateSettings(Request $request)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $request->validate([
            'sticky_cta_enabled' => 'sometimes|boolean',
            'sticky_cta_text'    => 'sometimes|nullable|string|max:50',
            'sticky_cta_url'     => 'sometimes|nullable|string|max:500',
            'whatsapp_enabled'   => 'sometimes|boolean',
            'whatsapp_number'    => 'sometimes|nullable|string|max:30',
            'whatsapp_message'   => 'sometimes|nullable|string|max:200',
            'favicon_url'        => 'sometimes|nullable|string|max:500',
            'social_facebook'    => 'sometimes|nullable|string|max:500',
            'social_instagram'   => 'sometimes|nullable|string|max:500',
            'social_youtube'     => 'sometimes|nullable|string|max:500',
            'social_twitter'     => 'sometimes|nullable|string|max:500',
            'social_linkedin'    => 'sometimes|nullable|string|max:500',
            'social_tiktok'      => 'sometimes|nullable|string|max:500',
            'social_pinterest'   => 'sometimes|nullable|string|max:500',
            'analytics_ga4_id'   => 'sometimes|nullable|string|max:50',
            'analytics_meta_pixel_id' => 'sometimes|nullable|string|max:50',
            'analytics_gtm_id'   => 'sometimes|nullable|string|max:50',
            // P1-4 (2026-05-29) — CSS + custom scripts on the coach
            // white-label site are intentionally raw-rendered in
            // master.blade.php (lines ~138/143/278/459). Pre-fix the
            // input was max-length-only, so a coach could inject:
            //   • CSS-based attacks (`expression()`, `behavior:`,
            //     `javascript:` URIs in `@import`)
            //   • Arbitrary <script> via custom_head_scripts /
            //     custom_body_scripts which executes on every
            //     visitor to that coach's white-label tenant.
            //
            // We can't kill `custom_head_scripts` outright — coaches
            // use it for GA/Meta/GTM (the dedicated `analytics_*`
            // fields below cover the common case; the raw script
            // field is for the edge cases — Hotjar, custom CRMs, …).
            //
            // Minimum-viable hardening:
            //  (a) CSS — strip dangerous patterns at the validator
            //      via a regex rule.
            //  (b) Scripts — audit-log when the value changes so
            //      operators have a paper trail (handled in the
            //      service `SiteSettingsService::update`; not in
            //      this commit's scope but tracked).
            //  (c) Length cap reduced from 100KB → 50KB; that's
            //      still ample for legitimate tracking snippets.
            //
            // Follow-up (not in this commit): per-tenant CSP header
            // in ResolveCoachByDomain middleware.
            // P1-4 follow-up (2026-05-29) — replaced the regex-only
            // check with App\Rules\SafeCss which parses the input into
            // a CSS AST and rejects:
            //   - expression() / -moz-binding (legacy XSS sinks)
            //   - behavior:/behaviour: (IE XSS sink)
            //   - url(javascript:…) / url(vbscript:…)
            //   - url(data:text/html…) / url(data:…javascript…)
            //   - SVG data URLs containing <script> / on* handlers
            //   - parse errors (refuse what neither browser nor parser
            //     can predictably interpret)
            // The CSP middleware is the runtime defense; this is the
            // at-input defense so coaches get an immediate error.
            'custom_css'         => ['sometimes', 'nullable', 'string', 'max:50000', new \App\Rules\SafeCss],
            'custom_head_scripts'=> 'sometimes|nullable|string|max:50000',
            'custom_body_scripts'=> 'sometimes|nullable|string|max:50000',
            'seo_default_title_suffix' => 'sometimes|nullable|string|max:60',
            'seo_default_description'  => 'sometimes|nullable|string|max:160',
            'seo_og_image_default'     => 'sometimes|nullable|string|max:500',
            'footer_config'      => 'sometimes|nullable|array',
            'nav_show_cta'       => 'sometimes|boolean',
            'nav_cta_text'       => 'sometimes|nullable|string|max:30',
            'nav_cta_url'        => 'sometimes|nullable|string|max:500',
            // Site-wide typography: a flat map of {role}_{device} => px. Values
            // are re-clamped when the CSS is built, so a loose array rule is fine.
            'typography_config'      => 'sometimes|nullable|array',
            'typography_config.*'    => 'nullable|integer|min:8|max:120',
        ]);
        $row = $this->settings->update($this->coachId(), $request->all());
        return response()->json(['ok' => true, 'settings' => $row]);
    }

    // ---------------- Section duplicate + visibility ----------------

    /**
     * POST /instructor/web-page/sections/{id}/duplicate → clone the section
     * onto the same page, placed right after the original.
     */
    public function duplicateSection(int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $section = $this->findOwnedSection($id);

        $copy = $section->replicate(['id', 'created_at', 'updated_at']);
        $copy->sort_order = $section->sort_order + 1;
        $copy->save();

        // Push everything after the source down by 1
        \DB::table('landing_sections')
            ->where('coach_page_id', $section->coach_page_id)
            ->where('id', '!=', $copy->id)
            ->where('sort_order', '>=', $copy->sort_order)
            ->increment('sort_order');

        // Re-set copy to exactly orig+1
        $copy->update(['sort_order' => $section->sort_order + 1]);

        $this->pages->snapshot($section->page->fresh());
        return response()->json(['ok' => true, 'section' => $copy]);
    }

    /**
     * POST /instructor/web-page/sections/{id}/toggle-visible — toggle
     * is_visible without deleting. Hidden sections don't render publicly.
     */
    public function toggleSectionVisible(int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $section = $this->findOwnedSection($id);
        $section->update(['is_visible' => ! $section->is_visible]);
        $this->pages->snapshot($section->page);
        return response()->json(['ok' => true, 'is_visible' => (bool) $section->is_visible]);
    }

    // ---------------- Page duplicate + nav controls + manual slug ----------------

    /**
     * POST /instructor/web-page/pages/{id}/duplicate — clone an entire page
     * including all sections. New page is draft with slug suffixed "-copy".
     */
    public function duplicatePage(int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $source = $this->findOwnedPage($id);

        $newSlug = \App\Models\CoachPage::generateUniqueSlug($source->coach_id, $source->slug . '-copy');
        $clone = $source->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
        $clone->slug = $newSlug;
        $clone->title = $source->title . ' (Copy)';
        $clone->is_published = false;
        $clone->page_type = $source->page_type === 'home' ? 'custom' : $source->page_type;
        $clone->save();

        // Clone sections
        foreach ($source->sections as $s) {
            $clone->sections()->create([
                'section_type'    => $s->section_type,
                'section_version' => $s->section_version,
                'sort_order'      => $s->sort_order,
                'content_json'    => $s->content_json,
                'is_visible'      => $s->is_visible,
            ]);
        }

        return response()->json(['ok' => true, 'page_id' => $clone->id]);
    }

    /**
     * POST /instructor/web-page/pages/{id}/nav — update nav-related fields:
     *   is_visible_in_nav, nav_label, nav_external_url
     */
    public function updatePageNav(Request $request, int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($id);
        $request->validate([
            'is_visible_in_nav' => 'sometimes|boolean',
            'nav_label'         => 'sometimes|nullable|string|max:60',
            'nav_external_url'  => 'sometimes|nullable|string|max:500',
            'use_global_footer' => 'sometimes|boolean',
        ]);
        $page->update($request->only(['is_visible_in_nav', 'nav_label', 'nav_external_url', 'use_global_footer']));
        return response()->json(['ok' => true, 'page' => $page->fresh()]);
    }

    // ---------------- Global footer (2026-06-17) ----------------

    /** Social platforms editable from the Global Footer (order = footer render order). */
    private const FOOTER_SOCIALS = ['facebook', 'instagram', 'youtube', 'twitter', 'linkedin', 'tiktok', 'pinterest'];

    /**
     * GET /instructor/web-page/footer — the dedicated Global Footer editor.
     * One footer per coach; rendered on every page that opts in.
     */
    public function editGlobalFooter()
    {
        if (! $this->permitted()) {
            return view('errors.403');
        }
        $coachId = $this->coachId();
        $footer  = CoachSiteFooter::forCoachOrNew($coachId);
        $site    = CoachLandingPage::where('added_by', $coachId)->first();
        // Count pages that currently opt out, so the UI can hint.
        $optedOut = CoachPage::forCoach($coachId)->where('use_global_footer', false)->count();

        $s = $this->settings->for($coachId);
        $social = [];
        foreach (self::FOOTER_SOCIALS as $key) {
            $social[$key] = $s->{'social_' . $key} ?? '';
        }

        return view('frontend.instructor-dashboard.coach-site.global-footer', [
            'footer'   => $footer,
            'content'  => $footer->content_json ?: CoachSiteFooter::defaultContent(),
            'site'     => $site,
            'optedOut' => $optedOut,
            'social'   => $social,
        ]);
    }

    /**
     * POST /instructor/web-page/footer — save the global footer. Content is
     * validated against the SAME footer_v1 schema the section editor uses, so
     * it renders identically through footer_v1.blade.php.
     */
    public function updateGlobalFooter(Request $request)
    {
        if (! $this->permitted()) {
            return response()->json(['ok' => false], 403);
        }
        $coachId = $this->coachId();

        $content = $request->input('content', []);
        if (! is_array($content)) {
            return response()->json(['ok' => false, 'error' => 'content must be object'], 422);
        }
        // Normalise: drop empty link groups / links so we never persist blanks.
        $content['link_groups'] = collect($content['link_groups'] ?? [])
            ->map(function ($g) {
                $g['links'] = collect($g['links'] ?? [])
                    ->filter(fn ($l) => trim((string) ($l['label'] ?? '')) !== '' && trim((string) ($l['url'] ?? '')) !== '')
                    ->values()->all();
                return $g;
            })
            ->filter(fn ($g) => trim((string) ($g['title'] ?? '')) !== '' || ! empty($g['links']))
            ->values()->all();
        $content['show_social'] = (bool) ($content['show_social'] ?? true);

        $errors = SectionRegistry::validate('footer_v1', $content);
        if ($errors) {
            return response()->json(['ok' => false, 'errors' => $errors], 422);
        }

        $footer = CoachSiteFooter::forCoachOrNew($coachId);
        $footer->coach_id        = $coachId;
        $footer->content_json    = $content;
        $footer->is_enabled      = $request->boolean('is_enabled', true);
        $footer->section_version = 'v1';
        $footer->save();

        $social = $request->input('social');
        if (is_array($social)) {
            $socialData = [];
            foreach (self::FOOTER_SOCIALS as $key) {
                $val = trim((string) ($social[$key] ?? ''));
                $socialData['social_' . $key] = $val !== '' ? mb_substr($val, 0, 500) : null;
            }
            $this->settings->update($coachId, $socialData);
        }

        return response()->json(['ok' => true, 'is_enabled' => $footer->is_enabled]);
    }

    /**
     * PUT /instructor/web-page/pages/{id}/slug — manual slug edit (Home is locked).
     */
    public function updatePageSlug(Request $request, int $id)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $page = $this->findOwnedPage($id);
        if ($page->page_type === 'home') {
            return response()->json(['ok' => false, 'error' => 'Home slug is locked'], 422);
        }
        $request->validate(['slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/']]);

        // Ensure uniqueness for this coach
        $exists = \App\Models\CoachPage::where('coach_id', $page->coach_id)
            ->where('slug', $request->slug)
            ->where('id', '!=', $page->id)
            ->exists();
        if ($exists) {
            return response()->json(['ok' => false, 'error' => 'Slug already in use'], 422);
        }
        $page->update(['slug' => $request->slug]);
        return response()->json(['ok' => true]);
    }

    /**
     * GET /instructor/web-page/pages-list — JSON list of pages for the
     * Pages tab in the editor (multi-page reorder + nav controls).
     */
    public function pagesList()
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $pages = \App\Models\CoachPage::forCoach($this->coachId())
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'page_type', 'title', 'is_published',
                   'is_visible_in_nav', 'nav_label', 'nav_external_url', 'sort_order']);
        return response()->json(['ok' => true, 'pages' => $pages]);
    }

    /**
     * POST /instructor/web-page/pages-reorder — bulk reorder pages.
     */
    public function reorderPages(Request $request)
    {
        if (! $this->permitted()) return response()->json(['ok' => false], 403);
        $ids = $request->input('order', []);
        if (! is_array($ids)) return response()->json(['ok' => false], 422);
        $coachId = $this->coachId();
        \DB::transaction(function () use ($ids, $coachId) {
            $i = 0;
            foreach ($ids as $id) {
                \App\Models\CoachPage::where('coach_id', $coachId)
                    ->where('id', (int) $id)
                    ->update(['sort_order' => $i++]);
            }
        });
        return response()->json(['ok' => true]);
    }

    // ---------------- Helpers ----------------

    private function permitted(): bool
    {
        return checkPermission('landing-page-builder') == 1;
    }

    /**
     * 2026-07-08 — course list for the Featured Courses Carousel picker.
     * Returns ONLY the current coach's own published courses (tenant-safe:
     * scoped to instructor_id + approved + active), searchable by title.
     */
    public function coursesForPicker(Request $request)
    {
        if (! $this->permitted()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $coachId = $this->coachId();
        $q = trim((string) $request->query('q', ''));

        $courses = \App\Models\Course::query()
            ->where('instructor_id', $coachId)
            ->where('coach_soft_delete', 0)
            ->where('is_approved', 'approved')
            ->where('status', 'active')
            ->when($q !== '', fn ($w) => $w->where('title', 'like', '%' . $q . '%'))
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'title', 'slug', 'thumbnail', 'type']);

        $data = $courses->map(fn ($c) => [
            'id'    => (int) $c->id,
            'title' => (string) $c->title,
            'thumb' => $c->thumbnail
                ? (\Illuminate\Support\Str::startsWith($c->thumbnail, ['http://', 'https://']) ? $c->thumbnail : asset($c->thumbnail))
                : null,
            'mode'  => $this->courseModeLabel($c->type),
        ]);

        return response()->json(['courses' => $data]);
    }

    private function courseModeLabel(?string $type): string
    {
        return match ((string) $type) {
            'live'    => 'Live',
            'hybrid'  => 'Hybrid',
            'webinar' => 'Webinar',
            default   => 'Recorded',
        };
    }

    private function coachId(): int
    {
        return (int) (userAuth()->role === 'instructor' ? userAuth()->id : userAuth()->coach_id);
    }

    private function coachUser(): ?User
    {
        return User::find($this->coachId());
    }

    private function findOwnedPage(int $id): CoachPage
    {
        return CoachPage::where('coach_id', $this->coachId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function findOwnedSection(int $id): CoachPageSection
    {
        $section = CoachPageSection::findOrFail($id);
        // The section must belong to a page owned by this coach
        $page = CoachPage::where('id', $section->coach_page_id)
            ->where('coach_id', $this->coachId())
            ->firstOrFail();
        $section->setRelation('page', $page);
        return $section;
    }
}
