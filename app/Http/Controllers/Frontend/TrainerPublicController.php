<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CoachDomain;
use App\Models\CoachLandingPage;
use App\Models\CoachTrainer;
use App\Models\User;
use App\Services\BrandResolver;

/**
 * Public, tenant-scoped Trainer Detail Page (2026-07-15, Phase 1).
 *
 * /trainers/{slug} is reserved across every coach surface (subdomain, custom
 * domain, path). The coach is resolved FROM THE HOST and only that coach's
 * ACTIVE trainer is served (the owning coach may preview an inactive one). On
 * the platform's own domain there is no coach → 404 (trainers are a coach-site
 * feature only). Mirrors CoachBlogPublicController's host-resolution contract.
 */
class TrainerPublicController extends Controller
{
    /**
     * Owning coach id from the current host:
     *   1. request attribute stamped by ResolveCoachByDomain
     *   2. custom-domain lookup
     *   3. subdomain ({sub}.{coach_domain})
     * 0 when this is not a coach host.
     */
    private function resolveCoachId(): int
    {
        $stamped = (int) request()->attributes->get('resolved_coach_id');
        if ($stamped > 0) {
            return $stamped;
        }

        $host = request()->getHost();

        $byDomain = (int) (CoachDomain::coachIdForHost($host) ?? 0);
        if ($byDomain > 0) {
            return $byDomain;
        }

        $coachDomain = (string) config('app.coach_domain');
        if ($coachDomain && str_ends_with($host, '.' . $coachDomain)) {
            $sub  = substr($host, 0, -1 * (strlen($coachDomain) + 1));
            $site = CoachLandingPage::where('slug', $sub)
                ->orWhere('subdomain', $host)
                ->first();
            if ($site) {
                return (int) $site->added_by;
            }
        }

        return 0;
    }

    /** Host-resolved trainer detail (clean /trainers/{slug}). */
    public function show(string $slug)
    {
        $coachId = $this->resolveCoachId();
        abort_if($coachId <= 0, 404);

        return $this->renderForCoach($coachId, $slug);
    }

    /** Path surface (/coach/{site_slug}/trainers/{slug}) — localhost / no-DNS. */
    public function showOnPath(string $site_slug, string $slug)
    {
        $site = CoachLandingPage::where('slug', $site_slug)->first();
        abort_if(! $site, 404);

        return $this->renderForCoach((int) $site->added_by, $slug);
    }

    /** Resolve + render a single coach trainer inside the coach-site master layout. */
    private function renderForCoach(int $coachId, string $slug)
    {
        $isOwner = $this->viewerIsOwner($coachId);

        $query = CoachTrainer::forCoach($coachId)->where('slug', $slug);
        if (! $isOwner) {
            $query->active(); // visitors: active only
        }
        $trainer = $query->first();
        abort_if(! $trainer, 404);

        // Packages: active for visitors, all for the previewing owner.
        $pkgQuery = $trainer->packages()->orderBy('sort_order')->orderBy('id');
        if (! $isOwner) {
            $pkgQuery->where('is_active', true);
        }
        $packages = $pkgQuery->get();

        $coach = User::find($coachId);
        abort_if(! $coach, 404);

        $brand = app(BrandResolver::class)->forCoach($coachId);
        $site  = CoachLandingPage::where('added_by', $coachId)->orderBy('id')->first();

        // Global header/menu — same nav used across the coach's website.
        $siteNav = app(CoachSitePublicController::class)->navForCoach($coachId, $site);

        // Synthetic "page" so the shared master layout can build SEO tags + expose coach_id.
        $metaBits = array_filter([$trainer->specialisation, $trainer->experience ? $trainer->experience . ' experience' : null]);
        $page = (object) [
            'coach_id'         => $coachId,
            'title'            => $trainer->name,
            'slug'             => 'trainers/' . $trainer->slug,
            'meta_title'       => $trainer->name . ($trainer->specialisation ? ' — ' . $trainer->specialisation : ''),
            'meta_description' => \Illuminate\Support\Str::limit(strip_tags((string) ($trainer->bio ?: implode(' · ', $metaBits))), 155),
            'og_image'         => $trainer->photo ? \Illuminate\Support\Facades\Storage::url($trainer->photo) : null,
            'robots'           => $trainer->is_active ? 'index' : 'noindex',
        ];

        $bodyHtml = view('frontend.coach-site.trainer-detail', compact(
            'trainer', 'packages', 'coach', 'brand', 'site'
        ))->render();

        return response()->view('frontend.coach-site.layouts.master', [
            'page'             => $page,
            'site'             => $site,
            'coach'            => $coach,
            'brand'            => $brand,
            'siteNav'          => $siteNav,
            'bodyHtml'         => $bodyHtml,
            'hasFooterSection' => false,
            'isDraftPreview'   => $isOwner && ! $trainer->is_active,
        ]);
    }

    /** The owning coach (or their staff) may preview an inactive trainer. */
    private function viewerIsOwner(int $ownerCoachId): bool
    {
        if (! auth()->check()) {
            return false;
        }
        $u = auth()->user();
        $callerCoachId = $u->role === 'instructor' ? (int) $u->id : (int) ($u->coach_id ?? 0);

        return $callerCoachId === $ownerCoachId;
    }
}
