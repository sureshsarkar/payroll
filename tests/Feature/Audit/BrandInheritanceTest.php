<?php

namespace Tests\Feature\Audit;

use App\Services\Brand;
use App\Services\BrandResolver;
use Tests\TestCase;

/**
 * UI/UX audit P0-5 — pin the contract that coach brand DOES inherit
 * through the auth boundary into the student dashboard, login page,
 * and every authenticated frontend view.
 *
 * The audit doc estimated this as 1-week work. The actual state of
 * the codebase is much better than the audit suggested — prior P4
 * white-label work (commits ~2026-05-21) wired:
 *
 *   - BrandResolver service with composition rule:
 *       coach setting → platform setting → hardcoded fallback
 *   - View composer in AppServiceProvider so $brand is auto-injected
 *     into every view
 *   - frontend/layouts/master.blade.php uses $brand->faviconUrl()
 *   - frontend/layouts/header.blade.php uses $brand->logoUrl()
 *   - frontend/layouts/styles.blade.php sets --tg-theme-primary from
 *     $brand->primaryColor in :root
 *   - auth/login.blade.php uses --auth-brand from $brand
 *   - student-dashboard/index.blade.php + many sub-pages use
 *     --sd-brand / --ec-brand / --so-brand from $brand
 *   - ResolveCoachByDomain middleware stamps coach_id from host
 *
 * The remaining risk is REGRESSION — a future PR removes the
 * $brand reference from one of these layouts and the chain breaks
 * silently. This test pins the contract.
 */
class BrandInheritanceTest extends TestCase
{
    public function test_brand_resolver_returns_brand_object(): void
    {
        $brand = app(BrandResolver::class)->current();
        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertIsString($brand->name);
        // primaryColor + accentColor may be null on a fresh DB; the
        // assertion is shape, not value.
        $this->assertTrue(property_exists($brand, 'primaryColor') ||
                          method_exists($brand, 'primaryColor') ||
                          isset($brand->primaryColor),
            'Brand must expose primaryColor');
    }

    public function test_frontend_master_layout_uses_brand_favicon(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/layouts/master.blade.php')
        );
        $this->assertStringContainsString(
            '$brand->faviconUrl()',
            $contents,
            'frontend/layouts/master.blade.php must use $brand->faviconUrl() '
            . '(P0-5 brand chain — was the favicon point of regression)'
        );
    }

    public function test_frontend_header_uses_brand_logo(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/layouts/header.blade.php')
        );
        $this->assertStringContainsString(
            '$brand->logoUrl()',
            $contents,
            'frontend/layouts/header.blade.php must use $brand->logoUrl() '
            . '(P0-5 brand chain)'
        );
    }

    public function test_frontend_styles_sets_brand_primary_css_var(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/layouts/styles.blade.php')
        );
        $this->assertStringContainsString(
            '--tg-theme-primary',
            $contents,
            'frontend/layouts/styles.blade.php must set --tg-theme-primary'
        );
        $this->assertStringContainsString(
            '$brand->primaryColor',
            $contents,
            '--tg-theme-primary must be sourced from $brand->primaryColor '
            . '(not from $setting directly)'
        );
    }

    public function test_login_page_uses_brand_primary_color(): void
    {
        $contents = file_get_contents(
            resource_path('views/auth/login.blade.php')
        );
        $this->assertStringContainsString(
            '$brand->primaryColor',
            $contents,
            'auth/login.blade.php must inherit coach brand at the auth boundary '
            . '(P0-5 — was the originally-cited break point)'
        );
    }

    public function test_student_dashboard_index_uses_brand_color(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/student-dashboard/index.blade.php')
        );
        $this->assertStringContainsString(
            '$brand->primaryColor',
            $contents,
            'student-dashboard/index must use $brand->primaryColor'
        );
    }

    public function test_student_dashboard_master_extends_brand_aware_master(): void
    {
        $contents = file_get_contents(
            resource_path('views/frontend/student-dashboard/layouts/master.blade.php')
        );
        // Confirms the inheritance chain — student-dashboard hangs off
        // frontend.layouts.master which is the brand-aware root.
        $this->assertStringContainsString(
            "@extends('frontend.layouts.master')",
            $contents,
            'student-dashboard master must extend frontend.layouts.master '
            . '(brand chain root)'
        );
    }

    public function test_brand_resolver_picks_up_coach_id_from_request_attribute(): void
    {
        // ResolveCoachByDomain stamps the coach id on the request
        // attributes; BrandResolver reads it. Simulate the stamp.
        $coachId = 99999;
        request()->attributes->set('resolved_coach_id', $coachId);

        // We can't assert the coach actually exists (no fixture);
        // we just assert the resolver tried to use it. Use a
        // closure-based read via reflection so we don't need to
        // actually hit the DB.
        $resolver = app(BrandResolver::class);
        $r = new \ReflectionClass($resolver);
        $m = $r->getMethod('resolveCurrentCoachId');
        $m->setAccessible(true);
        $resolved = $m->invoke($resolver);

        $this->assertSame($coachId, $resolved,
            'BrandResolver must read resolved_coach_id from request attributes '
            . '(the ResolveCoachByDomain middleware contract)');

        // cleanup
        request()->attributes->remove('resolved_coach_id');
    }
}
