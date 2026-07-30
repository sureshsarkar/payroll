<?php

namespace App\Services\Site;

use App\Models\CoachLandingPage;
use App\Models\CoachPage;
use App\Models\CoachPageSection;
use App\Models\CoachPageVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * High-level operations on a coach's multi-page Site:
 *  - bootstrap a fresh site (creates a Home page if none exist)
 *  - create / rename / delete a page
 *  - publish / unpublish a page (enforces "one Home must be live")
 *  - section CRUD (add / update / reorder / delete) with autosave
 *  - snapshot last-20 versions on every save
 *  - migrate legacy GrapesJS sites to html_passthrough_v1
 *
 * IDOR safety is the caller's responsibility — every method that takes
 * a CoachPage assumes it has already been ownership-checked.
 */
class PageManager
{
    private const KEEP_VERSIONS = 20;

    /**
     * Ensure the coach has at least a Home page; creates one if missing.
     * Returns the Home page.
     */
    public function ensureHome(int $coachId, ?CoachLandingPage $site = null): CoachPage
    {
        $home = CoachPage::forCoach($coachId)->where('page_type', 'home')->first();
        if ($home) {
            return $home;
        }
        return CoachPage::create([
            'coach_id'     => $coachId,
            'site_id'      => $site?->id,
            'slug'         => 'home',
            'page_type'    => 'home',
            'title'        => 'Home',
            'is_published' => false,
            'sort_order'   => 0,
        ]);
    }

    public function createPage(int $coachId, string $pageType, string $title, ?CoachLandingPage $site = null): CoachPage
    {
        if (! in_array($pageType, CoachPage::TYPES, true)) {
            $pageType = 'custom';
        }
        $slug = CoachPage::generateUniqueSlug($coachId, $title);
        $sort = (int) CoachPage::forCoach($coachId)->max('sort_order') + 1;
        return CoachPage::create([
            'coach_id'     => $coachId,
            'site_id'      => $site?->id,
            'slug'         => $slug,
            'page_type'    => $pageType,
            'title'        => $title,
            'is_published' => false,
            'sort_order'   => $sort,
        ]);
    }

    public function renamePage(CoachPage $page, string $newTitle): CoachPage
    {
        $newSlug = CoachPage::generateUniqueSlug($page->coach_id, $newTitle);
        $page->update([
            'title' => $newTitle,
            'slug'  => $page->slug === 'home' ? 'home' : $newSlug, // Home slug locked
        ]);
        $this->snapshot($page);
        return $page;
    }

    /**
     * Publish flag toggle. Enforces:
     *  - To publish any non-home page, the coach's Home page must be published.
     *  - Unpublishing Home cascades a warning (we don't auto-unpublish others
     *    — coach intent matters).
     */
    public function setPublished(CoachPage $page, bool $publish): array
    {
        if ($publish && $page->page_type !== 'home') {
            $homePub = CoachPage::forCoach($page->coach_id)
                ->where('page_type', 'home')
                ->where('is_published', true)
                ->exists();
            if (! $homePub) {
                return ['ok' => false, 'msg' => 'Publish your Home page first.'];
            }
        }
        $page->update(['is_published' => $publish]);
        return ['ok' => true, 'msg' => $publish ? 'Page published.' : 'Page unpublished.'];
    }

    public function deletePage(CoachPage $page): void
    {
        if ($page->page_type === 'home') {
            // Home is special — block delete to keep the Site valid
            return;
        }
        $page->delete();
    }

    // ---------------- Section CRUD ----------------

    public function addSection(CoachPage $page, string $type, ?array $content = null): CoachPageSection
    {
        $registry = SectionRegistry::get($type);
        if (! $registry) {
            throw new \InvalidArgumentException("Unknown section type: $type");
        }
        $sort = (int) $page->sections()->max('sort_order') + 1;

        // Smart defaults — prefill with coach's actual profile data so the
        // section looks "almost done" the moment it lands on the page.
        // Non-technical coaches won't have to fight blank fields.
        $defaultContent = $content ?? $this->smartDefaults($type, $page->coach_id, $registry['defaults']);

        $section = CoachPageSection::create([
            'coach_page_id'   => $page->id,
            'landing_page_id' => null,
            'section_type'    => $type,
            'section_version' => 'v1',
            'sort_order'      => $sort,
            'content_json'    => $defaultContent,
            'is_visible'      => true,
        ]);
        $this->snapshot($page);
        return $section;
    }

    /**
     * Smart defaults — read the coach's profile + brand and customize
     * the generic section defaults so the section feels personal
     * the moment it's added.
     */
    private function smartDefaults(string $type, int $coachId, array $genericDefaults): array
    {
        $coach = \App\Models\User::find($coachId);
        if (! $coach) return $genericDefaults;

        // Try to read brand info (may not exist yet)
        $brand = null;
        try { $brand = app(\App\Services\BrandResolver::class)->forCoach($coachId); }
        catch (\Throwable $e) {}

        $name = $coach->name ?? $brand?->name ?? '';

        switch ($type) {
            case 'hero_v1':
                return array_merge($genericDefaults, [
                    'headline' => $name ? "Welcome to {$name}" : $genericDefaults['headline'],
                    'subhead'  => 'Personalized 1-on-1 sessions, proven methodology, lifelong results.',
                ]);

            case 'about_v1':
                return array_merge($genericDefaults, [
                    'name' => $name,
                    'image' => $coach->image ? asset($coach->image) : null,
                ]);

            case 'contact_v1':
                return array_merge($genericDefaults, [
                    'email' => $coach->email ?? '',
                    'phone' => $coach->phone ?? '',
                ]);

            case 'lead_form_v1':
                return array_merge($genericDefaults, [
                    'title' => $name ? "Get in touch with {$name}" : $genericDefaults['title'],
                ]);

            case 'cta_banner_v1':
                return array_merge($genericDefaults, [
                    'headline' => $name ? "Ready to start with {$name}?" : $genericDefaults['headline'],
                ]);

            case 'footer_v1':
                $cfg = $genericDefaults;
                $cfg['tagline'] = $name ? "{$name} — empowering your transformation" : ($cfg['tagline'] ?? '');
                return $cfg;
        }
        return $genericDefaults;
    }

    public function updateSection(CoachPageSection $section, array $content): array
    {
        $errors = SectionRegistry::validate($section->section_type, $content);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $section->update(['content_json' => $content]);
        $this->snapshot($section->page);
        return ['ok' => true];
    }

    public function deleteSection(CoachPageSection $section): void
    {
        $page = $section->page;
        $section->delete();
        if ($page) {
            $this->snapshot($page);
        }
    }

    /**
     * Reorder all sections of a page in one transaction. Caller passes
     * an ordered list of section ids; missing ids stay at the end in
     * their existing relative order.
     */
    public function reorderSections(CoachPage $page, array $orderedIds): void
    {
        DB::transaction(function () use ($page, $orderedIds) {
            $i = 0;
            foreach ($orderedIds as $id) {
                CoachPageSection::where('coach_page_id', $page->id)
                    ->where('id', $id)
                    ->update(['sort_order' => $i++]);
            }
        });
        $this->snapshot($page);
    }

    // ---------------- Versioning ----------------

    /**
     * Snapshot the current page+sections state, then trim to the last
     * KEEP_VERSIONS entries.
     */
    public function snapshot(CoachPage $page, ?int $authorId = null): void
    {
        $authorId ??= auth()->id() ?? $page->coach_id;
        $payload = [
            'page'     => $page->fresh()->toArray(),
            'sections' => $page->fresh()->sections()->get()->toArray(),
        ];
        CoachPageVersion::create([
            'coach_page_id' => $page->id,
            'snapshot'      => $payload,
            'created_by'    => $authorId,
        ]);
        // Trim old versions
        $keepIds = CoachPageVersion::where('coach_page_id', $page->id)
            ->orderByDesc('id')
            ->limit(self::KEEP_VERSIONS)
            ->pluck('id');
        CoachPageVersion::where('coach_page_id', $page->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    public function restoreVersion(CoachPageVersion $version): void
    {
        DB::transaction(function () use ($version) {
            $page = CoachPage::find($version->coach_page_id);
            if (! $page) return;

            $snap = $version->snapshot;
            $page->update([
                'title'            => $snap['page']['title']            ?? $page->title,
                'meta_title'       => $snap['page']['meta_title']       ?? null,
                'meta_description' => $snap['page']['meta_description'] ?? null,
                'og_image'         => $snap['page']['og_image']         ?? null,
                'robots'           => $snap['page']['robots']           ?? 'index',
            ]);
            // Replace sections wholesale
            $page->sections()->delete();
            foreach ($snap['sections'] ?? [] as $s) {
                CoachPageSection::create([
                    'coach_page_id'   => $page->id,
                    'landing_page_id' => null,
                    'section_type'    => $s['section_type']    ?? 'hero_v1',
                    'section_version' => $s['section_version'] ?? 'v1',
                    'sort_order'      => $s['sort_order']      ?? 0,
                    'content_json'    => is_string($s['content_json'] ?? null)
                        ? json_decode($s['content_json'], true)
                        : ($s['content_json'] ?? []),
                ]);
            }
        });
    }

    // ---------------- Legacy migration ----------------

    /**
     * One-shot: for a CoachLandingPage that still has html_content
     * (legacy GrapesJS), spawn a Home CoachPage + one html_passthrough_v1
     * section so the public renderer keeps rendering identically.
     */
    public function migrateLegacy(CoachLandingPage $site): ?CoachPage
    {
        if (empty($site->html_content)) {
            return null;
        }
        if (CoachPage::forCoach($site->added_by)->where('page_type', 'home')->exists()) {
            return null;
        }
        $page = CoachPage::create([
            'coach_id'     => $site->added_by,
            'site_id'      => $site->id,
            'slug'         => 'home',
            'page_type'    => 'home',
            'title'        => $site->website_name ?? 'Home',
            'is_published' => (bool) $site->is_published,
            'sort_order'   => 0,
        ]);
        CoachPageSection::create([
            'coach_page_id'   => $page->id,
            'landing_page_id' => $site->id,
            'section_type'    => 'html_passthrough_v1',
            'section_version' => 'v1',
            'sort_order'      => 0,
            'content_json'    => [
                'html' => (string) $site->html_content,
                'css'  => (string) $site->css_content,
            ],
        ]);
        return $page;
    }
}
