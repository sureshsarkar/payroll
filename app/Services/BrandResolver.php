<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * BrandResolver — the single read path for "what brand do we render".
 *
 * LMS removal phase 2 (2026-08-27) — this used to compose a per-coach
 * white-label brand on top of the platform defaults: it resolved the coach from
 * the request host (custom domain / subdomain) or the /coach/{slug} tenant
 * stamp, then merged coach_brand_settings over cache('setting'). There are no
 * coach-branded surfaces any more, so there is exactly one brand — the
 * platform's — and forCoach() has gone with the coach sites.
 *
 * Used by:
 *   - The View composer registered in AppServiceProvider, so every view gets
 *     $brand automatically.
 *   - Anywhere in PHP that needs branded values:
 *         $brand = app(BrandResolver::class)->current();
 *         echo $brand->name;
 */
class BrandResolver
{
    public function current(): Brand
    {
        return Brand::platform($this->platformDefaults());
    }

    /**
     * Pulls the platform settings into an array. cache('setting') is
     * a stdClass — cast to array and we're good.
     */
    protected function platformDefaults(): array
    {
        return Cache::remember('brand:platform_defaults', 60, function () {
            $s = cache('setting');

            return $s ? (array) $s : [];
        });
    }
}
