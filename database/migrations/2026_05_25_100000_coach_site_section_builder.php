<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Coach Site Section Builder — multi-page custom website (Phase 1, 2026-05-25)
 *
 * Activates the dormant `landing_sections` table and adds full multi-page
 * support so every coach can build a complete branded marketing website
 * (Home / About / Services / Testimonials / Contact / Custom) replacing
 * the legacy single-page GrapesJS Newsletter-preset builder.
 *
 * All changes are additive — no columns dropped, no types retyped.
 * Existing coach_landing_pages.html_content / css_content / json_content
 * blobs are LEFT INTACT so legacy single-page sites keep rendering via
 * the html_passthrough fallback during the 90-day migration window.
 *
 * New tables:
 *   - coach_pages           — N pages per coach (Site = collection of pages)
 *   - coach_page_versions   — last-20 snapshot history per page
 *   - coach_site_page_views — first-party pageview analytics
 *
 * Extended tables (additive only):
 *   - landing_sections          + coach_page_id, section_version (legacy
 *                                 landing_page_id stays for back-compat)
 *   - landing_page_enquiries    + page_id, section_id, custom_fields(JSON)
 *   - orders                    + source, source_page_id, source_section_id
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── coach_pages ────────────────────────────────────────────────
        if (! Schema::hasTable('coach_pages')) {
            Schema::create('coach_pages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                $table->unsignedBigInteger('site_id')->nullable()
                    ->comment('FK to coach_landing_pages — kept as the site root');
                $table->string('slug', 120);
                $table->enum('page_type', [
                    'home', 'about', 'services', 'pricing',
                    'testimonials', 'contact', 'blog_index', 'custom',
                ])->default('custom');
                $table->string('title', 160);
                $table->string('meta_title', 60)->nullable();
                $table->string('meta_description', 160)->nullable();
                $table->string('og_image', 500)->nullable();
                $table->enum('robots', ['index', 'noindex'])->default('index');
                $table->boolean('is_published')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['coach_id', 'slug'], 'ux_coach_slug');
                $table->index(['coach_id', 'is_published'], 'idx_coach_pub');
                $table->index('site_id', 'idx_site');
                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // ── coach_page_versions (snapshot history) ─────────────────────
        if (! Schema::hasTable('coach_page_versions')) {
            Schema::create('coach_page_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_page_id');
                $table->longText('snapshot')->comment('full page + sections JSON snapshot');
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index(['coach_page_id', 'created_at'], 'idx_page_created');
                $table->foreign('coach_page_id')->references('id')->on('coach_pages')->cascadeOnDelete();
            });
        }

        // ── coach_site_page_views (analytics) ──────────────────────────
        if (! Schema::hasTable('coach_site_page_views')) {
            Schema::create('coach_site_page_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                $table->unsignedBigInteger('page_id');
                $table->char('visitor_hash', 64)->comment('sha256(ip + ua + day-bucket)');
                $table->string('referer', 500)->nullable();
                $table->string('utm_source', 100)->nullable();
                $table->string('utm_medium', 100)->nullable();
                $table->string('utm_campaign', 100)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['coach_id', 'created_at'], 'idx_coach_day');
                $table->index(['page_id', 'created_at'], 'idx_page_day');
            });
        }

        // ── landing_sections: activate for multi-page ──────────────────
        Schema::table('landing_sections', function (Blueprint $table) {
            if (! $this->hasColumn('landing_sections', 'coach_page_id')) {
                $table->unsignedBigInteger('coach_page_id')->nullable()->after('landing_page_id');
                $table->index('coach_page_id', 'idx_coach_page');
            }
            if (! $this->hasColumn('landing_sections', 'section_version')) {
                $table->string('section_version', 10)->default('v1')->after('section_type');
            }
        });

        // Make landing_page_id nullable so new sections under coach_page_id
        // don't need a fake landing-page row.
        DB::statement('ALTER TABLE landing_sections MODIFY landing_page_id BIGINT UNSIGNED NULL');

        // ── orders: lead attribution ───────────────────────────────────
        Schema::table('orders', function (Blueprint $table) {
            if (! $this->hasColumn('orders', 'source')) {
                $table->string('source', 50)->nullable()->after('coupon_code');
            }
            if (! $this->hasColumn('orders', 'source_page_id')) {
                $table->unsignedBigInteger('source_page_id')->nullable()->after('source');
            }
            if (! $this->hasColumn('orders', 'source_section_id')) {
                $table->unsignedBigInteger('source_section_id')->nullable()->after('source_page_id');
            }
        });

        // ── landing_page_enquiries: per-section attribution ────────────
        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            if (! $this->hasColumn('landing_page_enquiries', 'page_id')) {
                $table->unsignedBigInteger('page_id')->nullable()->after('landing_page_id');
                $table->index('page_id', 'idx_page');
            }
            if (! $this->hasColumn('landing_page_enquiries', 'section_id')) {
                $table->unsignedBigInteger('section_id')->nullable()->after('page_id');
                $table->index('section_id', 'idx_section');
            }
            if (! $this->hasColumn('landing_page_enquiries', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_page_versions');
        Schema::dropIfExists('coach_site_page_views');
        Schema::dropIfExists('coach_pages');

        Schema::table('landing_sections', function (Blueprint $table) {
            foreach (['coach_page_id', 'section_version'] as $col) {
                if ($this->hasColumn('landing_sections', $col)) {
                    if ($col === 'coach_page_id') {
                        $table->dropIndex('idx_coach_page');
                    }
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['source', 'source_page_id', 'source_section_id'] as $col) {
                if ($this->hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('landing_page_enquiries', function (Blueprint $table) {
            if ($this->hasColumn('landing_page_enquiries', 'section_id')) {
                $table->dropIndex('idx_section');
                $table->dropColumn('section_id');
            }
            if ($this->hasColumn('landing_page_enquiries', 'page_id')) {
                $table->dropIndex('idx_page');
                $table->dropColumn('page_id');
            }
            if ($this->hasColumn('landing_page_enquiries', 'custom_fields')) {
                $table->dropColumn('custom_fields');
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
