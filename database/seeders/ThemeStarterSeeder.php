<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Models\ThemeCategory;
use App\Models\ThemePage;
use App\Models\ThemeSection;
use Illuminate\Database\Seeder;

/**
 * Seeds 3 starter themes for launch:
 *   1. Modern Yoga Studio      — wellness / yoga vertical
 *   2. Executive Business Coach — business / consulting vertical
 *   3. Holistic Wellness        — generic coaching vertical (default fallback)
 *
 * Idempotent — uses updateOrCreate on slug.
 */
class ThemeStarterSeeder extends Seeder
{
    public function run(): void
    {
        // ── Categories ────────────────────────────────────────────
        $categories = collect([
            ['name' => 'Yoga & Wellness', 'slug' => 'yoga-wellness', 'icon' => 'fa-solid fa-spa',           'sort_order' => 1],
            ['name' => 'Business Coaching','slug' => 'business',     'icon' => 'fa-solid fa-briefcase',    'sort_order' => 2],
            ['name' => 'Fitness',         'slug' => 'fitness',        'icon' => 'fa-solid fa-dumbbell',     'sort_order' => 3],
            ['name' => 'Mental Health',   'slug' => 'mental-health',  'icon' => 'fa-solid fa-brain',        'sort_order' => 4],
            ['name' => 'Generic',         'slug' => 'generic',        'icon' => 'fa-solid fa-circle-nodes', 'sort_order' => 99],
        ])->mapWithKeys(function ($c) {
            $cat = ThemeCategory::updateOrCreate(['slug' => $c['slug']], $c);
            return [$c['slug'] => $cat->id];
        });

        // ── Theme 1: Modern Yoga Studio ───────────────────────────
        $this->createTheme([
            'name'           => 'Modern Yoga Studio',
            'slug'           => 'modern-yoga-studio',
            'description'    => 'Calming, spa-like theme designed for yoga teachers, meditation guides, and wellness studios.',
            'default_colors' => ['primary' => '#7C3AED', 'accent' => '#F59E0B', 'text' => '#0F172A', 'bg' => '#FFFFFF'],
            'default_fonts'  => ['display' => 'Playfair Display', 'body' => 'Inter'],
            'categories'     => [$categories['yoga-wellness']],
            'is_enabled'     => true,
            'sort_order'     => 1,
        ], [
            $this->yogaHome(),
            $this->yogaAbout(),
            $this->yogaServices(),
            $this->yogaContact(),
        ]);

        // ── Theme 2: Executive Business Coach ─────────────────────
        $this->createTheme([
            'name'           => 'Executive Business Coach',
            'slug'           => 'executive-business-coach',
            'description'    => 'Professional, confident theme for business coaches, consultants, and leadership trainers.',
            'default_colors' => ['primary' => '#1E40AF', 'accent' => '#F97316', 'text' => '#0F172A', 'bg' => '#F8FAFC'],
            'default_fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Inter'],
            'categories'     => [$categories['business']],
            'is_enabled'     => true,
            'sort_order'     => 2,
        ], [
            $this->businessHome(),
            $this->businessAbout(),
            $this->businessServices(),
            $this->businessContact(),
        ]);

        // ── Theme 3: Holistic Wellness ────────────────────────────
        $this->createTheme([
            'name'           => 'Holistic Wellness',
            'slug'           => 'holistic-wellness',
            'description'    => 'Versatile, warm theme suitable for any coaching practice — life coaching, mental health, holistic wellness.',
            'default_colors' => ['primary' => '#10B981', 'accent' => '#EC4899', 'text' => '#0F172A', 'bg' => '#FFFFFF'],
            'default_fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Inter'],
            'categories'     => [$categories['generic'], $categories['mental-health']],
            'is_enabled'     => true,
            'sort_order'     => 3,
        ], [
            $this->genericHome(),
            $this->genericAbout(),
            $this->genericServices(),
            $this->genericContact(),
        ]);
    }

    /** Create or update a theme + its pages + sections. */
    protected function createTheme(array $themeData, array $pages): void
    {
        $categories = $themeData['categories'] ?? [];
        unset($themeData['categories']);

        $theme = Theme::updateOrCreate(['slug' => $themeData['slug']], $themeData);
        $theme->categories()->sync($categories);

        // Wipe old pages/sections (idempotent reseed)
        foreach ($theme->pages as $p) {
            $p->sections()->delete();
            $p->delete();
        }

        foreach ($pages as $pageData) {
            $sections = $pageData['sections'] ?? [];
            unset($pageData['sections']);
            $page = $theme->pages()->create($pageData);
            foreach ($sections as $sort => $s) {
                $page->sections()->create([
                    'section_type'    => $s['type'],
                    'section_version' => 'v1',
                    'sort_order'      => $sort,
                    'is_required'     => $s['required'] ?? false,
                    'content_json'    => $s['content'] ?? [],
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // YOGA THEME PAGES
    // ──────────────────────────────────────────────────────────────

    protected function yogaHome(): array
    {
        return [
            'slug' => 'home', 'page_type' => 'home', 'title' => 'Home', 'sort_order' => 0, 'is_required' => true,
            'meta_title' => '{{brand_name}} — Yoga & Wellness',
            'meta_description' => 'Personalized yoga and meditation sessions with {{coach_name}}.',
            'sections' => [
                ['type' => 'hero_v1', 'required' => true, 'content' => [
                    'headline' => 'Find your balance with {{brand_name}}',
                    'subhead'  => 'Personalized yoga sessions, expert guidance, lifelong wellness.',
                    'cta_text' => 'Book a free class',
                    'cta_url'  => '#contact',
                    'background_variant' => 'gradient',
                    'layout'   => 'text-image-right',
                    '_preset'  => 'soft', '_font_family' => 'Playfair Display',
                ]],
                ['type' => 'about_v1', 'content' => [
                    'name' => '{{coach_name}}',
                    'role' => 'Certified Yoga Teacher',
                    'bio'  => 'I help students of all levels build strength, flexibility, and inner peace through traditional and modern yoga practices.',
                    'credentials' => ['200-hr Yoga Teacher Certification', 'Meditation Instructor', '10+ years of practice'],
                ]],
                ['type' => 'services_grid_v1', 'content' => [
                    'title' => 'Programs & Classes',
                    'intro' => 'Whether you are a beginner or seasoned practitioner, there is a path for you.',
                    'source' => 'auto-from-courses',
                    'columns' => '3',
                ]],
                ['type' => 'testimonials_v1', 'content' => [
                    'title' => 'Student stories',
                    'layout' => 'grid',
                    'quotes' => [
                        ['text' => 'Best yoga teacher I have practiced with. The classes are challenging and the energy is incredible.', 'author' => 'Priya S.', 'role' => 'Student', 'rating' => 5],
                        ['text' => 'I came in stiff and stressed. Six months later I feel transformed.',                                       'author' => 'Rahul M.', 'role' => 'Student', 'rating' => 5],
                        ['text' => 'The breathing techniques alone have changed my life.',                                                       'author' => 'Anjali V.','role' => 'Student', 'rating' => 5],
                    ],
                ]],
                ['type' => 'lead_form_v1', 'content' => [
                    'title' => 'Book your first session',
                    'intro' => 'Tell us a bit about yourself and we will get back to you within 24 hours.',
                    'fields' => [
                        ['key' => 'first_name', 'label' => 'Your name', 'type' => 'text',  'required' => true],
                        ['key' => 'email',      'label' => 'Email',     'type' => 'email', 'required' => true],
                        ['key' => 'phone',      'label' => 'Phone',     'type' => 'tel',   'required' => true],
                        ['key' => 'message',    'label' => 'What brings you here?', 'type' => 'textarea', 'required' => false],
                    ],
                    'submit_text' => 'Book a free class',
                ]],
                ['type' => 'footer_v1', 'required' => true, 'content' => [
                    'tagline' => '{{brand_name}} — Yoga for every body',
                    'copyright' => 'All rights reserved.',
                ]],
            ],
        ];
    }

    protected function yogaAbout(): array
    {
        return [
            'slug' => 'about', 'page_type' => 'about', 'title' => 'About', 'sort_order' => 1,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'Meet {{coach_name}}', 'subhead' => 'Yoga teacher · Meditation guide · Lifelong learner', 'layout' => 'text-center', '_preset' => 'soft']],
                ['type' => 'about_v1', 'content' => ['name' => '{{coach_name}}', 'role' => 'Founder & Lead Teacher', 'bio' => 'Share your full story here — your training, your inspiration, your approach to teaching.']],
                ['type' => 'stats_v1', 'content' => ['stats' => [
                    ['number' => '500', 'suffix' => '+', 'label' => 'Students taught', 'icon' => 'fa-solid fa-users'],
                    ['number' => '10',  'suffix' => '+', 'label' => 'Years experience', 'icon' => 'fa-solid fa-award'],
                    ['number' => '4.9', 'suffix' => '',  'label' => 'Average rating',  'icon' => 'fa-solid fa-star'],
                ]]],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}', 'copyright' => 'All rights reserved.']],
            ],
        ];
    }

    protected function yogaServices(): array
    {
        return [
            'slug' => 'services', 'page_type' => 'services', 'title' => 'Classes & Programs', 'sort_order' => 2,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'Classes & Programs', 'subhead' => 'Find the path that fits where you are right now.', 'layout' => 'text-center', '_preset' => 'soft']],
                ['type' => 'services_grid_v1', 'content' => ['title' => '', 'source' => 'auto-from-courses', 'columns' => '3']],
                ['type' => 'cta_banner_v1', 'content' => ['headline' => 'Ready to start your practice?', 'subhead' => 'Book a free 30-min discovery call.', 'cta_text' => 'Book now', 'cta_url' => '/contact', 'variant' => 'brand']],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}', 'copyright' => 'All rights reserved.']],
            ],
        ];
    }

    protected function yogaContact(): array
    {
        return [
            'slug' => 'contact', 'page_type' => 'contact', 'title' => 'Contact', 'sort_order' => 3,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'Get in touch', 'subhead' => 'I would love to hear from you.', 'layout' => 'text-center', '_preset' => 'soft']],
                ['type' => 'contact_v1', 'content' => ['title' => 'Visit us', 'email' => '{{support_email}}', 'phone' => '{{coach_phone}}', 'hours' => 'Mon–Sat · 6 AM – 8 PM']],
                ['type' => 'lead_form_v1', 'content' => ['title' => 'Send a message', 'submit_text' => 'Send']],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}', 'copyright' => 'All rights reserved.']],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // BUSINESS THEME PAGES
    // ──────────────────────────────────────────────────────────────

    protected function businessHome(): array
    {
        return [
            'slug' => 'home', 'page_type' => 'home', 'title' => 'Home', 'sort_order' => 0, 'is_required' => true,
            'meta_title' => '{{brand_name}} — Executive Coaching',
            'sections' => [
                ['type' => 'hero_v1', 'required' => true, 'content' => [
                    'headline' => 'Build a business that scales',
                    'subhead'  => 'Executive coaching for founders, leaders, and high performers.',
                    'cta_text' => 'Schedule a call',
                    'cta_url'  => '#contact',
                    'background_variant' => 'gradient',
                    'layout'   => 'text-image-right',
                    '_preset'  => 'brand', '_font_family' => 'Plus Jakarta Sans',
                ]],
                ['type' => 'stats_v1', 'content' => ['stats' => [
                    ['number' => '200', 'suffix' => '+', 'label' => 'Leaders coached',  'icon' => 'fa-solid fa-user-tie'],
                    ['number' => '15',  'suffix' => '+', 'label' => 'Years experience', 'icon' => 'fa-solid fa-award'],
                    ['number' => '92',  'suffix' => '%', 'label' => 'Client retention',  'icon' => 'fa-solid fa-chart-line'],
                ]]],
                ['type' => 'about_v1', 'content' => ['name' => '{{coach_name}}', 'role' => 'Executive Coach', 'bio' => 'I work with founders and senior leaders to unlock growth, navigate transitions, and lead with clarity.']],
                ['type' => 'services_grid_v1', 'content' => ['title' => 'Coaching engagements', 'source' => 'auto-from-courses', 'columns' => '3']],
                ['type' => 'testimonials_v1', 'content' => ['title' => 'Client outcomes', 'quotes' => [
                    ['text' => 'Doubled my revenue in 18 months while working fewer hours. Best investment I made.', 'author' => 'Founder, SaaS startup', 'rating' => 5],
                    ['text' => 'Helped me transition from manager to VP. The clarity sessions were transformational.', 'author' => 'VP Engineering', 'rating' => 5],
                ]]],
                ['type' => 'cta_banner_v1', 'content' => ['headline' => 'Ready to lead better?', 'subhead' => 'Book a complimentary 30-min strategy call.', 'cta_text' => 'Book the call', 'cta_url' => '#contact', 'variant' => 'brand']],
                ['type' => 'lead_form_v1', 'content' => ['title' => 'Get in touch']],
                ['type' => 'footer_v1', 'required' => true, 'content' => ['tagline' => '{{brand_name}}', 'copyright' => 'All rights reserved.']],
            ],
        ];
    }

    protected function businessAbout(): array
    {
        return [
            'slug' => 'about', 'page_type' => 'about', 'title' => 'About', 'sort_order' => 1,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'About {{coach_name}}', 'layout' => 'text-center']],
                ['type' => 'about_v1', 'content' => ['name' => '{{coach_name}}', 'role' => 'Executive Coach & Strategist']],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}']],
            ],
        ];
    }

    protected function businessServices(): array
    {
        return [
            'slug' => 'services', 'page_type' => 'services', 'title' => 'Services', 'sort_order' => 2,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'Coaching Engagements', 'layout' => 'text-center']],
                ['type' => 'services_grid_v1', 'content' => ['source' => 'auto-from-courses', 'columns' => '3']],
                ['type' => 'faq_v1', 'content' => ['title' => 'Common questions', 'items' => [
                    ['question' => 'How long is a typical engagement?', 'answer' => 'Most engagements run 3 to 6 months with bi-weekly sessions.'],
                    ['question' => 'Do you work with teams or individuals?', 'answer' => 'Both — individual coaching and team / leadership development.'],
                ]]],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}']],
            ],
        ];
    }

    protected function businessContact(): array
    {
        return [
            'slug' => 'contact', 'page_type' => 'contact', 'title' => 'Contact', 'sort_order' => 3,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'Schedule a call', 'layout' => 'text-center']],
                ['type' => 'lead_form_v1', 'content' => ['title' => 'Tell us about your goals']],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}']],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // GENERIC THEME PAGES
    // ──────────────────────────────────────────────────────────────

    protected function genericHome(): array
    {
        return [
            'slug' => 'home', 'page_type' => 'home', 'title' => 'Home', 'sort_order' => 0, 'is_required' => true,
            'sections' => [
                ['type' => 'hero_v1', 'required' => true, 'content' => [
                    'headline' => 'Welcome to {{brand_name}}',
                    'subhead'  => 'Personalized coaching to help you reach your goals.',
                    'cta_text' => 'Get started',
                    'cta_url'  => '#contact',
                    'layout'   => 'text-image-right', '_preset' => 'brand',
                ]],
                ['type' => 'about_v1', 'content' => ['name' => '{{coach_name}}', 'role' => 'Certified Coach']],
                ['type' => 'services_grid_v1', 'content' => ['source' => 'auto-from-courses', 'columns' => '3']],
                ['type' => 'testimonials_v1', 'content' => ['quotes' => []]],
                ['type' => 'lead_form_v1', 'content' => ['title' => 'Get in touch']],
                ['type' => 'footer_v1', 'required' => true, 'content' => ['tagline' => '{{brand_name}}']],
            ],
        ];
    }

    protected function genericAbout(): array
    {
        return [
            'slug' => 'about', 'page_type' => 'about', 'title' => 'About', 'sort_order' => 1,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'About me', 'layout' => 'text-center']],
                ['type' => 'about_v1', 'content' => ['name' => '{{coach_name}}']],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}']],
            ],
        ];
    }

    protected function genericServices(): array
    {
        return [
            'slug' => 'services', 'page_type' => 'services', 'title' => 'Services', 'sort_order' => 2,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'What I offer', 'layout' => 'text-center']],
                ['type' => 'services_grid_v1', 'content' => ['source' => 'auto-from-courses', 'columns' => '3']],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}']],
            ],
        ];
    }

    protected function genericContact(): array
    {
        return [
            'slug' => 'contact', 'page_type' => 'contact', 'title' => 'Contact', 'sort_order' => 3,
            'sections' => [
                ['type' => 'hero_v1', 'content' => ['headline' => 'Contact', 'layout' => 'text-center']],
                ['type' => 'lead_form_v1', 'content' => ['title' => 'Send a message']],
                ['type' => 'footer_v1', 'content' => ['tagline' => '{{brand_name}}']],
            ],
        ];
    }
}
