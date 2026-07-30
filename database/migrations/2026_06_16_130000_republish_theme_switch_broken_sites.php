<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-16 — GLOBAL recovery for the theme-switch 404 (white-label, all coaches).
 *
 * The pre-fix theme switch ('replace') recreated every page with
 * is_published=false, so any coach who switched themes saw their whole public
 * site 404 (CoachSitePublicController gates public pages on published()).
 *
 * This one-time migration re-publishes the pages of coaches who were demonstrably
 * LIVE but are now fully draft — the exact signature of the bug:
 *   • their SITE is published  (coach_landing_pages.is_published = 1 = live intent), AND
 *   • EVERY non-deleted page is currently a draft (SUM(is_published) = 0).
 *
 * Coaches still in onboarding (site not published) and live sites that still have
 * at least one published page are left untouched. Idempotent — re-running does
 * nothing once a site has any published page. Pairs with the forward fix in
 * ThemeApplicator (a switch on a live site now keeps it live).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_pages') || ! Schema::hasTable('coach_landing_pages')) {
            return;
        }

        $hasPageSoftDelete = Schema::hasColumn('coach_pages', 'deleted_at');

        $broken = DB::table('coach_pages as cp')
            ->join('coach_landing_pages as clp', function ($j) {
                $j->on('clp.added_by', '=', 'cp.coach_id')->where('clp.is_published', 1);
            })
            ->when($hasPageSoftDelete, fn ($q) => $q->whereNull('cp.deleted_at'))
            ->groupBy('cp.coach_id')
            ->havingRaw('SUM(cp.is_published) = 0 AND COUNT(*) > 0')
            ->pluck('cp.coach_id');

        if ($broken->isEmpty()) {
            return;
        }

        DB::table('coach_pages')
            ->whereIn('coach_id', $broken->all())
            ->when($hasPageSoftDelete, fn ($q) => $q->whereNull('deleted_at'))
            ->update(['is_published' => 1, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // No-op: we never want to re-break the recovered sites.
    }
};
