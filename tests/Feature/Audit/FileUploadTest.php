<?php

namespace Tests\Feature\Audit;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Verifies the audit's file-upload guarantees:
 *
 *  - file_upload() and file_template_upload() reject extensions outside the
 *    allowlist (was: any extension permitted, including .php).
 *  - Filenames are sanitized — basename()'d, lowercased, traversal-stripped.
 *  - public/uploads/.htaccess disables PHP execution as runtime backstop.
 *  - LFM's disallowed_extensions covers the broader executable set
 *    (was: only php/html/js — missed phtml/phar/php5/svg/htaccess).
 */
class FileUploadTest extends TestCase
{
    public function test_file_upload_rejects_php_extension(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not allowed/i');

        $fake = UploadedFile::fake()->createWithContent('shell.php', '<?php phpinfo();');
        file_upload($fake, 'uploads/test-' . uniqid() . '/');
    }

    public function test_file_upload_rejects_phtml(): void
    {
        $this->expectException(\RuntimeException::class);
        $fake = UploadedFile::fake()->createWithContent('a.phtml', '<?php');
        file_upload($fake, 'uploads/test-' . uniqid() . '/');
    }

    public function test_file_upload_rejects_svg_with_script(): void
    {
        $this->expectException(\RuntimeException::class);
        $fake = UploadedFile::fake()->createWithContent(
            'evil.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );
        file_upload($fake, 'uploads/test-' . uniqid() . '/');
    }

    public function test_file_template_upload_rejects_php(): void
    {
        $this->expectException(\RuntimeException::class);
        $fake = UploadedFile::fake()->createWithContent('attack.php', '<?php');
        file_template_upload($fake, 'uploads/test-' . uniqid() . '/');
    }

    public function test_file_template_upload_strips_path_traversal_in_basename(): void
    {
        // Even a benign-extension upload with a traversal-y name must not
        // escape the destination directory. Use a real PNG to satisfy the
        // mime-detection allowlist.
        $fake = UploadedFile::fake()->image('../../evil.png', 10, 10);
        $rel = file_template_upload($fake, 'uploads/test-traversal-' . uniqid() . '/');

        $this->assertStringNotContainsString('../',  $rel);
        $this->assertStringNotContainsString('..\\', $rel);

        $real = realpath(public_path($rel));
        $allowed = realpath(public_path('uploads'));
        $this->assertNotFalse($real);
        $this->assertNotFalse($allowed);
        $this->assertStringStartsWith($allowed, $real, 'final path must stay under public/uploads/');

        @unlink($real);
        @rmdir(dirname($real));
    }

    public function test_file_upload_accepts_real_image(): void
    {
        $dir = 'uploads/test-ok-' . uniqid() . '/';
        $fake = UploadedFile::fake()->image('photo.png', 10, 10);
        $rel = file_upload($fake, $dir);

        $this->assertStringEndsWith('.png', $rel);
        $abs = public_path($rel);
        $this->assertFileExists($abs);

        @unlink($abs);
        @rmdir(public_path($dir));
    }

    public function test_uploads_htaccess_blocks_php_execution(): void
    {
        $ht = public_path('uploads/.htaccess');
        $this->assertFileExists($ht, 'public/uploads/.htaccess must exist (defense-in-depth)');

        $body = file_get_contents($ht);
        $this->assertStringContainsString('php_flag engine off', $body,
            'uploads/.htaccess must turn off the PHP engine');
        $this->assertMatchesRegularExpression('/Require\s+all\s+denied/i', $body,
            'uploads/.htaccess must deny access to .php-family files');
        $this->assertStringContainsString('Options -Indexes', $body,
            'uploads/.htaccess must disable directory listing');
    }

    public function test_lfm_disallowed_extensions_covers_dangerous_set(): void
    {
        $disallowed = config('lfm.disallowed_extensions');
        $required = ['php', 'phtml', 'phar', 'php5', 'phps', 'svg', 'htaccess', 'exe', 'sh'];
        foreach ($required as $ext) {
            $this->assertContains($ext, $disallowed,
                "LFM disallowed_extensions must include '$ext' (was missed in pre-audit short list)");
        }
    }

    public function test_lfm_disallowed_mimetypes_covers_php_and_svg(): void
    {
        $disallowed = config('lfm.disallowed_mimetypes');
        foreach (['text/x-php', 'application/x-httpd-php', 'image/svg+xml'] as $mime) {
            $this->assertContains($mime, $disallowed,
                "LFM disallowed_mimetypes must block '$mime'");
        }
    }
}
