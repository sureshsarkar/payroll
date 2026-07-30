<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression scanner for server-side template injection (SSTI) and
 * dynamic Blade rendering with user input.
 *
 * 2026-05-06 audit: every view(...) call in app/ + Modules/ takes a
 * literal string template name. No Blade::render(\$userInput) /
 * Blade::compileString(\$userInput) patterns. Zero SSTI vectors.
 *
 * Forcing function: any new view(\$request->X) would let an attacker
 * pick any installed template (including admin-only ones) for
 * rendering, OR worse, with Blade::render(\$x) achieve full PHP
 * code execution via {{ system('id') }} -- the canonical SSTI
 * exploit on Laravel.
 */
class TemplateInjectionScanTest extends TestCase
{
    private const DANGEROUS_PATTERNS = [
        'view($request->',
        'view(request()->',
        '->view($request->',
        '->view(request()->',
        'Blade::render($request->',
        'Blade::render(request()->',
        'Blade::compileString($request->',
        'Blade::compileString(request()->',
    ];

    public function test_no_user_input_in_view_or_blade_render(): void
    {
        $hits = [];
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                if (basename($abs) === 'AuditSmoke.php') continue;
                if (basename($abs) === 'TemplateInjectionScanTest.php') continue;

                $body = (string) file_get_contents($abs);
                foreach (self::DANGEROUS_PATTERNS as $pat) {
                    if (str_contains($body, $pat)) {
                        $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                        $hits[] = "$rel  [$pat]";
                    }
                }
            }
        }
        $this->assertEmpty(
            $hits,
            "User input flowing into view()/Blade::render(). With Laravel's Blade,\n" .
            "Blade::render(\$x) where \$x contains `{{ system('whoami') }}` is full RCE.\n" .
            "view(\$x) is somewhat narrower but still gives the attacker their pick of\n" .
            "any installed template (admin views, partial includes that bypass auth, etc.).\n\n" .
            "Each must be either:\n" .
            "  (a) Literal: view('frontend.foo.bar', [...])\n" .
            "  (b) Allowlisted: in_array(\$x, ['safe.template'], true) ? view(\$x) : abort(404)\n\n" .
            "Sites:\n  " . implode("\n  ", $hits)
        );
    }
}
