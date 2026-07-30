<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Models\ThemeCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Full Theme Catalog — 20 themes across 8 categories.
 *
 * Each theme has a meta block + voice (domain copy) + style (fonts/preset)
 * + categories. Generic page builders translate those into the standard
 * 4-page structure (Home / About / Services / Contact).
 *
 * Idempotent — uses updateOrCreate on slug. Safe to re-run.
 */
class ThemeFullCatalogSeeder extends Seeder
{
    /** Resolved category id map after seedCategories() runs */
    protected array $cats = [];

    public function run(): void
    {
        $this->seedCategories();

        foreach ($this->themeCatalog() as $sort => $config) {
            $this->createTheme($config, $sort + 1);
        }

        $this->command->info('Theme catalog seeded — ' . count($this->themeCatalog()) . ' themes total.');
    }

    // ──────────────────────────────────────────────────────────────────
    // CATEGORIES (8 total, 3 new added on top of existing 5)
    // ──────────────────────────────────────────────────────────────────

    protected function seedCategories(): void
    {
        $data = [
            ['name' => 'Yoga & Wellness',     'slug' => 'yoga-wellness',      'icon' => 'fa-solid fa-spa',             'sort_order' => 1],
            ['name' => 'Fitness & Sports',    'slug' => 'fitness',            'icon' => 'fa-solid fa-dumbbell',        'sort_order' => 2],
            ['name' => 'Business Coaching',   'slug' => 'business',           'icon' => 'fa-solid fa-briefcase',       'sort_order' => 3],
            ['name' => 'Life & Motivation',   'slug' => 'life-motivation',    'icon' => 'fa-solid fa-rocket',          'sort_order' => 4],
            ['name' => 'Mental Health',       'slug' => 'mental-health',      'icon' => 'fa-solid fa-brain',           'sort_order' => 5],
            ['name' => 'Education & Tutoring','slug' => 'education-tutoring', 'icon' => 'fa-solid fa-graduation-cap',  'sort_order' => 6],
            ['name' => 'Health & Nutrition',  'slug' => 'health-nutrition',   'icon' => 'fa-solid fa-apple-whole',     'sort_order' => 7],
            ['name' => 'Generic',             'slug' => 'generic',            'icon' => 'fa-solid fa-circle-nodes',    'sort_order' => 99],
        ];
        foreach ($data as $c) {
            $cat = ThemeCategory::updateOrCreate(['slug' => $c['slug']], $c);
            $this->cats[$c['slug']] = $cat->id;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // THEME CATALOG — 20 themes (3 existing upserted + 17 new)
    // ──────────────────────────────────────────────────────────────────

    protected function themeCatalog(): array
    {
        return [
            // === Yoga & Wellness (3 themes) ===
            $this->themeModernYoga(),
            $this->themeMindfulMeditation(),
            $this->themeAyurveda(),

            // === Fitness & Sports (3 themes) ===
            $this->themeHIIT(),
            $this->themeStrength(),
            $this->themePersonalTrainer(),

            // === Business & Leadership (3 themes) ===
            $this->themeExecutiveBusiness(),
            $this->themeStartupMentor(),
            $this->themeSalesMarketing(),

            // === Life & Motivation (3 themes) ===
            $this->themeLifeTransformation(),
            $this->themeMotivationalSpeaker(),
            $this->themePersonalGrowth(),

            // === Mental Health & Therapy (2 themes) ===
            $this->themeMindfulnessTherapist(),
            $this->themeCounselingPractice(),

            // === Education & Tutoring (2 themes) ===
            $this->themeAcademicTutor(),
            $this->themeLanguageLearning(),

            // === Health & Nutrition (2 themes) ===
            $this->themeDietician(),
            $this->themeWellnessNutrition(),

            // === Generic (2 themes) ===
            $this->themeHolisticWellness(),
            $this->themeModernCoachPro(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // THEME DEFINITIONS — each returns a config array
    // ──────────────────────────────────────────────────────────────────

    protected function themeModernYoga(): array
    {
        return [
            'meta' => [
                'name' => 'Modern Yoga Studio', 'slug' => 'modern-yoga-studio',
                'description' => 'Calming, spa-like theme designed for yoga teachers, meditation guides, and wellness studios.',
                'colors' => ['primary' => '#7C3AED', 'accent' => '#F59E0B'],
                'fonts'  => ['display' => 'Playfair Display', 'body' => 'Inter'],
            ],
            'categories' => ['yoga-wellness'],
            'voice' => [
                'hero_headline' => 'Find your balance with {{brand_name}}',
                'hero_subhead'  => 'Personalized yoga sessions, expert guidance, lifelong wellness.',
                'cta_text'      => 'Book a free class',
                'role'          => 'Certified Yoga Teacher',
                'bio'           => 'I help students of all levels build strength, flexibility, and inner peace through traditional and modern yoga practices.',
                'credentials'   => ['200-hr Yoga Teacher Certification', 'Meditation Instructor', '10+ years of practice'],
                'services_title'=> 'Programs & Classes',
                'services_intro'=> 'Whether you are a beginner or seasoned practitioner, there is a path for you.',
                'about_page_title' => 'Meet {{coach_name}}',
                'cta_banner_h'  => 'Ready to start your practice?',
                'cta_banner_sub'=> 'Book a free 30-min discovery call.',
                'lead_title'    => 'Book your first session',
                'footer_tagline'=> '{{brand_name}} — Yoga for every body',
                'testimonials'  => [
                    ['text' => 'Best yoga teacher I have practiced with. The classes are challenging and the energy is incredible.', 'author' => 'Priya S.',  'role' => 'Student', 'rating' => 5],
                    ['text' => 'I came in stiff and stressed. Six months later I feel transformed.',                                       'author' => 'Rahul M.',  'role' => 'Student', 'rating' => 5],
                    ['text' => 'The breathing techniques alone have changed my life.',                                                       'author' => 'Anjali V.', 'role' => 'Student', 'rating' => 5],
                ],
                'has_recorded_preview' => true,
                'recorded_title'       => 'On-demand yoga sessions',
                'recorded_intro'       => 'Practice with me anytime — watch a free 1-minute preview of each session.',
            ],
            'style' => ['hero_preset' => 'soft', 'hero_align' => 'text-image-right', 'font_family' => 'Playfair Display'],
        ];
    }

    protected function themeMindfulMeditation(): array
    {
        return [
            'meta' => [
                'name' => 'Mindful Meditation', 'slug' => 'mindful-meditation',
                'description' => 'Tranquil, serene theme for meditation guides, mindfulness teachers, and breathwork instructors.',
                'colors' => ['primary' => '#0D9488', 'accent' => '#F472B6'],
                'fonts'  => ['display' => 'Playfair Display', 'body' => 'Lato'],
            ],
            'categories' => ['yoga-wellness'],
            'voice' => [
                'hero_headline' => 'Breathe. Be present. Begin again.',
                'hero_subhead'  => 'Guided meditation and mindfulness practice with {{coach_name}}.',
                'cta_text'      => 'Try a free session',
                'role'          => 'Meditation Guide',
                'bio'           => 'Through guided meditation and mindful awareness, I help you find calm in chaos and clarity in confusion.',
                'credentials'   => ['Certified MBSR Instructor', 'Vipassana Trained', '500+ hours of practice'],
                'services_title'=> 'Meditation Programs',
                'services_intro'=> 'Start with a single breath. Build a lifelong practice.',
                'about_page_title' => 'About {{coach_name}}',
                'cta_banner_h'  => 'Begin your mindfulness journey today',
                'cta_banner_sub'=> 'Join a free intro session.',
                'lead_title'    => 'Get in touch',
                'footer_tagline'=> '{{brand_name}} — Quiet mind, full heart',
                'testimonials'  => [
                    ['text' => 'I finally learned to quiet my mind. Life-changing.',  'author' => 'Neha K.',   'role' => 'Student', 'rating' => 5],
                    ['text' => 'These sessions are my refuge each week.',             'author' => 'Arun P.',   'role' => 'Student', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'soft', 'hero_align' => 'text-center', 'font_family' => 'Playfair Display'],
        ];
    }

    protected function themeAyurveda(): array
    {
        return [
            'meta' => [
                'name' => 'Ayurveda & Healing', 'slug' => 'ayurveda-healing',
                'description' => 'Traditional, warm theme for ayurveda practitioners, holistic healers, and traditional medicine coaches.',
                'colors' => ['primary' => '#A16207', 'accent' => '#65A30D'],
                'fonts'  => ['display' => 'Playfair Display', 'body' => 'Open Sans'],
            ],
            'categories' => ['yoga-wellness', 'health-nutrition'],
            'voice' => [
                'hero_headline' => 'Heal naturally with ancient wisdom',
                'hero_subhead'  => 'Personalized ayurveda consultations and holistic health programs by {{coach_name}}.',
                'cta_text'      => 'Book consultation',
                'role'          => 'Ayurveda Practitioner',
                'bio'           => 'I blend 5,000 years of ayurvedic wisdom with modern health science to create personalized healing protocols for body, mind, and spirit.',
                'credentials'   => ['BAMS Certified', 'Panchakarma Specialist', '15+ years experience'],
                'services_title'=> 'Healing Programs',
                'services_intro'=> 'Discover your dosha. Restore your balance.',
                'about_page_title' => 'Meet {{coach_name}}',
                'cta_banner_h'  => 'Start your healing journey',
                'cta_banner_sub'=> 'Book a free dosha assessment.',
                'lead_title'    => 'Schedule your consultation',
                'footer_tagline'=> '{{brand_name}} — Ancient wisdom for modern wellness',
                'testimonials'  => [
                    ['text' => 'Healed my chronic digestion issues in 3 months.', 'author' => 'Meera R.', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'Truly transformative. Found balance after years.',  'author' => 'Vikram J.', 'role' => 'Client', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Playfair Display'],
        ];
    }

    protected function themeHIIT(): array
    {
        return [
            'meta' => [
                'name' => 'HIIT Power Trainer', 'slug' => 'hiit-power-trainer',
                'description' => 'High-energy theme for HIIT coaches, fitness trainers, and athletic performance experts.',
                'colors' => ['primary' => '#DC2626', 'accent' => '#0F172A'],
                'fonts'  => ['display' => 'Bebas Neue', 'body' => 'Inter'],
            ],
            'categories' => ['fitness'],
            'voice' => [
                'hero_headline' => 'GET FIT. STAY FIT.',
                'hero_subhead'  => 'High-intensity training that delivers real results. No fluff. Just sweat.',
                'cta_text'      => 'Start your free trial',
                'role'          => 'HIIT Coach & Performance Trainer',
                'bio'           => 'I push you past your limits to build the strongest, fittest version of yourself. No excuses, just results.',
                'credentials'   => ['NASM Certified', 'CrossFit Level 2', '500+ clients transformed'],
                'services_title'=> 'TRAINING PROGRAMS',
                'services_intro'=> 'Pick your plan. Show up. Transform.',
                'about_page_title' => 'MEET YOUR COACH',
                'cta_banner_h'  => 'READY TO TRANSFORM?',
                'cta_banner_sub'=> 'Book a free intro session today.',
                'lead_title'    => 'Get a custom plan',
                'footer_tagline'=> '{{brand_name}} — Stronger every day',
                'testimonials'  => [
                    ['text' => 'Lost 25 pounds in 3 months. Best decision ever.',   'author' => 'Karan S.', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'Hardest workouts of my life. Worth every drop of sweat.','author' => 'Anita M.', 'role' => 'Client', 'rating' => 5],
                ],
                'stats' => [
                    ['number' => '500', 'suffix' => '+', 'label' => 'Clients transformed',  'icon' => 'fa-solid fa-fire'],
                    ['number' => '10',  'suffix' => '+', 'label' => 'Years experience',      'icon' => 'fa-solid fa-medal'],
                    ['number' => '95',  'suffix' => '%', 'label' => 'Client retention',       'icon' => 'fa-solid fa-bolt'],
                ],
                'has_recorded_preview' => true,
                'recorded_title'       => 'WORKOUT LIBRARY',
                'recorded_intro'       => 'Preview any workout free for 60 seconds. Like what you see? Add to cart.',
            ],
            'style' => ['hero_preset' => 'dark', 'hero_align' => 'text-center', 'font_family' => 'Bebas Neue'],
        ];
    }

    protected function themeStrength(): array
    {
        return [
            'meta' => [
                'name' => 'Strength & Conditioning', 'slug' => 'strength-conditioning',
                'description' => 'Bold, focused theme for strength coaches, powerlifting trainers, and conditioning specialists.',
                'colors' => ['primary' => '#475569', 'accent' => '#EA580C'],
                'fonts'  => ['display' => 'Oswald', 'body' => 'Inter'],
            ],
            'categories' => ['fitness'],
            'voice' => [
                'hero_headline' => 'Build real strength',
                'hero_subhead'  => 'Strength training and athletic conditioning programs by {{coach_name}}.',
                'cta_text'      => 'Book a session',
                'role'          => 'Strength & Conditioning Coach',
                'bio'           => 'I help athletes and everyday lifters build serious strength, mobility, and athletic performance.',
                'credentials'   => ['CSCS Certified', 'NSCA Trained', 'Former competitive powerlifter'],
                'services_title'=> 'Coaching Programs',
                'services_intro'=> 'Progressive overload. Measurable results.',
                'about_page_title' => 'Meet your coach',
                'cta_banner_h'  => 'Start training stronger',
                'cta_banner_sub'=> 'Get a free strength assessment.',
                'lead_title'    => 'Start your program',
                'footer_tagline'=> '{{brand_name}} — Train hard. Lift heavy.',
                'testimonials'  => [
                    ['text' => 'Added 50kg to my deadlift in 6 months.',  'author' => 'Rohit V.', 'role' => 'Athlete', 'rating' => 5],
                    ['text' => 'Programming that actually delivers gains.','author' => 'Sneha L.', 'role' => 'Lifter',  'rating' => 5],
                ],
                'has_recorded_preview' => true,
                'recorded_title'       => 'Training Programs',
                'recorded_intro'       => 'Preview the first minute of any program — see the format, then commit.',
            ],
            'style' => ['hero_preset' => 'dark', 'hero_align' => 'text-image-left', 'font_family' => 'Oswald'],
        ];
    }

    protected function themePersonalTrainer(): array
    {
        return [
            'meta' => [
                'name' => 'Personal Trainer Studio', 'slug' => 'personal-trainer-studio',
                'description' => 'Modern, motivational theme for personal trainers, group fitness instructors, and gym owners.',
                'colors' => ['primary' => '#84CC16', 'accent' => '#1E293B'],
                'fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Inter'],
            ],
            'categories' => ['fitness'],
            'voice' => [
                'hero_headline' => 'Your fitness journey starts here',
                'hero_subhead'  => 'Personalized training programs designed to fit your goals, your schedule, your life.',
                'cta_text'      => 'Get started',
                'role'          => 'Certified Personal Trainer',
                'bio'           => 'I create custom workout plans that meet you where you are and take you where you want to go.',
                'credentials'   => ['ACE Certified', 'Nutrition Specialist', '8+ years coaching'],
                'services_title'=> 'Training Plans',
                'services_intro'=> 'Beginner to advanced — there is a plan for you.',
                'about_page_title' => 'Hi, I am {{coach_name}}',
                'cta_banner_h'  => 'Ready to get started?',
                'cta_banner_sub'=> 'Book a free consultation.',
                'lead_title'    => 'Start your journey',
                'footer_tagline'=> '{{brand_name}} — Train smart. Live well.',
                'testimonials'  => [
                    ['text' => 'Finally found a program that fits my busy schedule.', 'author' => 'Pooja D.', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'The accountability is a game-changer.',                'author' => 'Amit S.',  'role' => 'Client', 'rating' => 5],
                ],
                'has_recorded_preview' => true,
                'recorded_title'       => 'On-demand workouts',
                'recorded_intro'       => 'Watch a free 1-minute preview of any workout. Start training today.',
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Plus Jakarta Sans'],
        ];
    }

    protected function themeExecutiveBusiness(): array
    {
        return [
            'meta' => [
                'name' => 'Executive Business Coach', 'slug' => 'executive-business-coach',
                'description' => 'Professional, confident theme for business coaches, consultants, and leadership trainers.',
                'colors' => ['primary' => '#1E40AF', 'accent' => '#F97316'],
                'fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Inter'],
            ],
            'categories' => ['business'],
            'voice' => [
                'hero_headline' => 'Build a business that scales',
                'hero_subhead'  => 'Executive coaching for founders, leaders, and high performers.',
                'cta_text'      => 'Schedule a call',
                'role'          => 'Executive Coach',
                'bio'           => 'I work with founders and senior leaders to unlock growth, navigate transitions, and lead with clarity.',
                'credentials'   => ['MBA + 15 years coaching', 'Former VP at Fortune 500', 'ICF Master Certified'],
                'services_title'=> 'Coaching Engagements',
                'services_intro'=> 'Strategic clarity for every stage of leadership.',
                'about_page_title' => 'About {{coach_name}}',
                'cta_banner_h'  => 'Ready to lead better?',
                'cta_banner_sub'=> 'Book a complimentary 30-min strategy call.',
                'lead_title'    => 'Get in touch',
                'footer_tagline'=> '{{brand_name}} — Leadership unlocked',
                'testimonials'  => [
                    ['text' => 'Doubled my revenue in 18 months. Best investment.',   'author' => 'Founder, SaaS startup', 'role' => 'CEO', 'rating' => 5],
                    ['text' => 'Helped me transition from manager to VP.',             'author' => 'VP Engineering',        'role' => 'Leader','rating' => 5],
                ],
                'stats' => [
                    ['number' => '200', 'suffix' => '+', 'label' => 'Leaders coached',  'icon' => 'fa-solid fa-user-tie'],
                    ['number' => '15',  'suffix' => '+', 'label' => 'Years experience', 'icon' => 'fa-solid fa-award'],
                    ['number' => '92',  'suffix' => '%', 'label' => 'Client retention',  'icon' => 'fa-solid fa-chart-line'],
                ],
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Plus Jakarta Sans'],
        ];
    }

    protected function themeStartupMentor(): array
    {
        return [
            'meta' => [
                'name' => 'Startup Mentor', 'slug' => 'startup-mentor',
                'description' => 'Innovative, modern theme for startup mentors, founder coaches, and venture advisors.',
                'colors' => ['primary' => '#4F46E5', 'accent' => '#06B6D4'],
                'fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Inter'],
            ],
            'categories' => ['business'],
            'voice' => [
                'hero_headline' => 'From idea to scale',
                'hero_subhead'  => 'I help founders build, launch, and grow startups that matter.',
                'cta_text'      => 'Book a mentor session',
                'role'          => 'Startup Mentor & Advisor',
                'bio'           => 'Former founder. Multiple exits. Now helping the next generation of founders avoid the mistakes I made.',
                'credentials'   => ['2 successful exits', '50+ startups mentored', 'YC alumni'],
                'services_title'=> 'Mentorship Programs',
                'services_intro'=> 'Fundraising, product, growth, exits — get expert guidance at every stage.',
                'about_page_title' => 'Founder journey',
                'cta_banner_h'  => 'Build something that lasts',
                'cta_banner_sub'=> 'Book a 1-on-1 strategy session.',
                'lead_title'    => 'Apply for mentorship',
                'footer_tagline'=> '{{brand_name}} — Building the future together',
                'testimonials'  => [
                    ['text' => 'Raised our Series A within 6 months of working together.','author' => 'Co-founder, fintech', 'role' => 'Founder', 'rating' => 5],
                    ['text' => 'Saved us from making a fatal pricing mistake.',           'author' => 'CEO, SaaS',           'role' => 'Founder', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Plus Jakarta Sans'],
        ];
    }

    protected function themeSalesMarketing(): array
    {
        return [
            'meta' => [
                'name' => 'Sales & Marketing Coach', 'slug' => 'sales-marketing-coach',
                'description' => 'Energetic, persuasive theme for sales trainers, marketing coaches, and conversion experts.',
                'colors' => ['primary' => '#BE123C', 'accent' => '#EAB308'],
                'fonts'  => ['display' => 'Montserrat', 'body' => 'Inter'],
            ],
            'categories' => ['business'],
            'voice' => [
                'hero_headline' => 'Close more deals. Grow faster.',
                'hero_subhead'  => 'Sales and marketing coaching for B2B teams and solo entrepreneurs.',
                'cta_text'      => 'Book a strategy call',
                'role'          => 'Sales & Marketing Coach',
                'bio'           => 'I train sales teams and solopreneurs to consistently close 3x more deals using proven frameworks.',
                'credentials'   => ['$50M+ in client revenue', 'Former VP Sales at scale-up', 'Author of 2 books'],
                'services_title'=> 'Sales Programs',
                'services_intro'=> 'Cold outreach, closing, marketing — pick your edge.',
                'about_page_title' => 'About {{coach_name}}',
                'cta_banner_h'  => 'Stop guessing. Start closing.',
                'cta_banner_sub'=> 'Get a free sales audit.',
                'lead_title'    => 'Get the audit',
                'footer_tagline'=> '{{brand_name}} — Growth, accelerated',
                'testimonials'  => [
                    ['text' => 'Closed 5 enterprise deals in our first quarter together.', 'author' => 'Sales VP, SaaS', 'role' => 'Client', 'rating' => 5],
                    ['text' => '3x our conversion rate in 90 days.',                       'author' => 'Founder, agency','role' => 'Client', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Montserrat'],
        ];
    }

    protected function themeLifeTransformation(): array
    {
        return [
            'meta' => [
                'name' => 'Life Transformation Coach', 'slug' => 'life-transformation-coach',
                'description' => 'Inspiring, warm theme for life coaches, transformation experts, and personal change facilitators.',
                'colors' => ['primary' => '#F97316', 'accent' => '#EC4899'],
                'fonts'  => ['display' => 'Playfair Display', 'body' => 'Inter'],
            ],
            'categories' => ['life-motivation'],
            'voice' => [
                'hero_headline' => 'Become the person you were meant to be',
                'hero_subhead'  => 'Deep, transformative coaching to help you live your fullest life.',
                'cta_text'      => 'Begin your journey',
                'role'          => 'Life Transformation Coach',
                'bio'           => 'I work with people ready for real change — not surface tweaks. Together we uncover what is holding you back and build the life you actually want.',
                'credentials'   => ['ICF Certified', '500+ transformations', 'Bestselling author'],
                'services_title'=> 'Transformation Programs',
                'services_intro'=> 'Six weeks to six months — choose your depth.',
                'about_page_title' => 'My story',
                'cta_banner_h'  => 'Your next chapter starts now',
                'cta_banner_sub'=> 'Schedule a free discovery call.',
                'lead_title'    => 'Apply for coaching',
                'footer_tagline'=> '{{brand_name}} — Live deliberately',
                'testimonials'  => [
                    ['text' => 'Found the courage to leave a job that was killing me.',  'author' => 'Sarah R.', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'Healed relationships I thought were beyond repair.',     'author' => 'Mike T.',  'role' => 'Client', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'soft', 'hero_align' => 'text-image-left', 'font_family' => 'Playfair Display'],
        ];
    }

    protected function themeMotivationalSpeaker(): array
    {
        return [
            'meta' => [
                'name' => 'Motivational Speaker', 'slug' => 'motivational-speaker',
                'description' => 'Bold, magnetic theme for motivational speakers, keynote presenters, and high-energy coaches.',
                'colors' => ['primary' => '#7E22CE', 'accent' => '#FACC15'],
                'fonts'  => ['display' => 'Bebas Neue', 'body' => 'Open Sans'],
            ],
            'categories' => ['life-motivation'],
            'voice' => [
                'hero_headline' => 'IGNITE YOUR POTENTIAL',
                'hero_subhead'  => 'Keynote speaker, mindset coach, and movement leader. Let me light the fire.',
                'cta_text'      => 'Book me to speak',
                'role'          => 'Speaker & Mindset Coach',
                'bio'           => 'I move audiences from where they are to where they want to be — through stories, frameworks, and an unmissable presence on stage.',
                'credentials'   => ['100+ keynote events', 'TEDx speaker', 'Author of bestseller'],
                'services_title'=> 'Speaking & Coaching',
                'services_intro'=> 'Keynotes, workshops, group coaching — let us light the fire.',
                'about_page_title' => 'MEET THE SPEAKER',
                'cta_banner_h'  => 'Book me to ignite your event',
                'cta_banner_sub'=> 'Custom keynote programs available.',
                'lead_title'    => 'Speaking inquiry',
                'footer_tagline'=> '{{brand_name}} — Move people. Change lives.',
                'testimonials'  => [
                    ['text' => 'Best keynote our company has ever booked. The team is still talking about it.','author' => 'Chief People Officer', 'role' => 'Event host', 'rating' => 5],
                    ['text' => 'Standing ovation. Need we say more?',                                          'author' => 'Conference organizer',  'role' => 'Host',      'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'dark', 'hero_align' => 'text-center', 'font_family' => 'Bebas Neue'],
        ];
    }

    protected function themePersonalGrowth(): array
    {
        return [
            'meta' => [
                'name' => 'Personal Growth Mentor', 'slug' => 'personal-growth-mentor',
                'description' => 'Thoughtful, reassuring theme for personal growth coaches, habit experts, and self-development mentors.',
                'colors' => ['primary' => '#1E3A8A', 'accent' => '#10B981'],
                'fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Nunito'],
            ],
            'categories' => ['life-motivation'],
            'voice' => [
                'hero_headline' => 'Grow a little every day',
                'hero_subhead'  => 'Practical personal development for people who want lasting change without the hype.',
                'cta_text'      => 'Start growing',
                'role'          => 'Personal Growth Mentor',
                'bio'           => 'I help thoughtful people build sustainable habits, navigate transitions, and grow into the person they want to be.',
                'credentials'   => ['Behavioral coach', 'Habit researcher', '10+ years mentoring'],
                'services_title'=> 'Growth Programs',
                'services_intro'=> 'Small steps. Real change. Sustainable progress.',
                'about_page_title' => 'About {{coach_name}}',
                'cta_banner_h'  => 'Start your growth journey',
                'cta_banner_sub'=> 'Free 15-minute strategy call.',
                'lead_title'    => 'Get in touch',
                'footer_tagline'=> '{{brand_name}} — Better every day',
                'testimonials'  => [
                    ['text' => 'Built habits I thought I never could. One year in, still going.',  'author' => 'Aakash M.', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'Calm, kind, and incredibly practical guidance.',                   'author' => 'Tanya S.',  'role' => 'Client', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Plus Jakarta Sans'],
        ];
    }

    protected function themeMindfulnessTherapist(): array
    {
        return [
            'meta' => [
                'name' => 'Mindfulness Therapist', 'slug' => 'mindfulness-therapist',
                'description' => 'Gentle, healing theme for mindfulness-based therapists, MBSR teachers, and trauma-informed counselors.',
                'colors' => ['primary' => '#A78BFA', 'accent' => '#FEF3C7'],
                'fonts'  => ['display' => 'Merriweather', 'body' => 'Lato'],
            ],
            'categories' => ['mental-health'],
            'voice' => [
                'hero_headline' => 'A gentle space to heal',
                'hero_subhead'  => 'Mindfulness-based therapy with compassion, presence, and evidence-backed practice.',
                'cta_text'      => 'Book a session',
                'role'          => 'Licensed Therapist · MBSR Teacher',
                'bio'           => 'I provide a safe, evidence-based space for people working through anxiety, stress, trauma, and life transitions.',
                'credentials'   => ['LMHC Licensed', 'MBSR Certified', 'Trauma-informed practice'],
                'services_title'=> 'Therapy Services',
                'services_intro'=> 'Individual therapy, group sessions, and self-paced courses.',
                'about_page_title' => 'About {{coach_name}}',
                'cta_banner_h'  => 'You deserve gentle support',
                'cta_banner_sub'=> 'Book a free 15-min consultation.',
                'lead_title'    => 'Request a consultation',
                'footer_tagline'=> '{{brand_name}} — Heal at your own pace',
                'testimonials'  => [
                    ['text' => 'Helped me through the hardest year of my life.', 'author' => 'Anonymous', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'A truly safe space to be myself.',               'author' => 'Anonymous', 'role' => 'Client', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'soft', 'hero_align' => 'text-image-right', 'font_family' => 'Merriweather'],
        ];
    }

    protected function themeCounselingPractice(): array
    {
        return [
            'meta' => [
                'name' => 'Counseling Practice', 'slug' => 'counseling-practice',
                'description' => 'Professional, trustworthy theme for licensed counselors, psychologists, and couples therapists.',
                'colors' => ['primary' => '#166534', 'accent' => '#FEF3C7'],
                'fonts'  => ['display' => 'Playfair Display', 'body' => 'Open Sans'],
            ],
            'categories' => ['mental-health'],
            'voice' => [
                'hero_headline' => 'Compassionate counseling, evidence-based care',
                'hero_subhead'  => 'Licensed counseling for individuals, couples, and families.',
                'cta_text'      => 'Schedule appointment',
                'role'          => 'Licensed Counselor',
                'bio'           => 'I help clients navigate anxiety, depression, relationship issues, and life transitions using evidence-based therapeutic approaches.',
                'credentials'   => ['Licensed LCSW', 'CBT Certified', 'EFT for couples'],
                'services_title'=> 'Counseling Services',
                'services_intro'=> 'Confidential, professional, and tailored to your needs.',
                'about_page_title' => 'About my practice',
                'cta_banner_h'  => 'Taking the first step takes courage',
                'cta_banner_sub'=> 'Free 20-min consultation.',
                'lead_title'    => 'Book a consultation',
                'footer_tagline'=> '{{brand_name}} — Counseling & Therapy',
                'testimonials'  => [
                    ['text' => 'Professional, kind, and incredibly effective.',  'author' => 'Anonymous', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'Saved our marriage. Truly.',                      'author' => 'Anonymous', 'role' => 'Couple', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'soft', 'hero_align' => 'text-image-right', 'font_family' => 'Playfair Display'],
        ];
    }

    protected function themeAcademicTutor(): array
    {
        return [
            'meta' => [
                'name' => 'Academic Tutor', 'slug' => 'academic-tutor',
                'description' => 'Scholarly, organized theme for academic tutors, test prep coaches, and subject matter experts.',
                'colors' => ['primary' => '#1D4ED8', 'accent' => '#FBBF24'],
                'fonts'  => ['display' => 'Merriweather', 'body' => 'Inter'],
            ],
            'categories' => ['education-tutoring'],
            'voice' => [
                'hero_headline' => 'Expert tutoring that works',
                'hero_subhead'  => 'Personalized academic coaching for students who want to excel.',
                'cta_text'      => 'Book a session',
                'role'          => 'Academic Tutor',
                'bio'           => 'I help students master challenging subjects, prepare for exams, and develop the study habits that lead to long-term success.',
                'credentials'   => ['IIT graduate', '10+ years tutoring', '500+ students mentored'],
                'services_title'=> 'Tutoring Programs',
                'services_intro'=> 'From foundational concepts to advanced exam prep.',
                'about_page_title' => 'About me',
                'cta_banner_h'  => 'Ready to ace your exams?',
                'cta_banner_sub'=> 'Free 30-min trial session.',
                'lead_title'    => 'Schedule a trial',
                'footer_tagline'=> '{{brand_name}} — Learning that lasts',
                'testimonials'  => [
                    ['text' => 'Top 1% in JEE — thank you for the guidance!',       'author' => 'Aarav K.',  'role' => 'Student', 'rating' => 5],
                    ['text' => 'Math finally makes sense to me.',                    'author' => 'Riya P.',   'role' => 'Student', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Merriweather'],
        ];
    }

    protected function themeLanguageLearning(): array
    {
        return [
            'meta' => [
                'name' => 'Language Learning Coach', 'slug' => 'language-learning-coach',
                'description' => 'Friendly, global theme for language teachers, polyglot mentors, and conversation coaches.',
                'colors' => ['primary' => '#F87171', 'accent' => '#14B8A6'],
                'fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Nunito'],
            ],
            'categories' => ['education-tutoring'],
            'voice' => [
                'hero_headline' => 'Speak a new language with confidence',
                'hero_subhead'  => 'Live conversation coaching that gets you speaking from day one.',
                'cta_text'      => 'Start free trial',
                'role'          => 'Language Coach',
                'bio'           => 'I help learners cross the gap from "studying" to actually speaking — fluently, naturally, and with confidence.',
                'credentials'   => ['Native speaker', 'CELTA certified', '1000+ students taught'],
                'services_title'=> 'Language Programs',
                'services_intro'=> 'Beginner to advanced — pick your level.',
                'about_page_title' => 'Bonjour! Namaste! Hola!',
                'cta_banner_h'  => 'Ready to speak fluently?',
                'cta_banner_sub'=> 'Try a free first lesson.',
                'lead_title'    => 'Book a trial lesson',
                'footer_tagline'=> '{{brand_name}} — One world, many voices',
                'testimonials'  => [
                    ['text' => 'Spoke Spanish to a native within 3 months. Mind blown.', 'author' => 'David S.', 'role' => 'Student', 'rating' => 5],
                    ['text' => 'Fun, structured, and effective. Highly recommend.',       'author' => 'Mia T.',   'role' => 'Student', 'rating' => 5],
                ],
                'has_recorded_preview' => true,
                'recorded_title'       => 'Self-paced lessons',
                'recorded_intro'       => 'Try a free lesson preview — hear my teaching style before you commit.',
            ],
            'style' => ['hero_preset' => 'soft', 'hero_align' => 'text-image-right', 'font_family' => 'Plus Jakarta Sans'],
        ];
    }

    protected function themeDietician(): array
    {
        return [
            'meta' => [
                'name' => 'Dietician & Nutrition Expert', 'slug' => 'dietician-nutrition',
                'description' => 'Fresh, healthy theme for dieticians, sports nutritionists, and certified nutrition experts.',
                'colors' => ['primary' => '#059669', 'accent' => '#FB923C'],
                'fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Inter'],
            ],
            'categories' => ['health-nutrition'],
            'voice' => [
                'hero_headline' => 'Eat well. Live better.',
                'hero_subhead'  => 'Personalized nutrition plans backed by science and built for your real life.',
                'cta_text'      => 'Book a consultation',
                'role'          => 'Registered Dietician',
                'bio'           => 'I create realistic, sustainable nutrition plans that work for your goals, your body, and your kitchen.',
                'credentials'   => ['Registered Dietician', 'MSc Nutrition', '1000+ clients'],
                'services_title'=> 'Nutrition Programs',
                'services_intro'=> 'Weight management, sports nutrition, clinical conditions — tailored plans.',
                'about_page_title' => 'About {{coach_name}}',
                'cta_banner_h'  => 'Eat better. Feel better.',
                'cta_banner_sub'=> 'Free initial 20-min consultation.',
                'lead_title'    => 'Book your consultation',
                'footer_tagline'=> '{{brand_name}} — Nutrition that nourishes',
                'testimonials'  => [
                    ['text' => 'Lost 15 kg sustainably. No fad diets.',  'author' => 'Kavita J.', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'My energy levels have transformed.',     'author' => 'Rajat B.',  'role' => 'Client', 'rating' => 5],
                ],
                'has_recorded_preview' => true,
                'recorded_title'       => 'Nutrition programs',
                'recorded_intro'       => 'Free 1-minute preview — see what is inside each program before buying.',
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Plus Jakarta Sans'],
        ];
    }

    protected function themeWellnessNutrition(): array
    {
        return [
            'meta' => [
                'name' => 'Wellness Nutrition Studio', 'slug' => 'wellness-nutrition-studio',
                'description' => 'Wholesome, organic theme for holistic nutritionists, wellness coaches, and gut health experts.',
                'colors' => ['primary' => '#65A30D', 'accent' => '#FEF9C3'],
                'fonts'  => ['display' => 'Playfair Display', 'body' => 'Lato'],
            ],
            'categories' => ['health-nutrition'],
            'voice' => [
                'hero_headline' => 'Holistic nutrition for whole-body wellness',
                'hero_subhead'  => 'Food-as-medicine plans that heal from the inside out.',
                'cta_text'      => 'Book a discovery call',
                'role'          => 'Holistic Nutritionist',
                'bio'           => 'I combine functional nutrition, gut health science, and lifestyle medicine to help you feel like yourself again.',
                'credentials'   => ['Functional Nutrition Cert', 'Gut Health Specialist', '8+ years practice'],
                'services_title'=> 'Wellness Programs',
                'services_intro'=> 'Whole-body wellness, naturally.',
                'about_page_title' => 'My approach',
                'cta_banner_h'  => 'Heal naturally',
                'cta_banner_sub'=> 'Free gut-health assessment.',
                'lead_title'    => 'Request the assessment',
                'footer_tagline'=> '{{brand_name}} — Food is medicine',
                'testimonials'  => [
                    ['text' => 'Years of digestive issues — gone in 4 months.', 'author' => 'Suneeta L.', 'role' => 'Client', 'rating' => 5],
                    ['text' => 'Truly holistic and effective.',                  'author' => 'Arjun D.',   'role' => 'Client', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'soft', 'hero_align' => 'text-image-left', 'font_family' => 'Playfair Display'],
        ];
    }

    protected function themeHolisticWellness(): array
    {
        return [
            'meta' => [
                'name' => 'Holistic Wellness', 'slug' => 'holistic-wellness',
                'description' => 'Versatile, warm theme suitable for any coaching practice — life coaching, mental health, holistic wellness.',
                'colors' => ['primary' => '#10B981', 'accent' => '#EC4899'],
                'fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Inter'],
            ],
            'categories' => ['generic', 'mental-health'],
            'voice' => [
                'hero_headline' => 'Welcome to {{brand_name}}',
                'hero_subhead'  => 'Personalized coaching to help you reach your goals.',
                'cta_text'      => 'Get started',
                'role'          => 'Certified Coach',
                'bio'           => 'I help clients identify what they truly want and build the practical steps to get there.',
                'credentials'   => ['Certified Coach', '8+ years experience'],
                'services_title'=> 'Coaching Programs',
                'services_intro'=> 'Pick the path that fits where you are right now.',
                'about_page_title' => 'About me',
                'cta_banner_h'  => 'Ready to start?',
                'cta_banner_sub'=> 'Book a free intro call.',
                'lead_title'    => 'Get in touch',
                'footer_tagline'=> '{{brand_name}}',
                'testimonials'  => [],
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Plus Jakarta Sans'],
        ];
    }

    protected function themeModernCoachPro(): array
    {
        return [
            'meta' => [
                'name' => 'Modern Coach Pro', 'slug' => 'modern-coach-pro',
                'description' => 'Clean, modern brand-default theme. A safe, professional choice for any coaching vertical.',
                'colors' => ['primary' => '#6366F1', 'accent' => '#8B5CF6'],
                'fonts'  => ['display' => 'Plus Jakarta Sans', 'body' => 'Inter'],
            ],
            'categories' => ['generic'],
            'voice' => [
                'hero_headline' => 'Coaching that creates real change',
                'hero_subhead'  => 'Professional, evidence-based coaching with {{coach_name}}.',
                'cta_text'      => 'Book a session',
                'role'          => 'Professional Coach',
                'bio'           => 'I work with clients across industries to unlock potential, build clarity, and drive measurable progress.',
                'credentials'   => ['Certified Coach', 'Proven framework', 'Results-driven'],
                'services_title'=> 'Coaching Services',
                'services_intro'=> 'Pick the engagement that fits your goals.',
                'about_page_title' => 'About {{coach_name}}',
                'cta_banner_h'  => 'Ready to make progress?',
                'cta_banner_sub'=> 'Free 30-min strategy call.',
                'lead_title'    => 'Get in touch',
                'footer_tagline'=> '{{brand_name}} — Coaching for results',
                'testimonials'  => [
                    ['text' => 'Practical, professional, and life-changing.', 'author' => 'Client', 'role' => 'Professional', 'rating' => 5],
                ],
            ],
            'style' => ['hero_preset' => 'brand', 'hero_align' => 'text-image-right', 'font_family' => 'Plus Jakarta Sans'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // GENERIC THEME BUILDER — turns one config into Theme + 4 pages + sections
    // ──────────────────────────────────────────────────────────────────

    protected function createTheme(array $config, int $sortOrder): void
    {
        $meta   = $config['meta'];
        $voice  = $config['voice'];
        $style  = $config['style'] ?? [];

        // Upsert the Theme row
        $theme = Theme::updateOrCreate(
            ['slug' => $meta['slug']],
            [
                'name'           => $meta['name'],
                'description'    => $meta['description']  ?? null,
                'default_colors' => $meta['colors'],
                'default_fonts'  => $meta['fonts'],
                'is_enabled'     => true,
                'is_premium'     => $meta['is_premium'] ?? false,
                'version'        => '1.0',
                'sort_order'     => $sortOrder,
            ]
        );

        // Categories
        $catIds = collect($config['categories'])
            ->map(fn ($slug) => $this->cats[$slug] ?? null)
            ->filter()->values()->toArray();
        $theme->categories()->sync($catIds);

        // Wipe + reseed pages (idempotent)
        foreach ($theme->pages as $p) {
            $p->sections()->delete();
            $p->delete();
        }

        foreach ($this->buildPages($voice, $style) as $pageData) {
            $sections = $pageData['sections'];
            unset($pageData['sections']);
            $page = $theme->pages()->create($pageData);
            foreach ($sections as $idx => $section) {
                $page->sections()->create([
                    'section_type'    => $section['type'],
                    'section_version' => 'v1',
                    'sort_order'      => $idx,
                    'is_required'     => $section['required'] ?? false,
                    'content_json'    => $section['content'] ?? [],
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // PAGE BUILDERS — generic templates parametrized by voice + style
    // ──────────────────────────────────────────────────────────────────

    protected function buildPages(array $v, array $s): array
    {
        return [
            $this->pageHome($v, $s),
            $this->pageAbout($v, $s),
            $this->pageServices($v, $s),
            $this->pageContact($v, $s),
        ];
    }

    protected function pageHome(array $v, array $s): array
    {
        $stats = $v['stats'] ?? [
            ['number' => '500', 'suffix' => '+', 'label' => 'Clients helped',     'icon' => 'fa-solid fa-users'],
            ['number' => '10',  'suffix' => '+', 'label' => 'Years experience',   'icon' => 'fa-solid fa-award'],
            ['number' => '4.9', 'suffix' => '',  'label' => 'Average rating',     'icon' => 'fa-solid fa-star'],
        ];
        // Video-first themes get a dedicated "Recorded Courses with 60s preview" section
        // BEFORE the regular Services Grid. Coaches in fitness, yoga, language, nutrition
        // benefit most from showing a clip-then-buy flow.
        $hasRecordedPreview = $v['has_recorded_preview'] ?? false;

        return [
            'slug' => 'home', 'page_type' => 'home', 'title' => 'Home', 'sort_order' => 0, 'is_required' => true,
            'meta_title'       => '{{brand_name}}',
            'meta_description' => substr($v['hero_subhead'] ?? '', 0, 160),
            'sections' => array_values(array_filter([
                ['type' => 'hero_v1', 'required' => true, 'content' => [
                    'headline'           => $v['hero_headline'],
                    'subhead'            => $v['hero_subhead'],
                    'cta_text'           => $v['cta_text'],
                    'cta_url'            => '#contact',
                    'background_variant' => 'gradient',
                    'layout'             => $s['hero_align'] ?? 'text-image-right',
                    '_preset'            => $s['hero_preset'] ?? 'brand',
                    '_font_family'       => $s['font_family'] ?? 'inherit',
                ]],
                ['type' => 'about_v1', 'content' => [
                    'name'        => '{{coach_name}}',
                    'role'        => $v['role'],
                    'bio'         => $v['bio'],
                    'credentials' => $v['credentials'] ?? [],
                ]],
                ['type' => 'stats_v1', 'content' => ['stats' => $stats]],
                // Recorded Courses with 60s preview — only for video-first themes
                $hasRecordedPreview ? ['type' => 'recorded_courses_v1', 'content' => [
                    'title'           => $v['recorded_title']    ?? 'On-demand courses',
                    'intro'           => $v['recorded_intro']    ?? 'Watch a free 1-minute preview, then add to cart.',
                    'preview_seconds' => '60',
                    'columns'         => '3',
                    'limit'           => '6',
                    'show_rating'     => true,
                    'show_enrollment' => true,
                    'cta_text'        => 'Add to cart',
                ]] : null,
                ['type' => 'services_grid_v1', 'content' => [
                    'title'   => $v['services_title']  ?? 'Services',
                    'intro'   => $v['services_intro']  ?? '',
                    'source'  => 'auto-from-courses',
                    'columns' => '3',
                ]],
                ['type' => 'testimonials_v1', 'content' => [
                    'title'  => 'What clients say',
                    'layout' => 'grid',
                    'quotes' => $v['testimonials'] ?? [],
                ]],
                ['type' => 'cta_banner_v1', 'content' => [
                    'headline' => $v['cta_banner_h']   ?? 'Ready to start?',
                    'subhead'  => $v['cta_banner_sub'] ?? '',
                    'cta_text' => $v['cta_text'],
                    'cta_url'  => '#contact',
                    'variant'  => 'brand',
                ]],
                ['type' => 'lead_form_v1', 'content' => [
                    'title' => $v['lead_title'] ?? 'Get in touch',
                    'fields' => [
                        ['key' => 'first_name', 'label' => 'Your name', 'type' => 'text',     'required' => true],
                        ['key' => 'last_name',  'label' => 'Last name', 'type' => 'text',     'required' => false],
                        ['key' => 'email',      'label' => 'Email',     'type' => 'email',    'required' => true],
                        ['key' => 'phone',      'label' => 'Phone',     'type' => 'tel',      'required' => true],
                        ['key' => 'message',    'label' => 'Message',   'type' => 'textarea', 'required' => false],
                    ],
                    'submit_text' => $v['cta_text'],
                ]],
                ['type' => 'footer_v1', 'required' => true, 'content' => [
                    'tagline'   => $v['footer_tagline'] ?? '{{brand_name}}',
                    'copyright' => 'All rights reserved.',
                ]],
            ])),
        ];
    }

    protected function pageAbout(array $v, array $s): array
    {
        $stats = $v['stats'] ?? [
            ['number' => '500', 'suffix' => '+', 'label' => 'Clients helped',   'icon' => 'fa-solid fa-users'],
            ['number' => '10',  'suffix' => '+', 'label' => 'Years experience', 'icon' => 'fa-solid fa-award'],
            ['number' => '4.9', 'suffix' => '',  'label' => 'Average rating',   'icon' => 'fa-solid fa-star'],
        ];
        return [
            'slug' => 'about', 'page_type' => 'about', 'title' => 'About', 'sort_order' => 1,
            'sections' => [
                ['type' => 'hero_v1', 'content' => [
                    'headline' => $v['about_page_title'] ?? 'About {{coach_name}}',
                    'layout'   => 'text-center',
                    '_preset'  => $s['hero_preset'] ?? 'brand',
                    '_font_family' => $s['font_family'] ?? 'inherit',
                ]],
                ['type' => 'about_v1', 'content' => [
                    'name'        => '{{coach_name}}',
                    'role'        => $v['role'],
                    'bio'         => $v['bio'],
                    'credentials' => $v['credentials'] ?? [],
                ]],
                ['type' => 'stats_v1', 'content' => ['stats' => $stats]],
                ['type' => 'testimonials_v1', 'content' => ['title' => 'Client outcomes', 'quotes' => $v['testimonials'] ?? []]],
                ['type' => 'cta_banner_v1', 'content' => [
                    'headline' => $v['cta_banner_h']   ?? 'Ready to start?',
                    'cta_text' => $v['cta_text'],
                    'cta_url'  => '/contact',
                    'variant'  => 'brand',
                ]],
                ['type' => 'footer_v1', 'content' => ['tagline' => $v['footer_tagline'] ?? '{{brand_name}}']],
            ],
        ];
    }

    protected function pageServices(array $v, array $s): array
    {
        return [
            'slug' => 'services', 'page_type' => 'services', 'title' => 'Services', 'sort_order' => 2,
            'sections' => [
                ['type' => 'hero_v1', 'content' => [
                    'headline' => $v['services_title'] ?? 'Services',
                    'subhead'  => $v['services_intro'] ?? '',
                    'layout'   => 'text-center',
                    '_preset'  => $s['hero_preset'] ?? 'brand',
                    '_font_family' => $s['font_family'] ?? 'inherit',
                ]],
                ['type' => 'services_grid_v1', 'content' => ['source' => 'auto-from-courses', 'columns' => '3']],
                ['type' => 'faq_v1', 'content' => ['title' => 'Common questions', 'items' => [
                    ['question' => 'How long is a typical engagement?', 'answer' => 'Most programs run between 4 and 12 weeks, depending on your goals.'],
                    ['question' => 'Do you offer 1:1 or group sessions?', 'answer' => 'Both — choose what works best for you.'],
                    ['question' => 'Is there a refund policy?', 'answer' => 'Yes — see terms on each program.'],
                ]]],
                ['type' => 'cta_banner_v1', 'content' => [
                    'headline' => $v['cta_banner_h'] ?? 'Pick your program',
                    'cta_text' => $v['cta_text'],
                    'cta_url'  => '/contact',
                    'variant'  => 'brand',
                ]],
                ['type' => 'footer_v1', 'content' => ['tagline' => $v['footer_tagline'] ?? '{{brand_name}}']],
            ],
        ];
    }

    protected function pageContact(array $v, array $s): array
    {
        return [
            'slug' => 'contact', 'page_type' => 'contact', 'title' => 'Contact', 'sort_order' => 3,
            'sections' => [
                ['type' => 'hero_v1', 'content' => [
                    'headline' => 'Get in touch',
                    'subhead'  => $v['cta_banner_sub'] ?? '',
                    'layout'   => 'text-center',
                    '_preset'  => $s['hero_preset'] ?? 'brand',
                    '_font_family' => $s['font_family'] ?? 'inherit',
                ]],
                ['type' => 'contact_v1', 'content' => [
                    'title' => 'Visit us',
                    'email' => '{{support_email}}',
                    'phone' => '{{coach_phone}}',
                    'hours' => 'Mon–Fri · 9 AM – 6 PM',
                ]],
                ['type' => 'lead_form_v1', 'content' => [
                    'title' => $v['lead_title'] ?? 'Send a message',
                    'fields' => [
                        ['key' => 'first_name', 'label' => 'Your name', 'type' => 'text',     'required' => true],
                        ['key' => 'last_name',  'label' => 'Last name', 'type' => 'text',     'required' => false],
                        ['key' => 'email',      'label' => 'Email',     'type' => 'email',    'required' => true],
                        ['key' => 'phone',      'label' => 'Phone',     'type' => 'tel',      'required' => true],
                        ['key' => 'message',    'label' => 'How can we help?', 'type' => 'textarea', 'required' => false],
                    ],
                    'submit_text' => $v['cta_text'],
                ]],
                ['type' => 'footer_v1', 'content' => ['tagline' => $v['footer_tagline'] ?? '{{brand_name}}']],
            ],
        ];
    }
}
