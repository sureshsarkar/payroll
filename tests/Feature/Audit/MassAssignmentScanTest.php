<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression scanner for mass-assignment risk.
 *
 * 2026-05-06 audit triaged the codebase for the canonical Laravel
 * mass-assignment hole: Model::create($request->all()) / ->update(...) /
 * ->fill(...) — combined with a `$guarded=[]` (or missing $fillable)
 * model, this lets attackers write to columns they shouldn't control
 * (user_id, role, status, is_admin, etc.). 14 models use $guarded=[],
 * but every writer was found to use either an explicit field array,
 * $request->validate() (returns only the validated keys), or
 * $request->validated() (FormRequest equivalent). Verdict: zero
 * exploitable sites.
 *
 * This test is the forcing function: it scans for the dangerous patterns
 * and fails CI if any new one appears, regardless of which model it
 * targets. The fix in every case is to swap to validate()/validated()
 * or an explicit field list.
 */
class MassAssignmentScanTest extends TestCase
{
    /**
     * Patterns considered dangerous. Each is a substring match — if a line
     * in any PHP file contains it, the test fails with the file path.
     */
    private const DANGEROUS_PATTERNS = [
        '::create($request->all())',
        '->create($request->all())',
        '::create(request()->all())',
        '->create(request()->all())',
        '->update($request->all())',
        '->update(request()->all())',
        '->fill($request->all())',
        '->fill(request()->all())',
        '->forceFill($request->all())',
        '::forceCreate($request->all())',
    ];

    public function test_no_request_all_passed_to_eloquent_writers_under_app(): void
    {
        $this->assertNoDangerousPatterns(base_path('app'));
    }

    public function test_no_request_all_passed_to_eloquent_writers_under_modules(): void
    {
        $this->assertNoDangerousPatterns(base_path('Modules'));
    }

    private function assertNoDangerousPatterns(string $root): void
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
            // AuditSmoke embeds these patterns as literal pattern strings
            // (its checkMassAssignment block mirrors this scanner). Skip
            // it to avoid self-matching the pattern definitions.
            if (basename($abs) === 'AuditSmoke.php') continue;

            $lines = file($abs);
            if ($lines === false) continue;

            foreach ($lines as $i => $line) {
                // Skip commented-out lines (best-effort; real PHP comments
                // are harder to detect, but `// ...` lines are most common).
                if (preg_match('/^\s*(?:\/\/|\*|#)/', $line)) continue;

                foreach (self::DANGEROUS_PATTERNS as $pat) {
                    if (str_contains($line, $pat)) {
                        $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                        $hits[] = sprintf('%s:%d  %s', $rel, $i + 1, trim($line));
                    }
                }
            }
        }

        $this->assertEmpty(
            $hits,
            "Found mass-assignment-prone calls. Each one must be either:\n" .
            "  (a) replaced with \$request->validate(...) / \$request->validated()\n" .
            "  (b) replaced with an explicit field array, \$request->only([...])\n" .
            "  (c) tied to a model with a tight \$fillable allowlist (NOT \$guarded=[])\n\n" .
            "Sites:\n" . implode("\n", $hits)
        );
    }
}
