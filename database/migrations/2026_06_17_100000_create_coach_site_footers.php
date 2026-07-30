<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-17 — Dedicated GLOBAL FOOTER per coach (Website Builder enhancement).
 *
 * Previously the "shared" footer was the coach's HOME page footer_v1 section,
 * borrowed by other pages (2026-06-15). That tied the footer conceptually to a
 * page. This introduces a first-class, page-independent global footer:
 *
 *   - coach_site_footers  : ONE row per coach. content_json uses the SAME shape
 *                           as the footer_v1 section (tagline / show_social /
 *                           copyright / link_groups), so the existing
 *                           footer_v1.blade.php renders it unchanged. is_enabled
 *                           lets a coach turn the whole global footer off.
 *   - coach_pages.use_global_footer : per-page opt-out (default ON). A page can
 *                           still define its own footer_v1 section to fully
 *                           override; this flag governs the global-footer
 *                           fallback only.
 *
 * Backfill (idempotent): for every coach that already designed a Home-page
 * footer_v1, copy its content into coach_site_footers so the switch to the
 * dedicated footer loses nothing. Fully guarded — safe to re-run, safe on
 * environments missing the source tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_site_footers')) {
            Schema::create('coach_site_footers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_id');
                $table->json('content_json')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->string('section_version', 10)->default('v1');
                $table->timestamps();

                $table->unique('coach_id', 'ux_coach_site_footer_coach');
                $table->foreign('coach_id', 'fk_coach_site_footer_coach')
                    ->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Per-page opt-out (default ON so existing pages keep showing a footer).
        if (Schema::hasTable('coach_pages') && ! Schema::hasColumn('coach_pages', 'use_global_footer')) {
            Schema::table('coach_pages', function (Blueprint $table) {
                $table->boolean('use_global_footer')->default(true)->after('is_visible_in_nav');
            });
        }

        $this->backfillFromHomeFooters();
    }

    /**
     * Copy each coach's existing Home-page footer_v1 content into the new
     * dedicated global-footer row (only when they don't already have one).
     */
    private function backfillFromHomeFooters(): void
    {
        if (! Schema::hasTable('coach_site_footers')
            || ! Schema::hasTable('coach_pages')
            || ! Schema::hasTable('landing_sections')) {
            return;
        }

        // Home pages that own a footer_v1 section, grouped per coach. We take the
        // first home page per coach (a coach has exactly one home in practice).
        //
        // INNER JOIN users — production has ORPHANED coach_pages rows whose owner
        // (users.id) was deleted but the page lingered. Inserting a
        // coach_site_footers row for such a ghost coach violates the coach_id FK.
        // Joining users skips those orphans cleanly (they have no live site anyway).
        $rows = DB::table('coach_pages as cp')
            ->join('landing_sections as ls', 'ls.coach_page_id', '=', 'cp.id')
            ->join('users as u', 'u.id', '=', 'cp.coach_id')
            ->where('cp.page_type', 'home')
            ->where('ls.section_type', 'footer_v1')
            ->whereNull('cp.deleted_at')
            ->orderBy('cp.coach_id')
            ->orderBy('cp.id')
            ->get(['cp.coach_id', 'ls.content_json']);

        $seen = [];
        foreach ($rows as $row) {
            $coachId = (int) $row->coach_id;
            if (isset($seen[$coachId])) {
                continue; // first home footer per coach wins
            }
            $seen[$coachId] = true;

            $exists = DB::table('coach_site_footers')->where('coach_id', $coachId)->exists();
            if (! $exists) {
                DB::table('coach_site_footers')->insert([
                    'coach_id'        => $coachId,
                    'content_json'    => $row->content_json, // already JSON text
                    'is_enabled'      => 1,
                    'section_version' => 'v1',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            // The footer is now a page-independent GLOBAL element rendered by the
            // public controller. Hide the legacy home footer_v1 section so the
            // home page falls back to the (authoritative) global footer like every
            // other page — otherwise editing the global footer wouldn't reflect on
            // home. Content is preserved (copied above + section not deleted), and
            // the column is nullable-safe. Only home footers are touched.
            if (Schema::hasColumn('landing_sections', 'is_visible')) {
                DB::table('landing_sections as ls')
                    ->join('coach_pages as cp', 'cp.id', '=', 'ls.coach_page_id')
                    ->where('cp.coach_id', $coachId)
                    ->where('cp.page_type', 'home')
                    ->where('ls.section_type', 'footer_v1')
                    ->update(['ls.is_visible' => 0]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: the renderer + builder now depend on these. Dropping
        // would re-break the global footer. Leave in place.
    }
};
