<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression scanner for dangerous PHP function usage.
 *
 * 2026-05-06 audit triaged app/ + Modules/ for the canonical RCE / object-
 * injection vectors:
 *
 *   eval()           — arbitrary PHP code execution
 *   unserialize()    — PHP-object-injection (works with __wakeup/__destruct
 *                      gadgets in any installed library)
 *   shell_exec(), exec(), system(), passthru(), popen(), proc_open()
 *                    — shell command execution
 *   create_function(), assert() with string arg
 *                    — historical eval-equivalents
 *
 * Verdict: zero usage in our code. (The exec() hits in /public are all
 * third-party JS minified bundles — JavaScript exec(), unrelated.)
 *
 * This test enforces that property going forward. Each future use must
 * be either:
 *   (a) replaced with the safe equivalent (json_decode for unserialize,
 *       Symfony Process for shell, etc.)
 *   (b) added to the EXEMPT_PATHS allowlist below with a `// PHPCS:
 *       audited-safe` comment on the line, AND a code review.
 */
class DangerousFunctionsScanTest extends TestCase
{
    /**
     * Files exempted from the scan. Currently empty — we have no legitimate
     * uses. Add a path here only after a per-line audit, and only with
     * explicit review approval.
     *
     * @var array<int, string>
     */
    private const EXEMPT_PATHS = [];

    /**
     * Each entry is a regex matched against each (uncommented) line of
     * code. The patterns are deliberately tight — they require the
     * function name as a whole word followed by `(`, so e.g. `myExec()`
     * (a method call) doesn't trip them.
     */
    private const PATTERNS = [
        '/\beval\s*\(/'         => 'eval()',
        '/\bunserialize\s*\(/'  => 'unserialize()',
        '/\bshell_exec\s*\(/'   => 'shell_exec()',
        '/(?<![A-Za-z0-9_])exec\s*\(/'         => 'exec()',
        '/(?<![A-Za-z0-9_])system\s*\(/'       => 'system()',
        '/\bpassthru\s*\(/'     => 'passthru()',
        '/\bpopen\s*\(/'        => 'popen()',
        '/\bproc_open\s*\(/'    => 'proc_open()',
        '/\bcreate_function\s*\(/' => 'create_function()',
    ];

    public function test_no_dangerous_functions_under_app(): void
    {
        $this->assertNoDangerousFunctions(base_path('app'));
    }

    public function test_no_dangerous_functions_under_modules(): void
    {
        $this->assertNoDangerousFunctions(base_path('Modules'));
    }

    private function assertNoDangerousFunctions(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $hits = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $abs = $file->getPathname();
            if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
            if (str_contains($abs, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) continue;

            $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
            if (in_array($rel, self::EXEMPT_PATHS, true)) continue;

            $lines = file($abs);
            if ($lines === false) continue;

            foreach ($lines as $i => $line) {
                if (preg_match('/^\s*(?:\/\/|\*|#)/', $line)) continue;
                if (str_contains($line, 'PHPCS: audited-safe')) continue;

                foreach (self::PATTERNS as $regex => $name) {
                    if (preg_match($regex, $line)) {
                        $hits[] = sprintf('%s:%d  [%s]  %s', $rel, $i + 1, $name, trim($line));
                    }
                }
            }
        }

        $this->assertEmpty(
            $hits,
            "Found dangerous PHP function calls. Each is an RCE / object-injection vector unless\n" .
            "carefully reviewed. Each must be either:\n" .
            "  (a) replaced with the safe equivalent (json_decode, Symfony Process, etc.)\n" .
            "  (b) audited and marked with `// PHPCS: audited-safe` on the same line, plus\n" .
            "      added to EXEMPT_PATHS in DangerousFunctionsScanTest\n\n" .
            "Sites:\n" . implode("\n", $hits)
        );
    }
}
