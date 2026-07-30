<?php

namespace App\Services\Site;

/**
 * Central catalog of section types for the coach marketing-website builder.
 *
 * Each entry defines:
 *   - label     : human-readable name shown in the section drawer
 *   - icon      : Font Awesome 5 class (fa-solid fa-X, since the platform
 *                 is on FA5 not FA6)
 *   - category  : drawer grouping ('hero' | 'content' | 'trust' |
 *                 'conversion' | 'media' | 'footer')
 *   - schema    : field definitions for the editor form (light JSON-schema
 *                 dialect, see validate())
 *   - defaults  : seed content_json when the coach adds a fresh section
 *
 * Section types are versioned (_v1, _v2, …). A coach's existing site
 * continues rendering with the old version even if a new version ships.
 *
 * Single source of truth — referenced by:
 *   - SectionRenderer  (picks Blade partial from section_type)
 *   - CoachSiteController (validates content_json on save)
 *   - Editor view  (renders the section drawer + form panels)
 */
class SectionRegistry
{
    /**
     * Return the full catalog. Cached at the class level for the request
     * lifetime (it's a pure-PHP array, no I/O).
     */
    /**
     * Common appearance schema appended to every section.
     * Coach can override colors / alignment per section ("section-wise")
     * without touching global brand settings.
     *
     * `_preset` chooses a color theme. `custom` exposes the raw color
     * pickers; other presets ignore them.
     */
    public static function appearanceFields(): array
    {
        return [
            '_preset'      => ['type' => 'enum',  'options' => ['brand', 'light', 'soft', 'dark', 'custom'],
                               'default' => 'brand', 'label' => 'Color theme'],
            '_bg_color'    => ['type' => 'color', 'label' => 'Background color'],
            '_bg_color_2'  => ['type' => 'color', 'label' => 'Second color (for gradient)'],
            '_text_color'  => ['type' => 'color', 'label' => 'Text color'],
            '_text_align'  => ['type' => 'enum',  'options' => ['inherit', 'left', 'center', 'right'],
                               'default' => 'inherit', 'label' => 'Text alignment'],
            '_font_family' => ['type' => 'enum',  'options' => array_keys(static::FONT_LIBRARY),
                               'default' => 'inherit', 'label' => 'Font family'],
            // 2026-06-23 — per-section font-size overrides (Typography feature,
            // "override font sizes for specific sections if needed"). Blank =
            // use the global/theme size. Applied scoped to this section only by
            // SectionRenderer via SiteSettingsService::buildSectionTypographyCss.
            '_fs_heading'    => ['type' => 'int', 'min' => 8, 'max' => 120, 'label' => 'Heading size override (px)'],
            '_fs_subheading' => ['type' => 'int', 'min' => 8, 'max' => 120, 'label' => 'Card/sub-title size override (px)'],
            '_fs_text'       => ['type' => 'int', 'min' => 8, 'max' => 120, 'label' => 'Body text size override (px)'],
            '_fs_button'     => ['type' => 'int', 'min' => 8, 'max' => 120, 'label' => 'Button text size override (px)'],
        ];
    }

    /**
     * Curated library of web-safe Google Fonts. Coach picks one from the
     * dropdown per section; the master layout loads only the fonts actually
     * in use across the current page (no unused font CSS pulled in).
     *
     * Key = label shown in the dropdown.
     * Value = ['stack' => css font-family value, 'google' => Google Fonts
     *          link param, or null when no remote load needed]
     */
    public const FONT_LIBRARY = [
        'inherit'           => ['stack' => null,                                       'google' => null],
        'Inter (default)'   => ['stack' => "'Inter', system-ui, sans-serif",          'google' => 'Inter:wght@400;500;600;700;800'],
        'Plus Jakarta Sans' => ['stack' => "'Plus Jakarta Sans', system-ui, sans-serif",'google' => 'Plus+Jakarta+Sans:wght@500;600;700;800'],
        'Poppins'           => ['stack' => "'Poppins', sans-serif",                   'google' => 'Poppins:wght@400;500;600;700;800'],
        'Montserrat'        => ['stack' => "'Montserrat', sans-serif",                'google' => 'Montserrat:wght@400;500;600;700;800'],
        'Roboto'            => ['stack' => "'Roboto', sans-serif",                    'google' => 'Roboto:wght@400;500;700;900'],
        'Open Sans'         => ['stack' => "'Open Sans', sans-serif",                 'google' => 'Open+Sans:wght@400;500;600;700;800'],
        'Lato'              => ['stack' => "'Lato', sans-serif",                      'google' => 'Lato:wght@400;700;900'],
        'Raleway'           => ['stack' => "'Raleway', sans-serif",                   'google' => 'Raleway:wght@400;500;600;700;800'],
        'Nunito'            => ['stack' => "'Nunito', sans-serif",                    'google' => 'Nunito:wght@400;500;600;700;800'],
        'Playfair Display'  => ['stack' => "'Playfair Display', Georgia, serif",     'google' => 'Playfair+Display:wght@400;500;600;700;800;900'],
        'Merriweather'      => ['stack' => "'Merriweather', Georgia, serif",         'google' => 'Merriweather:wght@400;700;900'],
        'Oswald'            => ['stack' => "'Oswald', sans-serif",                    'google' => 'Oswald:wght@400;500;600;700'],
        'Bebas Neue'        => ['stack' => "'Bebas Neue', sans-serif",                'google' => 'Bebas+Neue'],
        'DM Sans'           => ['stack' => "'DM Sans', sans-serif",                   'google' => 'DM+Sans:wght@400;500;700;800'],
    ];

    /**
     * Return the CSS font-family stack for a label, or null if 'inherit' / unknown.
     */
    public static function fontStack(?string $label): ?string
    {
        if (! $label || $label === 'inherit') return null;
        return self::FONT_LIBRARY[$label]['stack'] ?? null;
    }

    /**
     * Return the Google Fonts URL parameter for a label (e.g. "Poppins:wght@400;700"),
     * or null when the font doesn't need remote loading.
     */
    public static function fontGoogleSlug(?string $label): ?string
    {
        if (! $label || $label === 'inherit') return null;
        return self::FONT_LIBRARY[$label]['google'] ?? null;
    }

    /**
     * Build a `style="..."` value from a section's appearance settings.
     * Uses CSS variable overrides (--brand-*) so child elements that
     * reference these variables auto-inherit the new colors.
     *
     * Returns empty string when the section uses the default 'brand' preset.
     */
    public static function buildStyle(array $content): string
    {
        $preset = $content['_preset']    ?? 'brand';
        $bg     = trim((string) ($content['_bg_color']   ?? ''));
        $bg2    = trim((string) ($content['_bg_color_2'] ?? ''));
        $text   = trim((string) ($content['_text_color'] ?? ''));
        $align  = $content['_text_align'] ?? 'inherit';

        // STEP 1 — start from preset's base palette.
        $presetBg = null; $presetText = null; $presetMuted = null;
        switch ($preset) {
            case 'light': $presetBg = '#FFFFFF'; $presetText = '#0F172A'; break;
            case 'soft':  $presetBg = '#F8FAFC'; $presetText = '#0F172A'; break;
            case 'dark':  $presetBg = '#0F172A'; $presetText = '#FFFFFF'; $presetMuted = '#CBD5E1'; break;
            // 'brand' and 'custom' don't pre-fill — coach's explicit picks
            // win on top, otherwise global CSS variables stay in effect.
        }

        // STEP 2 — overlay explicit picks. Custom colors ALWAYS win, no
        // matter which preset is selected. This is the principle of
        // least surprise: if the coach picked a color, that color
        // should appear.
        $finalBg    = $bg    !== '' ? $bg    : $presetBg;
        $finalBg2   = $bg2   !== '' ? $bg2   : null;
        $finalText  = $text  !== '' ? $text  : $presetText;
        $finalMuted = $text  !== '' ? $text  : $presetMuted;   // muted defaults to text when overridden

        $parts = [];
        if ($finalBg !== null && $finalBg2 !== null) {
            $parts[] = "background: linear-gradient(135deg, {$finalBg} 0%, {$finalBg2} 100%)";
        } elseif ($finalBg !== null) {
            $parts[] = "background: {$finalBg}";
        }
        if ($finalText !== null) {
            $parts[] = "color: {$finalText}";
            $parts[] = "--brand-text: {$finalText}";
            $parts[] = "--brand-muted: " . ($finalMuted ?: $finalText);
        }
        if ($align !== 'inherit' && $align !== '') {
            $parts[] = "text-align: {$align}";
        }
        // Font family (looked up from FONT_LIBRARY)
        $fontStack = self::fontStack($content['_font_family'] ?? null);
        if ($fontStack) {
            $parts[] = "font-family: {$fontStack}";
        }
        return implode('; ', array_filter($parts));
    }

    /**
     * Collect Google Fonts URL slugs from all sections on a page so the
     * master layout can load ONLY the fonts actually in use (skip unused).
     * Called by CoachSitePublicController during render.
     *
     * @param iterable $sections
     * @return string[]  unique google slugs like ["Poppins:wght@400;700", …]
     */
    public static function collectFontSlugs(iterable $sections): array
    {
        $slugs = [];
        foreach ($sections as $section) {
            $label = $section->content_json['_font_family'] ?? null;
            $slug = self::fontGoogleSlug($label);
            if ($slug) $slugs[$slug] = true;
        }
        return array_keys($slugs);
    }

    public static function all(): array
    {
        $catalog = static::baseCatalog();
        // Auto-append appearance fields to every section EXCEPT legacy passthrough
        foreach ($catalog as $code => &$def) {
            if ($code === 'html_passthrough_v1') continue;
            $def['schema'] = array_merge($def['schema'], static::appearanceFields());
            $def['defaults'] = array_merge($def['defaults'], [
                '_preset'      => 'brand',
                '_bg_color'    => '',
                '_bg_color_2'  => '',
                '_text_color'  => '',
                '_text_align'  => 'inherit',
                '_font_family' => 'inherit',
                '_fs_heading'    => '',
                '_fs_subheading' => '',
                '_fs_text'       => '',
                '_fs_button'     => '',
            ]);
        }
        unset($def);
        return $catalog;
    }

    /**
     * The raw section catalog — kept private so external callers always
     * see the appearance-augmented version via all().
     */
    protected static function baseCatalog(): array
    {
        return [
            // 2026-06-18 — Rich Text block (WYSIWYG via TinyMCE: font, size,
            // colour, alignment, formatting). Body is sanitized HTML on render.
            'rich_text_v1' => [
                'label'    => 'Rich Text',
                'icon'     => 'fa-solid fa-align-left',
                'category' => 'content',
                'schema'   => [
                    'title' => ['type' => 'string',  'max' => 120, 'required' => false, 'label' => 'Heading (optional)'],
                    'align' => ['type' => 'enum',    'options' => ['left', 'center', 'right'], 'default' => 'left', 'label' => 'Heading alignment'],
                    'body'  => ['type' => 'wysiwyg', 'max' => 20000, 'required' => true,  'label' => 'Content'],
                ],
                'defaults' => [
                    'title' => '',
                    'align' => 'left',
                    'body'  => '<p>Start writing your content here…</p>',
                ],
            ],

            // 2026-06-18 — Cards block. Each card: image / title / description /
            // button. Any empty field auto-hides on the live site (see blade).
            'cards_v1' => [
                'label'    => 'Cards',
                'icon'     => 'fa-solid fa-grip',
                'category' => 'content',
                'schema'   => [
                    'title'   => ['type' => 'string', 'max' => 120, 'required' => false, 'label' => 'Section title (optional)'],
                    'intro'   => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Intro text (optional)'],
                    'columns' => ['type' => 'enum',   'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columns'],
                    'cards'   => ['type' => 'list', 'item_type' => 'object', 'max_items' => 12, 'add_label' => 'Add card', 'label' => 'Cards', 'item_schema' => [
                        'image'       => ['type' => 'image',  'label' => 'Image (optional)'],
                        'title'       => ['type' => 'string', 'max' => 100, 'required' => false, 'label' => 'Card title'],
                        'description' => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Description'],
                        'btn_text'    => ['type' => 'string', 'max' => 40,  'required' => false, 'label' => 'Button text'],
                        'btn_url'     => ['type' => 'string', 'max' => 500, 'required' => false, 'label' => 'Button link (URL)'],
                        'btn_newtab'  => ['type' => 'bool',   'default' => false, 'label' => 'Open button in new tab'],
                        // 2026-06-24 — optional per-card social links (e.g. for
                        // trainer/team cards). Each icon shows ONLY when its URL
                        // is filled, so every card is consistent + white-label
                        // (each person's own links, never hardcoded).
                        'social_facebook'  => ['type' => 'string', 'max' => 300, 'required' => false, 'label' => 'Facebook URL (optional)'],
                        'social_instagram' => ['type' => 'string', 'max' => 300, 'required' => false, 'label' => 'Instagram URL (optional)'],
                        'social_twitter'   => ['type' => 'string', 'max' => 300, 'required' => false, 'label' => 'X / Twitter URL (optional)'],
                        'social_linkedin'  => ['type' => 'string', 'max' => 300, 'required' => false, 'label' => 'LinkedIn URL (optional)'],
                        'social_youtube'   => ['type' => 'string', 'max' => 300, 'required' => false, 'label' => 'YouTube URL (optional)'],
                    ]],
                ],
                'defaults' => [
                    'title'   => 'What we offer',
                    'intro'   => '',
                    'columns' => '3',
                    'cards'   => [],
                ],
            ],

            // 2026-06-18 — Blog block. Shows the latest N published platform
            // blogs as clickable cards → blog detail opens in a new tab.
            'blog_v1' => [
                'label'    => 'Blog Posts',
                'icon'     => 'fa-solid fa-newspaper',
                'category' => 'content',
                'schema'   => [
                    'title'   => ['type' => 'string', 'max' => 120, 'required' => false, 'label' => 'Section title (optional)'],
                    'intro'   => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Intro text (optional)'],
                    'count'   => ['type' => 'int',    'min' => 1, 'max' => 12, 'default' => 3, 'label' => 'How many posts to show'],
                    'columns' => ['type' => 'enum',   'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columns'],
                ],
                'defaults' => [
                    'title'   => 'From our blog',
                    'intro'   => '',
                    'count'   => 3,
                    'columns' => '3',
                ],
            ],

            'hero_v1' => [
                'label'    => 'Hero',
                'icon'     => 'fa-solid fa-image',
                'category' => 'hero',
                'schema'   => [
                    'headline'           => ['type' => 'string', 'max' => 90,  'required' => true,  'label' => 'Headline'],
                    'subhead'            => ['type' => 'string', 'max' => 240, 'required' => false, 'label' => 'Subhead', 'multiline' => true],
                    'cta_text'           => ['type' => 'string', 'max' => 30,  'required' => false, 'label' => 'CTA button text'],
                    'cta_url'            => ['type' => 'string', 'max' => 500, 'required' => false, 'label' => 'CTA URL'],
                    'background_variant' => ['type' => 'enum',   'options' => ['image', 'gradient'], 'default' => 'gradient', 'label' => 'Background'],
                    'background_image'   => ['type' => 'image',  'required' => false, 'label' => 'Background image'],
                    'layout'             => ['type' => 'enum',   'options' => ['text-image-right', 'text-image-left', 'text-center'], 'default' => 'text-image-right', 'label' => 'Layout'],
                    'hero_image'         => ['type' => 'image',  'required' => false, 'label' => 'Hero image (for text+image layouts)'],
                ],
                'defaults' => [
                    'headline'           => 'Transform Your Life with Expert Coaching',
                    'subhead'            => 'Personalized 1-on-1 sessions, proven methodology, lifelong results.',
                    'cta_text'           => 'Get Started',
                    'cta_url'            => '#contact',
                    'background_variant' => 'gradient',
                    'layout'             => 'text-image-right',
                ],
            ],

            'about_v1' => [
                'label'    => 'About / Bio',
                'icon'     => 'fa-solid fa-user-tie',
                'category' => 'content',
                'schema'   => [
                    'image'           => ['type' => 'image',  'required' => false, 'label' => 'Your photo'],
                    'name'            => ['type' => 'string', 'max' => 100, 'required' => true,  'label' => 'Your name'],
                    'role'            => ['type' => 'string', 'max' => 100, 'required' => false, 'label' => 'Your title / role'],
                    'bio'             => ['type' => 'richtext', 'max' => 2000, 'required' => true, 'label' => 'About you'],
                    'credentials'     => ['type' => 'list', 'item_type' => 'string', 'max_items' => 10, 'label' => 'Credentials / certifications'],
                    // 2026-07-16 — image/content position. Default keeps the existing
                    // (image left) layout, so live sites are unchanged unless the coach opts in.
                    'layout_position' => ['type' => 'enum', 'options' => ['image_left', 'image_right'], 'default' => 'image_left', 'label' => 'Layout — photo position'],
                ],
                'defaults' => [
                    'name'            => '',
                    'role'            => 'Certified Coach',
                    'bio'             => 'Share your story — your background, your approach, what makes you different.',
                    'credentials'     => [],
                    'layout_position' => 'image_left',
                ],
            ],

            'recorded_courses_v1' => [
                'label'    => 'Recorded Courses',
                'icon'     => 'fa-solid fa-circle-play',
                'category' => 'conversion',
                'schema'   => [
                    'title'           => ['type' => 'string', 'max' => 100, 'default' => 'On-demand courses'],
                    'intro'           => ['type' => 'string', 'max' => 300, 'multiline' => true, 'label' => 'Intro text'],
                    // 2026-06-15 — choose which catalogue this section lists:
                    //   self_paced → recorded / on-demand courses (no batch)
                    //   live       → courses that run Live Classes (live + hybrid)
                    'course_type'     => ['type' => 'enum',   'options' => ['self_paced', 'live'],
                                          'default' => 'self_paced',
                                          'label' => 'Which courses to show — Self-paced (recorded) or Live-class courses'],
                    'source'          => ['type' => 'enum',   'options' => ['auto-from-courses', 'from-youtube'],
                                          'default' => 'auto-from-courses',
                                          'label' => 'Where to pull videos from'],
                    'youtube_channel_id' => ['type' => 'string', 'max' => 60, 'required' => false,
                                          'label' => 'YouTube channel ID (leave blank to use your coach profile)'],
                    'linked_course_slug' => ['type' => 'string', 'max' => 200, 'required' => false,
                                          'label' => 'Course slug that "Add to Cart" should add (e.g. yoga-bundle). Leave blank to auto-use your first course.'],
                    'preview_seconds' => ['type' => 'enum',   'options' => ['30', '60', '90', '120'], 'default' => '60',
                                          'label' => 'Free preview duration (seconds)'],
                    'columns'         => ['type' => 'enum',   'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columns'],
                    'limit'           => ['type' => 'enum',   'options' => ['3', '6', '9', '12'], 'default' => '6', 'label' => 'How many to show'],
                    'show_rating'     => ['type' => 'bool',   'default' => true, 'label' => 'Show ratings (courses mode)'],
                    'show_enrollment' => ['type' => 'bool',   'default' => true, 'label' => 'Show enrollment count (courses mode)'],
                    'cta_text'        => ['type' => 'string', 'max' => 30,  'default' => 'Add to cart', 'label' => 'CTA button text'],
                ],
                'defaults' => [
                    'title'              => 'On-demand courses',
                    'intro'              => 'Watch a free 1-minute preview. Love what you see? Add to cart and start learning.',
                    'course_type'        => 'self_paced',
                    'source'             => 'auto-from-courses',
                    'youtube_channel_id' => '',
                    'linked_course_slug' => '',
                    'preview_seconds'    => '60',
                    'columns'            => '3',
                    'limit'              => '6',
                    'show_rating'        => true,
                    'show_enrollment'    => true,
                    'cta_text'           => 'Add to cart',
                ],
            ],

            'services_grid_v1' => [
                'label'    => 'Services / Courses',
                'icon'     => 'fa-solid fa-th',
                'category' => 'conversion',
                'schema'   => [
                    'title'   => ['type' => 'string', 'max' => 100, 'required' => false, 'label' => 'Section title', 'default' => 'My Services'],
                    'intro'   => ['type' => 'string', 'max' => 300, 'required' => false, 'label' => 'Intro text', 'multiline' => true],
                    'source'  => ['type' => 'enum',   'options' => ['auto-from-courses', 'manual'], 'default' => 'auto-from-courses', 'label' => 'Show'],
                    'columns' => ['type' => 'enum',   'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columns'],
                    'manual_cards' => ['type' => 'list', 'item_type' => 'object', 'label' => 'Manual cards (when source = manual)', 'item_schema' => [
                        'title'       => ['type' => 'string', 'max' => 80,  'required' => true],
                        'description' => ['type' => 'string', 'max' => 200, 'required' => false],
                        'image'       => ['type' => 'image'],
                        'cta_text'    => ['type' => 'string', 'max' => 30],
                        'cta_url'     => ['type' => 'string', 'max' => 500],
                    ]],
                ],
                'defaults' => [
                    'title'   => 'Programs & Courses',
                    'intro'   => 'Pick the path that fits where you are right now.',
                    'source'  => 'auto-from-courses',
                    'columns' => '3',
                    'manual_cards' => [],
                ],
            ],

            'testimonials_v1' => [
                'label'    => 'Testimonials',
                'icon'     => 'fa-solid fa-quote-right',
                'category' => 'trust',
                'schema'   => [
                    'title'  => ['type' => 'string', 'max' => 100, 'default' => 'What clients say'],
                    'layout' => ['type' => 'enum', 'options' => ['carousel', 'grid'], 'default' => 'grid'],
                    'quotes' => ['type' => 'list', 'item_type' => 'object', 'max_items' => 12, 'item_schema' => [
                        'text'   => ['type' => 'string', 'max' => 500, 'required' => true, 'multiline' => true],
                        'author' => ['type' => 'string', 'max' => 80,  'required' => true],
                        'role'   => ['type' => 'string', 'max' => 80,  'required' => false],
                        'photo'  => ['type' => 'image',  'required' => false],
                        'rating' => ['type' => 'int',    'min' => 1, 'max' => 5, 'default' => 5],
                    ]],
                ],
                'defaults' => [
                    'title'  => 'What clients say',
                    'layout' => 'grid',
                    'quotes' => [],
                ],
            ],

            'youtube_v1' => [
                'label'    => 'YouTube Videos',
                'icon'     => 'fa-solid fa-video',
                'category' => 'media',
                'schema'   => [
                    'title'       => ['type' => 'string', 'max' => 100, 'default' => 'Latest videos'],
                    'channel_id'  => ['type' => 'string', 'max' => 100, 'required' => false, 'label' => 'YouTube Channel ID (leave blank to auto-use coach profile)'],
                    'video_count' => ['type' => 'enum', 'options' => ['3', '6', '9'], 'default' => '6'],
                    'layout'      => ['type' => 'enum', 'options' => ['grid', 'carousel'], 'default' => 'grid'],
                ],
                'defaults' => [
                    'title'       => 'Latest videos',
                    'channel_id'  => '',
                    'video_count' => '6',
                    'layout'      => 'grid',
                ],
            ],

            'video_gallery_v1' => [
                'label'    => 'Live Class Sessions',
                'icon'     => 'fa-solid fa-calendar-check',
                'category' => 'media',
                'schema'   => [
                    // 2026-06-15 — this section now showcases the coach's live-class
                    // courses by default (and renders uploaded videos if any are added).
                    'title'  => ['type' => 'string', 'max' => 100, 'default' => 'Live Class Sessions'],
                    'intro'  => ['type' => 'string', 'max' => 300, 'multiline' => true],
                    'videos' => ['type' => 'list', 'item_type' => 'object', 'max_items' => 30, 'item_schema' => [
                        'title'       => ['type' => 'string', 'max' => 120, 'required' => true],
                        'description' => ['type' => 'string', 'max' => 300],
                        'thumbnail'   => ['type' => 'image'],
                        'video_url'   => ['type' => 'string', 'max' => 500, 'required' => true, 'label' => 'Video URL (mp4, vimeo, youtube, etc.)'],
                    ]],
                ],
                'defaults' => [
                    'title'  => 'Live Class Sessions',
                    'intro'  => '',
                    'videos' => [],
                ],
            ],

            // 2026-06-23 — Image Gallery. Coaches upload multiple images and
            // pick Grid or Carousel layout. Per-image caption + description,
            // optional click-to-zoom lightbox. Pure-CSS/vanilla-JS (no libs);
            // styling lives in coach-site.css, behaviour is self-contained in
            // the section blade so it survives standalone section rendering.
            'gallery_v1' => [
                'label'    => 'Image Gallery',
                'icon'     => 'fa-solid fa-images',
                'category' => 'media',
                'schema'   => [
                    'title'      => ['type' => 'string', 'max' => 120, 'required' => false, 'label' => 'Section title (optional)'],
                    'intro'      => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Intro text (optional)'],
                    'layout'     => ['type' => 'enum', 'options' => ['grid', 'carousel'], 'default' => 'grid', 'label' => 'Layout'],
                    'columns'    => ['type' => 'enum', 'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columns (Grid view)'],
                    'lightbox'   => ['type' => 'bool', 'default' => true, 'label' => 'Click image to zoom (lightbox)'],
                    'autoplay'   => ['type' => 'bool', 'default' => false, 'label' => 'Autoplay (Carousel view)'],
                    'autoplay_speed' => ['type' => 'int', 'min' => 2, 'max' => 15, 'default' => 5, 'label' => 'Autoplay speed (seconds)'],
                    'arrows'     => ['type' => 'bool', 'default' => true, 'label' => 'Show navigation arrows (Carousel view)'],
                    'pagination' => ['type' => 'bool', 'default' => true, 'label' => 'Show pagination dots (Carousel view)'],
                    'images'     => ['type' => 'list', 'item_type' => 'object', 'max_items' => 50, 'add_label' => 'Add image', 'label' => 'Gallery images', 'item_schema' => [
                        'image'       => ['type' => 'image',  'label' => 'Image'],
                        'title'       => ['type' => 'string', 'max' => 100, 'required' => false, 'label' => 'Caption (optional)'],
                        'description' => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Description (optional)'],
                    ]],
                ],
                'defaults' => [
                    'title'      => 'Gallery',
                    'intro'      => '',
                    'layout'     => 'grid',
                    'columns'    => '3',
                    'lightbox'   => true,
                    'autoplay'   => false,
                    'autoplay_speed' => 5,
                    'arrows'     => true,
                    'pagination' => true,
                    'images'     => [],
                ],
            ],

            // 2026-06-24 — Benefits / Features. Center image with concentric
            // circles, benefit items left & right with icon+title+desc and
            // dotted connectors. Layouts: split (left/right), grid, timeline.
            // From "Custom design.docx" item 1.
            'benefits_v1' => [
                'label'    => 'Benefits / Features',
                'icon'     => 'fa-solid fa-circle-nodes',
                'category' => 'content',
                'schema'   => [
                    'eyebrow'      => ['type' => 'string', 'max' => 60, 'required' => false, 'label' => 'Eyebrow (small label above title)'],
                    'title'        => ['type' => 'string', 'max' => 140, 'required' => false, 'label' => 'Section title'],
                    'subtitle'     => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Subtitle (optional)'],
                    'layout'       => ['type' => 'enum', 'options' => ['split', 'grid', 'timeline'], 'default' => 'split', 'label' => 'Layout'],
                    'columns'      => ['type' => 'enum', 'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columns (Grid layout)'],
                    'center_image' => ['type' => 'image', 'label' => 'Center image (Split layout)'],
                    'icon_color'   => ['type' => 'color', 'label' => 'Icon color'],
                    'connector'    => ['type' => 'enum', 'options' => ['dotted', 'dashed', 'none'], 'default' => 'dotted', 'label' => 'Connector line style (Split layout)'],
                    'items'        => ['type' => 'list', 'item_type' => 'object', 'max_items' => 30, 'add_label' => 'Add benefit', 'label' => 'Benefit items', 'item_schema' => [
                        'icon'        => ['type' => 'image',  'label' => 'Icon image (optional — defaults to a check)'],
                        'title'       => ['type' => 'string', 'max' => 120, 'required' => false, 'label' => 'Benefit title'],
                        'description' => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Short description'],
                    ]],
                ],
                'defaults' => [
                    'eyebrow'      => 'Our Benefits',
                    'title'        => 'Why choose us',
                    'subtitle'     => '',
                    'layout'       => 'split',
                    'columns'      => '3',
                    'center_image' => '',
                    'icon_color'   => '',
                    'connector'    => 'dotted',
                    'items'        => [],
                ],
            ],

            // 2026-06-24 — Class Schedule / Upcoming Classes. Responsive card
            // grid; each card has time + instructor badges, title, focus/desc,
            // CTA, optional status. From "Custom design.docx" item 2.
            'schedule_v1' => [
                'label'    => 'Class Schedule',
                'icon'     => 'fa-solid fa-calendar-week',
                'category' => 'content',
                'schema'   => [
                    'eyebrow'   => ['type' => 'string', 'max' => 60, 'required' => false, 'label' => 'Eyebrow (small label above title)'],
                    'title'     => ['type' => 'string', 'max' => 140, 'required' => false, 'label' => 'Section title'],
                    'subtitle'  => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Subtitle (optional)'],
                    'columns'   => ['type' => 'enum', 'options' => ['1', '2', '3', '4'], 'default' => '3', 'label' => 'Columns'],
                    'carousel'  => ['type' => 'bool', 'default' => true, 'label' => 'Swipe carousel on mobile'],
                    'items'     => ['type' => 'list', 'item_type' => 'object', 'max_items' => 50, 'add_label' => 'Add class', 'label' => 'Classes', 'item_schema' => [
                        'time'        => ['type' => 'string', 'max' => 40,  'required' => false, 'label' => 'Class time (e.g. 05:00 AM)'],
                        'title'       => ['type' => 'string', 'max' => 120, 'required' => false, 'label' => 'Class title'],
                        'instructor'  => ['type' => 'string', 'max' => 80,  'required' => false, 'label' => 'Coach / instructor name'],
                        'description' => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Focus / short description'],
                        'btn_text'    => ['type' => 'string', 'max' => 30,  'required' => false, 'label' => 'Button text (e.g. Book Now)'],
                        'btn_url'     => ['type' => 'string', 'max' => 500, 'required' => false, 'label' => 'Button link (URL)'],
                        'badge_color' => ['type' => 'color', 'label' => 'Instructor badge color'],
                        'card_bg'     => ['type' => 'color', 'label' => 'Card background color'],
                        'status'      => ['type' => 'enum', 'options' => ['none', 'upcoming', 'live', 'completed'], 'default' => 'none', 'label' => 'Status badge'],
                    ]],
                    // 2026-07-14 — "Book a Session" popup config. When at least one
                    // time period (with a price) is configured, the class cards open
                    // this booking form + payment flow instead of the plain callback.
                    // All labels/options are coach-editable + reorderable, tenant-specific.
                    'book_title'    => ['type' => 'string', 'max' => 80,  'required' => false, 'label' => 'Booking form — title'],
                    'book_subtitle' => ['type' => 'string', 'max' => 200, 'required' => false, 'label' => 'Booking form — subtitle'],
                    'book_submit'   => ['type' => 'string', 'max' => 40,  'required' => false, 'label' => 'Booking form — submit button text'],
                    'book_success'  => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Booking form — success message'],
                    'plan_types'    => ['type' => 'list', 'item_type' => 'string', 'max_items' => 12, 'add_label' => 'Add plan type',   'label' => 'Plan types (e.g. Online, Offline)'],
                    'course_types'  => ['type' => 'list', 'item_type' => 'string', 'max_items' => 12, 'add_label' => 'Add course type', 'label' => 'Course types (e.g. Individual Plan, Couple Plan)'],
                    'periods'       => ['type' => 'list', 'item_type' => 'object', 'max_items' => 24, 'add_label' => 'Add time period', 'label' => 'Time periods + price (drives the payment amount)', 'item_schema' => [
                        'label' => ['type' => 'string', 'max' => 60, 'required' => false, 'label' => 'Label (e.g. 1 Month)'],
                        'price' => ['type' => 'string', 'max' => 20, 'required' => false, 'label' => 'Price (number, e.g. 2500)'],
                    ]],
                    'reasons'           => ['type' => 'list', 'item_type' => 'string', 'max_items' => 12, 'add_label' => 'Add reason', 'label' => 'Reasons (e.g. Fitness, Problem)'],
                    'show_body_metrics' => ['type' => 'bool', 'default' => true, 'label' => 'Show Height / Weight fields in the booking form'],
                ],
                'defaults' => [
                    'eyebrow'  => 'Our Upcoming',
                    'title'    => 'Classes & Schedules',
                    'subtitle' => '',
                    'columns'  => '3',
                    'carousel' => true,
                    'items'    => [],
                    'book_title'    => 'Book a session',
                    'book_subtitle' => '',
                    'book_submit'   => 'Submit',
                    'book_success'  => '',
                    'plan_types'    => [],
                    'course_types'  => [],
                    'periods'       => [],
                    'reasons'       => [],
                    'show_body_metrics' => true,
                ],
            ],

            // 2026-07-15 — Trainers. A grid of the coach's trainer profiles
            // (managed under Coach Panel → Trainers). Each card links to the
            // public Trainer Detail Page (/trainers/{slug}) where visitors book a
            // Personal Class Session. Data is pulled LIVE + tenant-scoped in the
            // render blade, so profile/package edits reflect automatically.
            'trainers_v1' => [
                'label'    => 'Trainers',
                'icon'     => 'fa-solid fa-user-tie',
                'category' => 'content',
                'schema'   => [
                    'eyebrow'  => ['type' => 'string', 'max' => 60,  'required' => false, 'label' => 'Eyebrow (small label above title)'],
                    'title'    => ['type' => 'string', 'max' => 140, 'required' => false, 'label' => 'Section title'],
                    'subtitle' => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Subtitle (optional)'],
                    'columns'  => ['type' => 'enum',   'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Columns'],
                    'limit'    => ['type' => 'int',    'min' => 0, 'max' => 60, 'label' => 'Max trainers to show (0 = all)'],
                    'btn_text' => ['type' => 'string', 'max' => 40, 'required' => false, 'label' => 'Card button text (e.g. View Profile)'],
                    'show_specialisation' => ['type' => 'bool', 'default' => true, 'label' => 'Show specialisation'],
                    'show_experience'     => ['type' => 'bool', 'default' => true, 'label' => 'Show experience'],
                    'show_packages_count' => ['type' => 'bool', 'default' => true, 'label' => 'Show number of session packages'],
                ],
                'defaults' => [
                    'eyebrow'  => 'Meet Our',
                    'title'    => 'Expert Trainers',
                    'subtitle' => '',
                    'columns'  => '3',
                    'limit'    => 0,
                    'btn_text' => 'View Profile',
                    'show_specialisation' => true,
                    'show_experience'     => true,
                    'show_packages_count' => true,
                ],
            ],

            // 2026-07-15 — Trainer Booking. Drop onto ANY page (e.g. a hand-built
            // trainer/About page) to add a working "Book Personal Class Session"
            // button + form + payment for a chosen trainer. Reuses the exact
            // popup + server-side price + Razorpay pipeline as the Trainer Detail
            // route — no duplicate payment logic.
            'trainer_booking_v1' => [
                'label'    => 'Trainer Booking',
                'icon'     => 'fa-solid fa-calendar-check',
                'category' => 'content',
                'schema'   => [
                    'trainer_name'  => ['type' => 'string', 'max' => 150, 'required' => false, 'label' => 'Trainer name (shown in the booking form)'],
                    'heading'       => ['type' => 'string', 'max' => 140, 'required' => false, 'label' => 'Heading (optional)'],
                    'intro'         => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Intro text (optional)'],
                    'button_text'   => ['type' => 'string', 'max' => 40,  'required' => false, 'label' => 'Button text'],
                    'currency'      => ['type' => 'string', 'max' => 8,   'required' => false, 'label' => 'Currency code (default INR)'],
                    // The PRICE here is server-authoritative — the amount the visitor pays.
                    'packages'      => ['type' => 'list', 'item_type' => 'object', 'max_items' => 24, 'add_label' => 'Add package', 'label' => 'Session packages (label + price — drives the payment amount)', 'item_schema' => [
                        'label' => ['type' => 'string', 'max' => 120, 'required' => false, 'label' => 'Label (e.g. 5 Session Validity 10 days)'],
                        'price' => ['type' => 'string', 'max' => 20,  'required' => false, 'label' => 'Price (number, e.g. 6000)'],
                    ]],
                    'plan_types'    => ['type' => 'list', 'item_type' => 'string', 'max_items' => 12, 'add_label' => 'Add plan type',   'label' => 'Plan types (e.g. Online, Offline)'],
                    'course_types'  => ['type' => 'list', 'item_type' => 'string', 'max_items' => 12, 'add_label' => 'Add course type', 'label' => 'Course types (e.g. Individual Plan, Couple Plan)'],
                    'reasons'       => ['type' => 'list', 'item_type' => 'string', 'max_items' => 12, 'add_label' => 'Add reason',      'label' => 'Reasons (e.g. Fitness, Weight Loss)'],
                    'show_packages' => ['type' => 'bool', 'default' => false, 'label' => 'Also show the package list here (turn off if the page already lists them)'],
                ],
                'defaults' => [
                    'trainer_name'  => '',
                    'heading'       => '',
                    'intro'         => '',
                    'button_text'   => 'Book Now',
                    'currency'      => 'INR',
                    'packages'      => [],
                    'plan_types'    => ['Online', 'Offline'],
                    'course_types'  => ['Individual Plan', 'Couple Plan'],
                    'reasons'       => ['Fitness', 'Weight Loss', 'Problem'],
                    'show_packages' => false,
                ],
            ],

            // 2026-06-24 — Pricing & Plans. Category cards (Online/Offline =
            // booking form with Course Type + Time Period selectors → Book Class
            // modal that stores a lead; Private/Corporate = direct CTA buttons).
            // From "Pricing and Plan.docx".
            'pricing_plans_v1' => [
                'label'    => 'Pricing & Plans',
                'icon'     => 'fa-solid fa-tags',
                'category' => 'conversion',
                'schema'   => [
                    'eyebrow'    => ['type' => 'string', 'max' => 60, 'required' => false, 'label' => 'Eyebrow (small label above title)'],
                    'title'      => ['type' => 'string', 'max' => 140, 'required' => false, 'label' => 'Section title'],
                    'subtitle'   => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Subtitle (optional)'],
                    'columns'    => ['type' => 'enum', 'options' => ['2', '3', '4'], 'default' => '4', 'label' => 'Columns'],
                    'time_slots' => ['type' => 'list', 'item_type' => 'string', 'max_items' => 40, 'add_label' => 'Add time slot', 'label' => 'Booking time slots (e.g. 06:00 AM - Mansi Rawat)'],
                    'categories' => ['type' => 'list', 'item_type' => 'object', 'max_items' => 12, 'add_label' => 'Add category', 'label' => 'Plan categories', 'item_schema' => [
                        'name'     => ['type' => 'string', 'max' => 80, 'required' => false, 'label' => 'Category name (e.g. Online Class)'],
                        'mode'     => ['type' => 'enum', 'options' => ['booking', 'cta'], 'default' => 'booking', 'label' => 'Mode — booking (form) or direct buttons'],
                        'features' => ['type' => 'list', 'item_type' => 'string', 'max_items' => 15, 'add_label' => 'Add feature', 'label' => 'Features'],
                        'individual_periods' => ['type' => 'list', 'item_type' => 'object', 'max_items' => 12, 'add_label' => 'Add period', 'label' => 'Individual plan — time periods (booking mode)', 'item_schema' => [
                            'label' => ['type' => 'string', 'max' => 40, 'required' => false, 'label' => 'Label (e.g. 3 Months)'],
                            'price' => ['type' => 'string', 'max' => 20, 'required' => false, 'label' => 'Price (number, e.g. 6000)'],
                        ]],
                        'couple_periods' => ['type' => 'list', 'item_type' => 'object', 'max_items' => 12, 'add_label' => 'Add period', 'label' => 'Couple plan — time periods (leave empty to hide couple option)', 'item_schema' => [
                            'label' => ['type' => 'string', 'max' => 40, 'required' => false, 'label' => 'Label (e.g. 3 Months)'],
                            'price' => ['type' => 'string', 'max' => 20, 'required' => false, 'label' => 'Price (number, e.g. 9600)'],
                        ]],
                        'cta_buttons' => ['type' => 'list', 'item_type' => 'object', 'max_items' => 3, 'add_label' => 'Add button', 'label' => 'Buttons (direct mode — e.g. Book Now / Enquiry Now)', 'item_schema' => [
                            'label' => ['type' => 'string', 'max' => 30, 'required' => false, 'label' => 'Button text'],
                            'url'   => ['type' => 'string', 'max' => 500, 'required' => false, 'label' => 'Redirect URL'],
                        ]],
                    ]],
                ],
                'defaults' => [
                    'eyebrow'    => 'Our Pricing',
                    'title'      => 'Flexible pricing for everyone',
                    'subtitle'   => '',
                    'columns'    => '4',
                    'time_slots' => [],
                    'categories' => [],
                ],
            ],

            'stats_v1' => [
                'label'    => 'Numbers / Stats',
                'icon'     => 'fa-solid fa-chart-line',
                'category' => 'trust',
                'schema'   => [
                    'stats' => ['type' => 'list', 'item_type' => 'object', 'max_items' => 6, 'item_schema' => [
                        'number' => ['type' => 'string', 'max' => 20, 'required' => true],
                        'suffix' => ['type' => 'string', 'max' => 5],
                        'label'  => ['type' => 'string', 'max' => 60, 'required' => true],
                        'icon'   => ['type' => 'string', 'max' => 60],
                    ]],
                ],
                'defaults' => [
                    'stats' => [
                        ['number' => '500', 'suffix' => '+', 'label' => 'Students coached',  'icon' => 'fa-solid fa-users'],
                        ['number' => '10',  'suffix' => '+', 'label' => 'Years experience',  'icon' => 'fa-solid fa-award'],
                        ['number' => '4.9', 'suffix' => '',  'label' => 'Average rating',    'icon' => 'fa-solid fa-star'],
                    ],
                ],
            ],

            'faq_v1' => [
                'label'    => 'FAQ',
                'icon'     => 'fa-solid fa-circle-question',
                'category' => 'content',
                'schema'   => [
                    'title'   => ['type' => 'string', 'max' => 100, 'default' => 'Frequently asked questions'],
                    'layout'  => ['type' => 'enum', 'options' => ['accordion', 'two-column'], 'default' => 'accordion'],
                    'items'   => ['type' => 'list', 'item_type' => 'object', 'max_items' => 20, 'item_schema' => [
                        'question' => ['type' => 'string',   'max' => 200, 'required' => true],
                        'answer'   => ['type' => 'richtext', 'max' => 1500, 'required' => true],
                    ]],
                ],
                'defaults' => [
                    'title' => 'Frequently asked questions',
                    'layout' => 'accordion',
                    'items' => [],
                ],
            ],

            'lead_form_v1' => [
                'label'    => 'Lead Form',
                'icon'     => 'fa-solid fa-envelope-open-text',
                'category' => 'conversion',
                'schema'   => [
                    'title'      => ['type' => 'string', 'max' => 100, 'default' => 'Get in touch'],
                    'intro'      => ['type' => 'string', 'max' => 300, 'multiline' => true],
                    'fields'     => ['type' => 'list', 'item_type' => 'object', 'max_items' => 10, 'item_schema' => [
                        'key'      => ['type' => 'string', 'max' => 40,  'required' => true, 'label' => 'Field key (no spaces)'],
                        'label'    => ['type' => 'string', 'max' => 80,  'required' => true],
                        'type'     => ['type' => 'enum', 'options' => ['text', 'email', 'tel', 'textarea', 'select'], 'required' => true],
                        'required' => ['type' => 'bool', 'default' => false],
                        'options'  => ['type' => 'list', 'item_type' => 'string', 'max_items' => 20, 'label' => 'Options (for select)'],
                    ]],
                    'submit_text'   => ['type' => 'string', 'max' => 40, 'default' => 'Send message'],
                    'success_text'  => ['type' => 'string', 'max' => 200, 'default' => 'Thanks — we will reach out shortly.'],
                    'service_label' => ['type' => 'string', 'max' => 80,  'default' => 'I am interested in'],
                ],
                'defaults' => [
                    'title'        => 'Get in touch',
                    'intro'        => '',
                    'fields'       => [
                        ['key' => 'first_name', 'label' => 'First name', 'type' => 'text',  'required' => true],
                        ['key' => 'last_name',  'label' => 'Last name',  'type' => 'text',  'required' => false],
                        ['key' => 'email',      'label' => 'Email',      'type' => 'email', 'required' => true],
                        ['key' => 'phone',      'label' => 'Phone',      'type' => 'tel',   'required' => true],
                        ['key' => 'message',    'label' => 'Message',    'type' => 'textarea', 'required' => false],
                    ],
                    'submit_text'  => 'Send message',
                    'success_text' => 'Thanks — we will reach out shortly.',
                ],
            ],

            'cta_banner_v1' => [
                'label'    => 'CTA Banner',
                'icon'     => 'fa-solid fa-bullhorn',
                'category' => 'conversion',
                'schema'   => [
                    'headline' => ['type' => 'string', 'max' => 120, 'required' => true],
                    'subhead'  => ['type' => 'string', 'max' => 240, 'multiline' => true],
                    'cta_text' => ['type' => 'string', 'max' => 30,  'default' => 'Get started'],
                    'cta_url'  => ['type' => 'string', 'max' => 500],
                    'variant'  => ['type' => 'enum', 'options' => ['brand', 'dark', 'light'], 'default' => 'brand'],
                ],
                'defaults' => [
                    'headline' => 'Ready to start?',
                    'subhead'  => 'Book a free discovery call today.',
                    'cta_text' => 'Book now',
                    'cta_url'  => '#contact',
                    'variant'  => 'brand',
                ],
            ],

            'contact_v1' => [
                'label'    => 'Contact + Map',
                'icon'     => 'fa-solid fa-location-dot',
                'category' => 'conversion',
                'schema'   => [
                    'title'         => ['type' => 'string', 'max' => 100, 'default' => 'Visit us'],
                    'address'       => ['type' => 'string', 'max' => 300, 'multiline' => true],
                    'phone'         => ['type' => 'string', 'max' => 30],
                    'email'         => ['type' => 'string', 'max' => 80, 'label' => 'Public email (leave blank to use brand support email)'],
                    'hours'         => ['type' => 'string', 'max' => 200, 'multiline' => true, 'label' => 'Business hours'],
                    'map_embed_url' => ['type' => 'string', 'max' => 600, 'label' => 'Google Maps embed URL'],
                ],
                'defaults' => [
                    'title'   => 'Visit us',
                    'address' => '',
                    'phone'   => '',
                    'email'   => '',
                    'hours'   => 'Mon–Fri 9 AM – 6 PM',
                    'map_embed_url' => '',
                ],
            ],

            'footer_v1' => [
                'label'    => 'Footer',
                'icon'     => 'fa-solid fa-shoe-prints',
                'category' => 'footer',
                'schema'   => [
                    'tagline'       => ['type' => 'string', 'max' => 200],
                    'show_social'   => ['type' => 'bool', 'default' => true],
                    'copyright'     => ['type' => 'string', 'max' => 200, 'default' => 'All rights reserved.'],
                    'link_groups'   => ['type' => 'list', 'item_type' => 'object', 'max_items' => 4, 'item_schema' => [
                        'title' => ['type' => 'string', 'max' => 60, 'required' => true],
                        'links' => ['type' => 'list', 'item_type' => 'object', 'max_items' => 8, 'item_schema' => [
                            'label' => ['type' => 'string', 'max' => 60, 'required' => true],
                            'url'   => ['type' => 'string', 'max' => 500, 'required' => true],
                        ]],
                    ]],
                ],
                'defaults' => [
                    'tagline'     => '',
                    'show_social' => true,
                    'copyright'   => 'All rights reserved.',
                    'link_groups' => [],
                ],
            ],

            // Featured Courses Carousel (2026-07-08) — the coach hand-picks
            // published courses from their Course Module (searchable multi-select
            // with drag-reorder). Course data (thumbnail/title/description/mode)
            // is pulled LIVE at render time, so edits in the Course Module reflect
            // automatically; only the ORDERED list of ids is stored here.
            'featured_courses_v1' => [
                'label'    => 'Featured Courses Carousel',
                'icon'     => 'fa-solid fa-graduation-cap',
                'category' => 'conversion',
                'schema'   => [
                    'badge'          => ['type' => 'string', 'max' => 40,  'required' => false, 'label' => 'Section badge (e.g. Courses)'],
                    'title'          => ['type' => 'string', 'max' => 120, 'required' => false, 'label' => 'Section heading'],
                    'intro'          => ['type' => 'string', 'max' => 300, 'required' => false, 'multiline' => true, 'label' => 'Section subheading (optional)'],
                    'course_ids'     => ['type' => 'course_picker', 'max_items' => 24, 'label' => 'Select courses (search, tick, drag to reorder)'],
                    'btn_text'       => ['type' => 'string', 'max' => 40,  'required' => false, 'default' => 'View Course', 'label' => 'Button text'],
                    'autoplay'       => ['type' => 'bool', 'default' => true,  'label' => 'Auto-scroll'],
                    'autoplay_speed' => ['type' => 'int',  'min' => 2, 'max' => 10, 'default' => 5, 'label' => 'Auto-scroll speed (seconds)'],
                    'slides_desktop' => ['type' => 'enum', 'options' => ['2', '3', '4'], 'default' => '3', 'label' => 'Visible slides — desktop'],
                    'slides_tablet'  => ['type' => 'enum', 'options' => ['1', '2', '3'], 'default' => '2', 'label' => 'Visible slides — tablet'],
                    'slides_mobile'  => ['type' => 'enum', 'options' => ['1', '2'],      'default' => '1', 'label' => 'Visible slides — mobile'],
                    'show_image'     => ['type' => 'bool', 'default' => true, 'label' => 'Show course image'],
                    'show_desc'      => ['type' => 'bool', 'default' => true, 'label' => 'Show description'],
                    'show_mode'      => ['type' => 'bool', 'default' => true, 'label' => 'Show course mode badge (Online/Offline/Hybrid)'],
                    'show_button'    => ['type' => 'bool', 'default' => true, 'label' => 'Show button'],
                    'arrows'         => ['type' => 'bool', 'default' => true, 'label' => 'Show navigation arrows'],
                    'dots'           => ['type' => 'bool', 'default' => true, 'label' => 'Show pagination dots'],
                ],
                'defaults' => [
                    'badge' => 'Courses', 'title' => 'Featured Courses', 'intro' => '',
                    'course_ids' => [], 'btn_text' => 'View Course',
                    'autoplay' => true, 'autoplay_speed' => 5,
                    'slides_desktop' => '3', 'slides_tablet' => '2', 'slides_mobile' => '1',
                    'show_image' => true, 'show_desc' => true, 'show_mode' => true, 'show_button' => true,
                    'arrows' => true, 'dots' => true,
                ],
            ],

            // Migration-only escape hatch for old GrapesJS sites. Hidden from
            // the drawer; only written by the migration script.
            'html_passthrough_v1' => [
                'label'    => 'Legacy HTML (migrated)',
                'icon'     => 'fa-solid fa-code',
                'category' => 'legacy',
                'hidden'   => true,
                'schema'   => [
                    'html' => ['type' => 'string', 'max' => 1000000],
                    'css'  => ['type' => 'string', 'max' => 1000000],
                ],
                'defaults' => ['html' => '', 'css' => ''],
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function defaults(string $type): array
    {
        return self::get($type)['defaults'] ?? [];
    }

    /**
     * Visible-in-drawer section types grouped by category. Hidden ones
     * (html_passthrough_v1) are filtered out.
     */
    public static function byCategory(): array
    {
        $out = [];
        foreach (self::all() as $code => $def) {
            if (!empty($def['hidden'])) {
                continue;
            }
            $out[$def['category']][] = ['code' => $code] + $def;
        }
        return $out;
    }

    /**
     * Lightweight content validation. Throws on bad content_json so the
     * controller can reject the save with a 422.
     */
    public static function validate(string $type, array $content): array
    {
        $def = self::get($type);
        if (! $def) {
            return ['Unknown section type: ' . $type];
        }
        $errors = [];
        foreach ($def['schema'] as $key => $field) {
            $val = $content[$key] ?? null;

            if (! empty($field['required']) && ($val === null || $val === '' || (is_array($val) && empty($val)))) {
                $errors[] = "{$key} is required";
                continue;
            }

            // Optional + not provided: blank string counts as "unset" (the UI
            // sends '' for an empty number/text input), so skip all type checks.
            // Required fields were already caught above.
            if ($val === null || $val === '') {
                continue;
            }

            if (($field['type'] ?? null) === 'string' && is_string($val)) {
                $max = $field['max'] ?? 5000;
                if (mb_strlen($val) > $max) {
                    $errors[] = "{$key} exceeds max length {$max}";
                }
            }

            if (($field['type'] ?? null) === 'enum' && isset($field['options']) && ! in_array($val, $field['options'], true)) {
                $errors[] = "{$key} must be one of: " . implode(', ', $field['options']);
            }

            if (($field['type'] ?? null) === 'int') {
                if (! is_numeric($val)) {
                    $errors[] = "{$key} must be a number";
                } else {
                    $n = (int) $val;
                    if (isset($field['min']) && $n < $field['min']) {
                        $errors[] = "{$key} must be >= {$field['min']}";
                    }
                    if (isset($field['max']) && $n > $field['max']) {
                        $errors[] = "{$key} must be <= {$field['max']}";
                    }
                }
            }
        }
        return $errors;
    }
}
