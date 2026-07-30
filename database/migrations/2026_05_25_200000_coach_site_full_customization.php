<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Coach Site Full Customization (Phase 1.5, audit 2026-05-25 evening)
 *
 * Adds:
 *   - coach_site_settings: one row per coach holding ALL site-wide
 *     settings (analytics, favicon, sticky CTA, WhatsApp button, social
 *     links, custom CSS / head scripts, footer config, SEO defaults).
 *   - landing_sections.is_visible: toggle a section without deleting.
 *   - coach_pages.{is_visible_in_nav, nav_label, nav_external_url}:
 *     fine-grained nav control (hide pages, rename in nav only, add
 *     external links to nav).
 *
 * All additive. Existing data untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── coach_site_settings ─────────────────────────────────────
        if (! Schema::hasTable('coach_site_settings')) {
            Schema::create('coach_site_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id')->unique();

                // Favicon (separate from brand logo)
                $table->string('favicon_url', 500)->nullable();

                // Sticky / floating CTA button (e.g. "Book a Call")
                $table->boolean('sticky_cta_enabled')->default(false);
                $table->string('sticky_cta_text', 50)->nullable();
                $table->string('sticky_cta_url', 500)->nullable();

                // WhatsApp floating button
                $table->boolean('whatsapp_enabled')->default(false);
                $table->string('whatsapp_number', 30)->nullable();
                $table->string('whatsapp_message', 200)->nullable();

                // Social links (footer + sharing)
                $table->string('social_facebook',  500)->nullable();
                $table->string('social_instagram', 500)->nullable();
                $table->string('social_youtube',   500)->nullable();
                $table->string('social_twitter',   500)->nullable();
                $table->string('social_linkedin',  500)->nullable();
                $table->string('social_tiktok',    500)->nullable();

                // Analytics integrations
                $table->string('analytics_ga4_id',         50)->nullable();
                $table->string('analytics_meta_pixel_id',  50)->nullable();
                $table->string('analytics_gtm_id',         50)->nullable();

                // Power-user custom code
                $table->longText('custom_css')->nullable();
                $table->longText('custom_head_scripts')->nullable();
                $table->longText('custom_body_scripts')->nullable();

                // Default SEO
                $table->string('seo_default_title_suffix', 60)->nullable()
                    ->comment('Appended to page meta_title — e.g. " | Yoga Coach"');
                $table->string('seo_default_description',  160)->nullable();
                $table->string('seo_og_image_default',     500)->nullable();

                // Site-wide footer (rendered on all pages — JSON content)
                $table->json('footer_config')->nullable();

                // Nav config (CTA button in top bar, logo override)
                $table->boolean('nav_show_cta')->default(true);
                $table->string('nav_cta_text', 30)->nullable();
                $table->string('nav_cta_url',  500)->nullable();

                $table->timestamps();
                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // ── landing_sections.is_visible ─────────────────────────────
        Schema::table('landing_sections', function (Blueprint $table) {
            if (! $this->hasColumn('landing_sections', 'is_visible')) {
                $table->boolean('is_visible')->default(true)->after('section_version');
            }
        });

        // ── coach_pages.nav controls ─────────────────────────────────
        Schema::table('coach_pages', function (Blueprint $table) {
            if (! $this->hasColumn('coach_pages', 'is_visible_in_nav')) {
                $table->boolean('is_visible_in_nav')->default(true)->after('is_published');
            }
            if (! $this->hasColumn('coach_pages', 'nav_label')) {
                $table->string('nav_label', 60)->nullable()->after('is_visible_in_nav')
                    ->comment('Override the title shown in nav menu — falls back to page title');
            }
            if (! $this->hasColumn('coach_pages', 'nav_external_url')) {
                $table->string('nav_external_url', 500)->nullable()->after('nav_label')
                    ->comment('If set, nav item opens this URL instead of the page itself');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_site_settings');

        Schema::table('landing_sections', function (Blueprint $table) {
            if ($this->hasColumn('landing_sections', 'is_visible')) $table->dropColumn('is_visible');
        });

        Schema::table('coach_pages', function (Blueprint $table) {
            foreach (['is_visible_in_nav', 'nav_label', 'nav_external_url'] as $c) {
                if ($this->hasColumn('coach_pages', $c)) $table->dropColumn($c);
            }
        });
    }

    private function hasColumn(string $table, string $column): bool
    {
        $db = DB::getDatabaseName();
        return (bool) DB::selectOne(
            'SELECT 1 AS x FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$db, $table, $column]
        );
    }
};
