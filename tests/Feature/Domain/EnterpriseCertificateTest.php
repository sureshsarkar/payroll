<?php

namespace Tests\Feature\Domain;

use App\Models\CertificateCredential;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Enterprise certificate (2026-07-08): a verifiable credential per (student,
 * course), a public verify page, and a framed/branded landscape template.
 */
class EnterpriseCertificateTest extends TestCase
{
    use DatabaseTransactions;

    /* ── credential ──────────────────────────────────────────────────── */

    public function test_credential_issue_is_idempotent_per_student_course(): void
    {
        $a = CertificateCredential::issueFor(501, 9001, ['student_name' => 'Asha', 'course_title' => 'PHP 101']);
        $b = CertificateCredential::issueFor(501, 9001, ['student_name' => 'Asha Verma', 'course_title' => 'PHP 101']);

        $this->assertSame($a->id, $b->id, 'same (student,course) → same row');
        $this->assertSame($a->uid, $b->uid, 'uid is stable across re-downloads');
        $this->assertSame('Asha Verma', $b->fresh()->student_name, 'display fields refresh');
        $this->assertMatchesRegularExpression('/^MBS-[A-Z0-9]{10}$/', $a->uid, 'friendly credential code');
    }

    public function test_different_courses_get_different_credentials(): void
    {
        $a = CertificateCredential::issueFor(502, 9002);
        $b = CertificateCredential::issueFor(502, 9003);
        $this->assertNotSame($a->uid, $b->uid);
    }

    /* ── public verification page ────────────────────────────────────── */

    private function verifyHtml(string $uid): string
    {
        // Call the controller directly — the test APP_URL carries a /public
        // subpath, so $this->get('/absolute') would 404 (codebase convention).
        return app(\App\Http\Controllers\Frontend\CertificateVerificationController::class)->show($uid)->render();
    }

    public function test_verify_page_confirms_a_real_credential(): void
    {
        $c = CertificateCredential::issueFor(503, 9004, [
            'student_name' => 'Rahul Sharma', 'course_title' => 'Advanced Java', 'coach_name' => 'Santosh',
        ]);

        $html = $this->verifyHtml($c->uid);
        $this->assertStringContainsString('Certificate verified', $html);
        $this->assertStringContainsString('Rahul Sharma', $html);
        $this->assertStringContainsString('Advanced Java', $html);
        $this->assertStringContainsString($c->uid, $html);
    }

    public function test_verify_page_rejects_an_unknown_code(): void
    {
        $html = $this->verifyHtml('MBS-DOESNOTEXIST');
        $this->assertStringContainsString('Certificate not found', $html);
        $this->assertStringNotContainsString('Certificate verified', $html);
    }

    /* ── enterprise template render ──────────────────────────────────── */

    public function test_enterprise_template_renders_brand_and_credential(): void
    {
        $html = view('frontend.student-dashboard.certificate.enterprise', [
            'brandColor' => '#123456', 'goldColor' => '#b08d4f', 'paperColor' => '#faf7f0',
            'brandName' => 'Yoga Academy', 'logoData' => null, 'signatureData' => null,
            'studentName' => 'Priya Nair', 'courseTitle' => 'Hatha Yoga Foundations',
            'dateText' => '8 Jul 2026', 'instructorName' => 'Guru Anand',
            'credentialUid' => 'MBS-ABC1234XYZ', 'verifyUrl' => '#', 'verifyLabel' => 'mbsguru.com/verify',
            'qrData' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
        ])->render();

        $this->assertStringContainsString('CERTIFICATE OF COMPLETION', $html);
        $this->assertStringContainsString('Priya Nair', $html, 'recipient name');
        $this->assertStringContainsString('Hatha Yoga Foundations', $html, 'course');
        $this->assertStringContainsString('MBS-ABC1234XYZ', $html, 'credential id');
        $this->assertStringContainsString('#123456', $html, 'coach brand colour applied');
        $this->assertStringContainsString('data:image/svg+xml;base64', $html, 'QR embedded');
        $this->assertStringContainsString('size: A4 landscape', $html, 'landscape (fixes the old portrait crop)');
    }

    /** The template renders 3 distinct DESIGN skins off the same structure. */
    public function test_enterprise_template_supports_design_variants(): void
    {
        $base = [
            'brandColor' => '#0f766e', 'goldColor' => '#b08d4f', 'paperColor' => '#faf7f0',
            'brandName' => 'Yoga Academy', 'logoData' => null, 'signatureData' => null,
            'studentName' => 'Priya Nair', 'courseTitle' => 'Hatha Yoga', 'dateText' => '8 Jul 2026',
            'instructorName' => 'Guru Anand', 'credentialUid' => 'MBS-ABC1234XYZ',
            'verifyUrl' => '#', 'verifyLabel' => 'v', 'qrData' => null,
        ];

        foreach (['classic', 'modern', 'royal'] as $tpl) {
            $html = view('frontend.student-dashboard.certificate.enterprise', $base + ['template' => $tpl])->render();
            $this->assertStringContainsString('data-tpl="' . $tpl . '"', $html, "renders the {$tpl} skin");
            $this->assertStringContainsString('Priya Nair', $html);
        }
    }

    /** An unknown/blank template must not blow up — it falls back to classic. */
    public function test_enterprise_template_defaults_when_variant_missing(): void
    {
        $html = view('frontend.student-dashboard.certificate.enterprise', [
            'brandColor' => '#0f766e', 'goldColor' => '#b08d4f', 'paperColor' => '#faf7f0',
            'brandName' => 'A', 'logoData' => null, 'signatureData' => null,
            'studentName' => 'N', 'courseTitle' => 'C', 'dateText' => 'd', 'instructorName' => 'i',
            'credentialUid' => 'MBS-XXXXXXXXXX', 'verifyUrl' => '#', 'verifyLabel' => 'v', 'qrData' => null,
        ])->render(); // no 'template' key
        $this->assertStringContainsString('data-tpl="classic"', $html);
    }

    /**
     * The accent override drives the certificate colour: the controller resolves
     * accent_color (or the brand colour) and passes it as $brandColor, so a
     * per-certificate accent shows up everywhere the brand colour would.
     */
    public function test_accent_colour_override_is_applied(): void
    {
        $html = view('frontend.student-dashboard.certificate.enterprise', [
            'template' => 'modern', 'brandColor' => '#7c3aed', 'goldColor' => '#b08d4f',
            'paperColor' => '#faf7f0', 'brandName' => 'A', 'logoData' => null, 'signatureData' => null,
            'studentName' => 'N', 'courseTitle' => 'C', 'dateText' => 'd', 'instructorName' => 'i',
            'credentialUid' => 'MBS-XXXXXXXXXX', 'verifyUrl' => '#', 'verifyLabel' => 'v', 'qrData' => null,
        ])->render();
        $this->assertStringContainsString('#7c3aed', $html, 'the chosen accent colour is used');
    }

    /** Typography & layout options flow through to the exported HTML. */
    public function test_typography_and_layout_options_render(): void
    {
        $html = view('frontend.student-dashboard.certificate.enterprise', [
            'template' => 'modern', 'brandColor' => '#0f766e', 'goldColor' => '#b08d4f',
            'paperColor' => '#eef7f2', 'fontFamily' => 'sans-serif', 'textAlign' => 'left',
            'brandName' => 'A', 'logoData' => null, 'signatureData' => null,
            'studentName' => 'Priya', 'courseTitle' => 'C', 'dateText' => 'd', 'instructorName' => 'i',
            'credentialUid' => 'MBS-XXXXXXXXXX', 'verifyUrl' => '#', 'verifyLabel' => 'v', 'qrData' => null,
        ])->render();

        $this->assertStringContainsString('font-family: sans-serif', $html, 'chosen font family applied');
        $this->assertStringContainsString('data-al="left"', $html, 'chosen alignment applied');
        $this->assertStringContainsString('#eef7f2', $html, 'paper-colour override applied');
    }

    /** Missing typography vars must not break the render (safe defaults). */
    public function test_typography_defaults_when_options_absent(): void
    {
        $html = view('frontend.student-dashboard.certificate.enterprise', [
            'template' => 'classic', 'brandColor' => '#0f766e', 'goldColor' => '#b08d4f', 'paperColor' => '#faf7f0',
            'brandName' => 'A', 'logoData' => null, 'signatureData' => null,
            'studentName' => 'N', 'courseTitle' => 'C', 'dateText' => 'd', 'instructorName' => 'i',
            'credentialUid' => 'MBS-XXXXXXXXXX', 'verifyUrl' => '#', 'verifyLabel' => 'v', 'qrData' => null,
        ])->render(); // no fontFamily / textAlign
        $this->assertStringContainsString('font-family: serif', $html);
        $this->assertStringContainsString('data-al="center"', $html);
    }

    public function test_enterprise_template_is_xss_safe_on_name(): void
    {
        $html = view('frontend.student-dashboard.certificate.enterprise', [
            'brandColor' => '#0f766e', 'goldColor' => '#b08d4f', 'paperColor' => '#faf7f0',
            'brandName' => 'A', 'logoData' => null, 'signatureData' => null,
            'studentName' => '<script>alert(1)</script>', 'courseTitle' => 'C',
            'dateText' => 'd', 'instructorName' => 'i',
            'credentialUid' => 'MBS-XXXXXXXXXX', 'verifyUrl' => '#', 'verifyLabel' => 'v', 'qrData' => null,
        ])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'Blade escapes the name');
    }
}
