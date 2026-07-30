<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Theme System Foundation (Phase 1 of the White-Label Theme Builder).
 *
 * Introduces:
 *  - themes               : the master theme catalog (Super Admin owned)
 *  - theme_pages          : per-theme page structure (home, about, ...)
 *  - theme_sections       : per-page section structure with default content
 *  - theme_categories     : tag taxonomy (yoga / business / fitness / ...)
 *  - theme_category_pivot : many-to-many (themes ↔ categories)
 *  - theme_applications   : audit trail of which coach applied which theme
 *  - theme_audit_log      : admin action history
 *
 * Plus additive columns on coach_landing_pages to record the source theme.
 *
 * Everything is additive — no existing table data touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('themes')) {
            Schema::create('themes', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->text('description')->nullable();
                $table->string('thumbnail_url', 500)->nullable();
                $table->json('default_colors')->nullable()
                    ->comment('{primary, accent, text, bg}');
                $table->json('default_fonts')->nullable()
                    ->comment('{display, body}');
                $table->boolean('is_enabled')->default(false);
                $table->boolean('is_premium')->default(false);
                $table->string('version', 20)->default('1.0');
                $table->integer('sort_order')->default(0);
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['is_enabled', 'sort_order'], 'idx_enabled_sort');
            });
        }

        if (! Schema::hasTable('theme_categories')) {
            Schema::create('theme_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80);
                $table->string('slug', 100)->unique();
                $table->string('icon', 60)->nullable()->comment('Font Awesome class');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('theme_category_pivot')) {
            Schema::create('theme_category_pivot', function (Blueprint $table) {
                $table->unsignedBigInteger('theme_id');
                $table->unsignedBigInteger('category_id');
                $table->primary(['theme_id', 'category_id']);
                $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
                $table->foreign('category_id')->references('id')->on('theme_categories')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('theme_pages')) {
            Schema::create('theme_pages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('theme_id');
                $table->string('slug', 120);
                $table->enum('page_type', [
                    'home', 'about', 'services', 'pricing',
                    'testimonials', 'contact', 'blog_index', 'custom',
                ]);
                $table->string('title', 160);
                $table->string('meta_title', 60)->nullable();
                $table->string('meta_description', 160)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_required')->default(false)
                    ->comment('If true, coach cannot delete this page after applying theme');
                $table->timestamps();
                $table->unique(['theme_id', 'slug'], 'ux_theme_page_slug');
                $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('theme_sections')) {
            Schema::create('theme_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('theme_page_id');
                $table->string('section_type', 60);
                $table->string('section_version', 10)->default('v1');
                $table->longText('content_json');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_required')->default(false);
                $table->timestamps();
                $table->index(['theme_page_id', 'sort_order'], 'idx_page_order');
                $table->foreign('theme_page_id')->references('id')->on('theme_pages')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('theme_applications')) {
            Schema::create('theme_applications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                $table->unsignedBigInteger('theme_id');
                $table->string('version', 20);
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('superseded_at')->nullable()
                    ->comment('Set when a newer theme application replaces this one');
                $table->timestamps();
                $table->index(['coach_id', 'applied_at'], 'idx_coach_applied');
                $table->foreign('coach_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('theme_id')->references('id')->on('themes');
            });
        }

        if (! Schema::hasTable('theme_audit_log')) {
            Schema::create('theme_audit_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_user_id');
                $table->unsignedBigInteger('theme_id')->nullable();
                $table->string('action', 60)->comment('create / update / enable / disable / delete / clone');
                $table->json('payload')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['theme_id', 'created_at'], 'idx_theme_created');
            });
        }

        // Extend coach_landing_pages with theme reference
        Schema::table('coach_landing_pages', function (Blueprint $table) {
            if (! $this->hasColumn('coach_landing_pages', 'theme_id')) {
                $table->unsignedBigInteger('theme_id')->nullable()->after('added_by');
            }
            if (! $this->hasColumn('coach_landing_pages', 'theme_applied_at')) {
                $table->timestamp('theme_applied_at')->nullable()->after('theme_id');
            }
            if (! $this->hasColumn('coach_landing_pages', 'theme_version')) {
                $table->string('theme_version', 20)->nullable()->after('theme_applied_at');
            }
        });

        // Extend coach_pages so we can link a page back to its source theme_section
        // (lets us "reset to theme default" and "update from theme" later)
        Schema::table('landing_sections', function (Blueprint $table) {
            if (! $this->hasColumn('landing_sections', 'theme_section_origin_id')) {
                $table->unsignedBigInteger('theme_section_origin_id')->nullable()
                    ->after('is_visible')->comment('FK to theme_sections — source row when cloned from a theme');
                $table->index('theme_section_origin_id', 'idx_theme_origin');
            }
        });

        // Mark coach onboarding state — when they picked their first theme
        Schema::table('users', function (Blueprint $table) {
            if (! $this->hasColumn('users', 'onboarding_theme_chosen_at')) {
                $table->timestamp('onboarding_theme_chosen_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->hasColumn('users', 'onboarding_theme_chosen_at')) {
                $table->dropColumn('onboarding_theme_chosen_at');
            }
        });

        Schema::table('landing_sections', function (Blueprint $table) {
            if ($this->hasColumn('landing_sections', 'theme_section_origin_id')) {
                $table->dropIndex('idx_theme_origin');
                $table->dropColumn('theme_section_origin_id');
            }
        });

        Schema::table('coach_landing_pages', function (Blueprint $table) {
            foreach (['theme_id', 'theme_applied_at', 'theme_version'] as $col) {
                if ($this->hasColumn('coach_landing_pages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('theme_audit_log');
        Schema::dropIfExists('theme_applications');
        Schema::dropIfExists('theme_sections');
        Schema::dropIfExists('theme_pages');
        Schema::dropIfExists('theme_category_pivot');
        Schema::dropIfExists('theme_categories');
        Schema::dropIfExists('themes');
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
