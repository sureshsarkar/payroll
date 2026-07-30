<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CoachBlog;
use App\Models\CoachDomain;
use App\Models\CoachLandingPage;
use App\Models\User;
use App\Services\BrandResolver;
use Illuminate\Http\Request;

/**
 * Public, tenant-scoped blog detail for a coach's custom website (2026-06-23).
 *
 * /blog/{slug} is reserved across every coach surface (subdomain, custom
 * domain, path). This controller resolves the coach FROM THE HOST and serves
 * ONLY that coach's published post (the owner can preview drafts). When no
 * coach resolves — i.e. the request is on the PLATFORM's own domain — it
 * delegates to the platform blog controller unchanged, so the marketplace blog
 * keeps working exactly as before.
 */
class CoachBlogPublicController extends Controller
{
    /**
     * Resolve the owning coach id from the current host:
     *   1. custom domain (ResolveCoachByDomain already stamped the request)
     *   2. custom domain lookup as a fallback
     *   3. subdomain (sub.coach_domain)
     * Returns 0 when this is not a coach host (the platform's own domain).
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

        // Subdomain surface: {sub}.{coach_domain}
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

    /** Host-resolved coach blog (clean /blog/{slug} on subdomain + custom domain). */
    public function show(string $slug)
    {
        $coachId = $this->resolveCoachId();

        // Not a coach host → platform blog, unchanged behaviour.
        if ($coachId <= 0) {
            return app(BlogController::class)->show($slug);
        }

        return $this->renderForCoach($coachId, $slug);
    }

    /** Path surface (/coach/{site_slug}/blog/{slug}) — localhost / no-DNS fallback. */
    public function showOnPath(string $site_slug, string $slug)
    {
        $site = CoachLandingPage::where('slug', $site_slug)->first();
        abort_if(! $site, 404);

        return $this->renderForCoach((int) $site->added_by, $slug);
    }

    /** Resolve + render a single coach post inside the coach-site master layout. */
    private function renderForCoach(int $coachId, string $slug)
    {
        $isOwner = $this->viewerIsOwner($coachId);

        $query = CoachBlog::forCoach($coachId)->where('slug', $slug);
        if (! $isOwner) {
            $query->published(); // visitors: published only
        }
        $blog = $query->first();
        abort_if(! $blog, 404);

        // Count a view for genuine visitors (not the owner previewing).
        if (! $isOwner) {
            try { $blog->increment('views'); } catch (\Throwable $e) {}
        }

        $coach = User::find($coachId);
        abort_if(! $coach, 404);

        $brand = app(BrandResolver::class)->forCoach($coachId);
        $site  = CoachLandingPage::where('added_by', $coachId)->orderBy('id')->first(); // F22: deterministic when added_by not unique

        // Reading time (~200 wpm) from the stripped content.
        $words       = str_word_count(strip_tags((string) $blog->content));
        $readMinutes = max(1, (int) ceil($words / 200));

        // Related + recent posts (this coach's published, excluding current).
        $related = CoachBlog::forCoach($coachId)->published()
            ->where('id', '!=', $blog->id)
            ->orderByDesc('published_at')->orderByDesc('created_at')
            ->limit(3)->get();
        $recent = CoachBlog::forCoach($coachId)->published()
            ->where('id', '!=', $blog->id)
            ->orderByDesc('published_at')->orderByDesc('created_at')
            ->limit(5)->get();

        // Global header/menu — same nav used across the coach's website
        // (Issue 1: it was missing on the blog detail page).
        $siteNav = app(CoachSitePublicController::class)->navForCoach($coachId, $site);

        // Synthetic "page" so the shared master layout can build SEO tags.
        $page = (object) [
            'coach_id'         => $coachId,
            'title'            => $blog->title,
            'slug'             => 'blog/' . $blog->slug,
            'meta_title'       => $blog->seo_title ?: $blog->title,
            'meta_description' => $blog->seo_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $blog->short_description), 155),
            'og_image'         => $blog->image ? asset($blog->image) : null,
            'robots'           => $blog->isPublished() ? 'index' : 'noindex',
        ];

        $bodyHtml = view('frontend.coach-site.blog-detail', compact(
            'blog', 'coach', 'brand', 'related', 'recent', 'readMinutes'
        ))->render();

        return response()->view('frontend.coach-site.layouts.master', [
            'page'             => $page,
            'site'             => $site,
            'coach'            => $coach,
            'brand'            => $brand,
            'siteNav'          => $siteNav,
            'bodyHtml'         => $bodyHtml,
            'hasFooterSection' => false,
            'isDraftPreview'   => $isOwner && ! $blog->isPublished(),
        ]);
    }

    /** The owning coach (or their staff) may preview drafts. */
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
