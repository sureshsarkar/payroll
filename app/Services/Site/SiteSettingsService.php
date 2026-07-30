<?php

namespace App\Services\Site;

use App\Models\CoachSiteSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write the per-coach site-wide settings row.
 *
 * Cached for 60s under "coach_site_settings:{coach_id}" so the public
 * renderer (which runs on every public page-view) doesn't hit DB
 * repeatedly. Cache busts on update().
 */
class SiteSettingsService
{
    private const TTL = 60;

    /**
     * 2026-06-23 — site-wide Typography. Each role maps to the public-site
     * CSS selectors its font-size should drive. Coaches set a px value per
     * role per device; only values they set are emitted (others fall back to
     * the theme defaults in coach-site.css) — so this is purely additive.
     */
    public const TYPOGRAPHY_ROLES = [
        'headings'     => ['label' => 'Headings / Section titles', 'selectors' => '.cs-h1, .cs-h2, .cs-hero__title, .cs-section-head .cs-h2'],
        'subheadings'  => ['label' => 'Subheadings / Card titles', 'selectors' => '.cs-h3, .cs-card__title, .cs-gallery__title'],
        'lead'         => ['label' => 'Intro / Lead text',         'selectors' => '.cs-lead, .cs-section-head .cs-lead'],
        'body'         => ['label' => 'Body & paragraph text',     'selectors' => '.cs-main p, .cs-card__desc, .cs-gallery__desc, .cs-prose, .cs-rich-text, .cs-about__text'],
        'buttons'      => ['label' => 'Buttons',                   'selectors' => '.cs-btn'],
        'testimonials' => ['label' => 'Testimonials',              'selectors' => '.cs-quote__text, .cs-quote__author'],
        'faq'          => ['label' => 'FAQ content',               'selectors' => '.cs-acc__q, .cs-acc__a'],
        'course_titles'=> ['label' => 'Course titles',            'selectors' => '.cs-rec__title'],
        'course_desc'  => ['label' => 'Course descriptions',      'selectors' => '.cs-rec__body, .cs-rec__meta'],
        'footer'       => ['label' => 'Footer text',               'selectors' => '.cs-footer, .cs-footer__links a, .cs-footer__h'],
    ];

    /**
     * Per-section font-size override roles → the selectors they drive WITHIN a
     * single section (scoped under [data-cs-sec="{id}"] by buildSectionTypographyCss).
     * Lets a coach bump one section's text without touching the global defaults.
     */
    public const SECTION_TYPOGRAPHY_ROLES = [
        '_fs_heading'    => ['label' => 'Override heading size (px)',    'selectors' => '.cs-h1, .cs-h2, .cs-h3, .cs-hero__title'],
        '_fs_subheading' => ['label' => 'Override card-title size (px)', 'selectors' => '.cs-card__title, .cs-gallery__title, .cs-rec__title'],
        '_fs_text'       => ['label' => 'Override body text size (px)',  'selectors' => 'p, .cs-lead, .cs-card__desc, .cs-gallery__desc, .cs-quote__text, .cs-acc__a, .cs-rec__body, .cs-prose'],
        '_fs_button'     => ['label' => 'Override button text size (px)','selectors' => '.cs-btn'],
    ];

    /** Breakpoint for each device tier (desktop = base, no media query). */
    private const TYPOGRAPHY_DEVICES = [
        'desktop' => null,
        'tablet'  => '(max-width: 1024px)',
        'mobile'  => '(max-width: 640px)',
    ];

    /**
     * Get the settings row for a coach. Auto-creates default row if missing.
     */
    public function for(int $coachId): CoachSiteSettings
    {
        return Cache::remember("coach_site_settings:{$coachId}", self::TTL, function () use ($coachId) {
            return CoachSiteSettings::firstOrCreate(['coach_id' => $coachId]);
        });
    }

    /**
     * Update (or create) the settings row. Bust cache.
     */
    public function update(int $coachId, array $data): CoachSiteSettings
    {
        $row = CoachSiteSettings::firstOrCreate(['coach_id' => $coachId]);
        // Whitelist columns to prevent mass-assignment of unrelated fields
        $allowed = (new CoachSiteSettings)->getFillable();
        $clean = array_intersect_key($data, array_flip($allowed));
        $row->update($clean);
        Cache::forget("coach_site_settings:{$coachId}");
        return $row->fresh();
    }

    /**
     * Build the site-wide typography CSS (inner CSS only — caller wraps it in
     * a nonce'd <style>). Emits a font-size rule per role only for the values
     * the coach actually set, grouped by device media query. Values are clamped
     * to a sane range; non-numeric/blank values are skipped. Uses !important so
     * the coach's explicit choice wins over theme defaults. Returns '' when
     * nothing is configured (so no <style> is emitted at all).
     */
    public function typographyCss(?CoachSiteSettings $settings): string
    {
        $cfg = $settings?->typography_config;
        if (! is_array($cfg) || empty($cfg)) {
            return '';
        }

        // Collect rules per device tier.
        $byDevice = ['desktop' => [], 'tablet' => [], 'mobile' => []];
        foreach (self::TYPOGRAPHY_ROLES as $role => $meta) {
            foreach (array_keys(self::TYPOGRAPHY_DEVICES) as $device) {
                $raw = $cfg["{$role}_{$device}"] ?? null;
                if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                    continue;
                }
                $px = max(8, min(120, (int) $raw)); // clamp out hostile/typo values
                $byDevice[$device][] = "{$meta['selectors']} { font-size: {$px}px !important; }";
            }
        }

        $css = '';
        foreach (self::TYPOGRAPHY_DEVICES as $device => $media) {
            if (empty($byDevice[$device])) {
                continue;
            }
            $rules = implode("\n", $byDevice[$device]);
            $css .= $media ? "@media {$media} {\n{$rules}\n}\n" : "{$rules}\n";
        }

        return trim($css);
    }

    /**
     * Per-section font-size overrides. Returns scoped CSS (rules prefixed with
     * [data-cs-sec="{id}"]) for any _fs_* values set on the section's content,
     * or '' when none. Lets a coach override font sizes for ONE section without
     * affecting the rest of the site or other coaches. Static so it can be
     * called from the renderer without resolving the service.
     */
    public static function buildSectionTypographyCss(array $content, int $sectionId): string
    {
        if ($sectionId <= 0) {
            return '';
        }
        $rules = [];
        foreach (self::SECTION_TYPOGRAPHY_ROLES as $key => $meta) {
            $raw = $content[$key] ?? null;
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                continue;
            }
            $px = max(8, min(120, (int) $raw));
            $scoped = implode(', ', array_map(
                fn ($sel) => "[data-cs-sec=\"{$sectionId}\"] {$sel}",
                array_map('trim', explode(',', $meta['selectors']))
            ));
            $rules[] = "{$scoped} { font-size: {$px}px !important; }";
        }
        return implode("\n", $rules);
    }

    /**
     * Build the WhatsApp click-to-chat URL from the configured number + message.
     */
    public function whatsappUrl(CoachSiteSettings $settings): ?string
    {
        if (! $settings->whatsapp_enabled || ! $settings->whatsapp_number) {
            return null;
        }
        $num = preg_replace('/[^0-9]/', '', $settings->whatsapp_number);
        $msg = trim((string) $settings->whatsapp_message);
        $url = "https://wa.me/{$num}";
        if ($msg !== '') {
            // rawurlencode produces %20 for spaces (RFC 3986) — preferred by WhatsApp's wa.me
            $url .= '?text=' . rawurlencode($msg);
        }
        return $url;
    }
}
