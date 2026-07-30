<?php

namespace App\Services\Site;

use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\User;
use App\Models\YoutubeCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

/**
 * Renders a CoachPage's ordered sections into a single HTML body.
 *
 * Responsibilities:
 *  - Map section_type to its Blade partial (resources/views/frontend/coach-site/sections/{type}.blade.php)
 *  - Resolve external data each section needs (e.g. YouTube videos for youtube_v1)
 *  - Cache the rendered output per page version so subsequent visits avoid the per-section render cost
 *
 * The renderer NEVER duplicates brand data — colors/logo/support email
 * come from BrandResolver and are injected once at the master layout.
 */
class SectionRenderer
{
    public function __construct(
        private readonly YouTubeFetcher $youtube,
    ) {
    }

    /**
     * Render all sections of $page in order, returning concatenated HTML.
     * The caller wraps this in the master layout (which already has the
     * brand CSS variables and JSON-LD).
     */
    public function renderSections(CoachPage $page, ?User $coach): string
    {
        $sections = $page->sections; // already eager-loaded + ordered
        // F46 (audit 2026-06-26) — resolve the brand ONCE per page render instead
        // of per-section (renderOne previously called BrandResolver::current() for
        // every block). (Full rendered-HTML caching is intentionally NOT done here:
        // sections embed per-request @csrf tokens + csp_nonce(), so caching the
        // output would break forms + CSP — a static/dynamic split is needed first.)
        $brand = app(\App\Services\BrandResolver::class)->current();
        $html = '';
        foreach ($sections as $section) {
            // Skip hidden sections in public render (audit 2026-05-25 evening)
            if (isset($section->is_visible) && ! $section->is_visible) {
                continue;
            }
            $html .= $this->renderOne($section, $page, $coach, $brand);
        }
        return $html;
    }

    /**
     * Render a single section. Public so the editor preview can call it
     * to live-preview a single block without re-rendering the whole page.
     */
    public function renderOne(CoachPageSection $section, CoachPage $page, ?User $coach, $brand = null): string
    {
        $type = $section->section_type;
        $view = "frontend.coach-site.sections.{$type}";

        if (! view()->exists($view)) {
            return '';  // unknown / dropped section type — fail silently
        }

        $content = $section->content_json ?? [];
        // Owner-only diagnostics: partials use this to surface helpful
        // empty-state hints (e.g. "no YouTube API key configured") that
        // students should never see. True only when the logged-in user
        // owns this coach site. Editor preview iframe goes through the
        // same controller so this naturally lights up there.
        $isOwner = auth()->check() && $coach && auth()->id() === $coach->id;

        $data = [
            'content'         => $content,
            'section'         => $section,
            'sectionId'       => $section->id,
            'page'            => $page,
            'coach'           => $coach,
            'isOwnerPreview'  => $isOwner,
            'brand'           => $brand ?? app(\App\Services\BrandResolver::class)->current(), // F46 — reuse hoisted brand
            // Per-section appearance overrides — bg color, text color,
            // gradient, alignment — built from `_preset` + custom fields.
            'appearanceStyle' => SectionRegistry::buildStyle($content),
        ];

        // Section-specific external data resolution
        if ($type === 'youtube_v1') {
            $data['youtubeVideos']   = $this->resolveYouTube(
                $section->content_json['channel_id'] ?? null,
                $coach
            );
            // Owner-side empty-state diagnostic: why is the list empty?
            $data['youtubeDiagnostic'] = $this->youtubeDiagnostic(
                $section->content_json['channel_id'] ?? null,
                $coach,
                $data['youtubeVideos']
            );
        }

        // 2026-06-18 — Blog block. 2026-06-23: on a coach site it now shows the
        // COACH'S OWN published posts (tenant-filtered), per the Blog Management
        // spec. Falls back to platform blogs only if no coach is resolved.
        if ($type === 'blog_v1') {
            $count = max(1, min(12, (int) ($section->content_json['count'] ?? 3)));
            $data['blogPosts'] = $coach
                ? $this->resolveCoachBlogs((int) $coach->id, $count)
                : $this->resolveBlogs($count);
        }

        $body = view($view, $data)->render();

        // 2026-06-23 — per-section font-size overrides. If the coach set any
        // _fs_* values on this section, tag the section's root element and
        // prepend a scoped <style> so the override applies to THIS section only
        // (never the rest of the site, never other coaches).
        $sectionCss = SiteSettingsService::buildSectionTypographyCss($content, (int) $section->id);
        if ($sectionCss !== '') {
            // Add the data attribute to the section's root tag (section/footer/div).
            $tagged = preg_replace(
                '/<(section|footer|div)\b/',
                '<$1 data-cs-sec="' . (int) $section->id . '"',
                $body,
                1
            );
            if ($tagged !== null && $tagged !== $body) {
                $nonce = function_exists('csp_nonce') ? csp_nonce() : '';
                $body = '<style' . ($nonce ? " nonce=\"{$nonce}\"" : '') . ">{$sectionCss}</style>" . $tagged;
            }
        }

        return $body;
    }

    /**
     * Latest N PUBLISHED posts owned by THIS coach (tenant-filtered) for the
     * blog_v1 section. Guarded so a missing table never breaks the render.
     */
    public function resolveCoachBlogs(int $coachId, int $count)
    {
        try {
            return \App\Models\CoachBlog::forCoach($coachId)
                ->published()
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit($count)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Latest N PUBLISHED platform blogs for the blog_v1 section. Guarded so a
     * missing Blog module / table never breaks the page render (returns empty).
     */
    public function resolveBlogs(int $count)
    {
        try {
            return \Modules\Blog\app\Models\Blog::query()
                ->where('status', 1)
                ->whereHas('category', fn ($q) => $q->where('status', 1))
                ->with('translation')
                ->orderByDesc('created_at')
                ->limit($count)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Cached 24h-TTL YouTube fetch. Channel ID falls back to the coach's
     * YoutubeCredential row if blank in the section content.
     *
     * Behavior on failure (quota / API down / network):
     *   - Return last successful cache (stale-while-revalidate semantics)
     *   - If no cache exists, return [] so the partial renders "Videos coming soon"
     */
    public function resolveYouTube(?string $channelIdOverride, ?User $coach): array
    {
        $channelId = $channelIdOverride;
        if (! $channelId && $coach) {
            $cred = YoutubeCredential::where('instructor_id', $coach->id)->first();
            if ($cred) {
                $channelId = $cred->channel_id;
            }
        }
        if (! $channelId) {
            return [];
        }

        return $this->youtube->latestVideos($channelId, $coach?->id);
    }

    /**
     * Owner-facing reason why the YouTube fetch returned no videos.
     * Students never see this — the partials gate it behind $isOwnerPreview.
     *
     * Returns null when the list is non-empty (no diagnostic needed).
     */
    public function youtubeDiagnostic(?string $channelIdOverride, ?User $coach, array $videos): ?string
    {
        if (! empty($videos)) {
            return null;
        }

        $channelId = $channelIdOverride;
        if (! $channelId && $coach) {
            $cred = YoutubeCredential::where('instructor_id', $coach->id)->first();
            $channelId = $cred?->channel_id;
        }
        if (! $channelId) {
            return __('No YouTube channel ID. Paste your channel ID into the section editor, or save one in YouTube Credentials.');
        }

        // Channel ID present — must be an API-key or quota issue.
        $hasCoachKey = $coach
            ? (bool) optional(YoutubeCredential::where('instructor_id', $coach->id)->first())->api_key
            : false;
        $hasPlatformKey = ! empty(config('services.youtube.api_key'));

        if (! $hasCoachKey && ! $hasPlatformKey) {
            return __('No YouTube API key configured. Add one in YouTube Credentials (Settings → YouTube), or ask the platform operator to set YOUTUBE_API_KEY in the .env.');
        }

        // Key + channel both present, fetch still empty — quota, bad channel ID,
        // private channel, or the channel literally has no public videos.
        return __('Fetched the channel but got zero videos. Double-check the channel ID (starts with UC...) and that the channel has public uploads. If everything looks right, the API may have hit its daily quota — videos return tomorrow.');
    }
}
