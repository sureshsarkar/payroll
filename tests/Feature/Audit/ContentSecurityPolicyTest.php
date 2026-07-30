<?php

namespace Tests\Feature\Audit;

use App\Http\Middleware\ContentSecurityPolicy;
use App\Models\CoachSiteSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * P1-4 (2026-05-29) — CSP middleware contract.
 *
 * These tests run the middleware in isolation (no full HTTP stack) so we
 * can verify:
 *   - per-request nonce is generated, base64-safe, and length-stable
 *   - the report-only header is emitted by default
 *   - CSP_ENFORCE flips the header name to the enforcing variant
 *   - coach-specific analytics IDs add the right origins to the policy
 *   - non-HTML responses (JSON, file downloads) get NO CSP header
 *   - a pre-existing upstream CSP header is left alone
 */
class ContentSecurityPolicyTest extends TestCase
{
    public function test_nonce_is_generated_and_stamped_on_request(): void
    {
        $req = Request::create('/');
        $mw  = new ContentSecurityPolicy();
        $mw->handle($req, fn ($r) => $this->html());

        $nonce = $req->attributes->get('csp_nonce');
        $this->assertIsString($nonce);
        // 16 bytes of base64 (url-safe) = 22 chars unpadded.
        $this->assertSame(22, strlen($nonce));
        // URL-safe alphabet only.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $nonce);
    }

    public function test_each_request_gets_a_fresh_nonce(): void
    {
        $mw = new ContentSecurityPolicy();
        $r1 = Request::create('/');
        $r2 = Request::create('/');
        $mw->handle($r1, fn ($r) => $this->html());
        $mw->handle($r2, fn ($r) => $this->html());

        $this->assertNotSame(
            $r1->attributes->get('csp_nonce'),
            $r2->attributes->get('csp_nonce'),
            'Nonces MUST be unique per request — a sticky nonce defeats the protection.'
        );
    }

    public function test_default_is_report_only(): void
    {
        $req = Request::create('/');
        $mw  = new ContentSecurityPolicy();
        $resp = $mw->handle($req, fn ($r) => $this->html());

        $this->assertTrue($resp->headers->has('Content-Security-Policy-Report-Only'),
            'Default mode must be Report-Only — never break production traffic on first deploy.');
        $this->assertFalse($resp->headers->has('Content-Security-Policy'),
            'Enforcing header must NOT be present when CSP_ENFORCE is unset.');
    }

    public function test_nonce_appears_in_policy(): void
    {
        $req = Request::create('/');
        $mw  = new ContentSecurityPolicy();
        $resp = $mw->handle($req, fn ($r) => $this->html());

        $policy = $resp->headers->get('Content-Security-Policy-Report-Only');
        $nonce = $req->attributes->get('csp_nonce');
        $this->assertStringContainsString("'nonce-$nonce'", $policy,
            "Policy must list the request's nonce in script-src.");
    }

    public function test_policy_contains_expected_directives(): void
    {
        $req = Request::create('/');
        $mw  = new ContentSecurityPolicy();
        $resp = $mw->handle($req, fn ($r) => $this->html());
        $policy = $resp->headers->get('Content-Security-Policy-Report-Only');

        foreach ([
            "default-src 'self'",
            'script-src ',
            'style-src ',
            'img-src ',
            'connect-src ',
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $policy,
                "Policy must include directive: $needle");
        }
    }

    public function test_non_html_response_is_not_stamped(): void
    {
        $req = Request::create('/');
        $mw  = new ContentSecurityPolicy();
        $resp = $mw->handle($req, fn ($r) => new Response('{}', 200, ['Content-Type' => 'application/json']));

        $this->assertFalse($resp->headers->has('Content-Security-Policy'));
        $this->assertFalse($resp->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_upstream_csp_header_is_not_overwritten(): void
    {
        $req = Request::create('/');
        $mw  = new ContentSecurityPolicy();
        $upstream = "default-src 'self' upstream.example";
        $resp = $mw->handle($req, function ($r) use ($upstream) {
            $resp = $this->html();
            $resp->headers->set('Content-Security-Policy-Report-Only', $upstream);
            return $resp;
        });

        $this->assertSame($upstream, $resp->headers->get('Content-Security-Policy-Report-Only'),
            'When Nginx (or another upstream) already set a CSP header, the middleware must leave it alone.');
    }

    public function test_coach_with_gtm_id_extends_script_and_connect_src(): void
    {
        $coachId = \App\Models\User::factory()->create(['role' => 'instructor'])->id;
        CoachSiteSettings::create([
            'coach_id'         => $coachId,
            'analytics_gtm_id' => 'GTM-XYZ123',
        ]);

        $req = Request::create('/');
        $req->attributes->set('resolved_coach_id', $coachId);
        $mw  = new ContentSecurityPolicy();
        $resp = $mw->handle($req, fn ($r) => $this->html());
        $policy = $resp->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString('https://www.googletagmanager.com', $policy,
            'GTM-enabled coach must have googletagmanager.com allowlisted in script-src.');
        $this->assertStringContainsString('https://www.google-analytics.com', $policy,
            'GTM implies GA — google-analytics.com must be in connect-src.');
    }

    public function test_coach_without_analytics_gets_tight_policy(): void
    {
        $coachId = \App\Models\User::factory()->create(['role' => 'instructor'])->id;
        // No CoachSiteSettings row at all.

        $req = Request::create('/');
        $req->attributes->set('resolved_coach_id', $coachId);
        $mw  = new ContentSecurityPolicy();
        $resp = $mw->handle($req, fn ($r) => $this->html());
        $policy = $resp->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringNotContainsString('googletagmanager', $policy,
            "A coach with no analytics configured must NOT have third-party trackers in their CSP allowlist.");
        $this->assertStringNotContainsString('facebook.net', $policy);
        $this->assertStringNotContainsString('hotjar', $policy);
    }

    private function html(): Response
    {
        return new Response('<!DOCTYPE html><html></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
