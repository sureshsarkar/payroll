<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression scanner for SQL-injection risk in raw SQL call sites.
 *
 * 2026-05-06 audit triaged every raw SQL site (selectRaw / whereRaw /
 * havingRaw / orderByRaw / groupByRaw / DB::raw / DB::statement /
 * DB::select / DB::unprepared) under app/ + Modules/. Verdict: zero
 * SQLi vulnerabilities — every variable-interpolated site is one of:
 *
 *   1. Hardcoded local literal (e.g. DashboardController's $expr)
 *   2. Internal allowlist constant (AuditSmoke's $table)
 *   3. Helper-returned filesystem path (Installer's $database_path)
 *   4. `?` placeholder + bindings array (CoachAnalyticsController's
 *      ->havingRaw('COUNT(*) >= ?', [$threshold]))
 *
 * This test is a FORCING FUNCTION: it scans the tree for raw-SQL
 * variable interpolation and asserts every hit is on the audited
 * allowlist below. A new hit that isn't on the list fails CI, forcing
 * the author to either:
 *   (a) eliminate the interpolation (use parameter binding), or
 *   (b) prove the interpolant is not user-controlled and add it here.
 */
class RawSqlInjectionScanTest extends TestCase
{
    /**
     * Paths (relative to base) where each known-safe variable-interpolated
     * raw-SQL call site lives, with line counts that this audit verified.
     * Format: 'relative/path.php' => expected_count_of_interpolated_raw_sites
     *
     * If a file's count goes UP, a new interpolation needs review.
     * If a file's count goes DOWN (e.g. someone parameterized one), the
     * test will still pass — `actual <= expected` is the safe direction.
     */
    private const AUDITED_INTERPOLATIONS = [
        // DashboardController: 5 selectRaw("SUM($expr)") calls where
        // $expr is the hardcoded literal earnings formula on line 29.
        'app/Http/Controllers/Admin/DashboardController.php' => 5,

        // InstructorDashboardController: 2 selectRaw("SUM($earningsExpr)")
        // calls in the pulse aggregator (added 2026-05-19 phase 3). Both
        // use the same hardcoded literal earnings formula defined inline
        // in the method ($earningsExpr = '((payable_amount + ...))'). No
        // user input flows into the SQL string.
        'app/Http/Controllers/Frontend/InstructorDashboardController.php' => 2,

        // AuditSmoke: 2 DB::select("SHOW INDEX FROM `$table`") where
        // $table comes from a hardcoded const-like array in this file.
        'app/Console/Commands/AuditSmoke.php' => 2,
    ];

    /**
     * Files OUTSIDE the audited set may NOT contain any
     * variable-interpolated raw-SQL calls. Tested below.
     */
    public function test_no_unaudited_raw_sql_interpolation_under_app(): void
    {
        $hits = $this->scanForInterpolatedRawSql(base_path('app'));
        $this->assertSitesMatchAllowlist($hits, 'app/');
    }

    public function test_no_unaudited_raw_sql_interpolation_under_modules(): void
    {
        $hits = $this->scanForInterpolatedRawSql(base_path('Modules'));
        $this->assertSitesMatchAllowlist($hits, 'Modules/');
    }

    /**
     * @param array<string,int> $hits  rel-path => count of suspicious lines
     */
    private function assertSitesMatchAllowlist(array $hits, string $root): void
    {
        $unexpected = [];
        foreach ($hits as $relPath => $count) {
            $allowed = self::AUDITED_INTERPOLATIONS[$relPath] ?? 0;
            if ($count > $allowed) {
                $unexpected[$relPath] = "found=$count allowed=$allowed";
            }
        }

        $this->assertEmpty(
            $unexpected,
            "Found new variable-interpolated raw-SQL call sites under $root that haven't been audited.\n" .
            "Each one MUST be either parameterized with ?-bindings, or — if the interpolant is provably " .
            "not user-controlled — added to RawSqlInjectionScanTest::AUDITED_INTERPOLATIONS.\n\n" .
            "New sites: " . print_r($unexpected, true)
        );
    }

    /**
     * Walk a directory and count lines that match
     *    raw-SQL-method('...$var...')
     * The regex covers: selectRaw, whereRaw, orWhereRaw, orderByRaw,
     * havingRaw, groupByRaw, DB::raw, DB::statement, DB::select,
     * DB::unprepared.
     *
     * Lines that interpolate $var AND ALSO use a ? placeholder are
     * exempted (those are properly parameterized — pattern is
     *   ->havingRaw('SUM(x) > ?', [$threshold])  ).
     *
     * @return array<string,int>  rel-path-from-base => suspicious line count
     */
    private function scanForInterpolatedRawSql(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        // Only double-quoted PHP strings interpolate $variables. Single
        // quotes are literal. Match the method call followed by a
        // double-quoted argument, then sniff the body for `$`.
        // Heredocs / concat are out of scope — they're rare in this codebase
        // and would need a proper PHP parser to handle robustly.
        $rawCallRe = '/(?:selectRaw|whereRaw|orWhereRaw|orderByRaw|havingRaw|groupByRaw|DB::raw|DB::statement|DB::select|DB::unprepared)\s*\(\s*"((?:[^"\\\\]|\\\\.)*\$(?:[^"\\\\]|\\\\.)*)"/';

        $hits = [];
        $base = base_path() . DIRECTORY_SEPARATOR;
        foreach ($iter as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            // Skip vendor folders inside Modules
            $abs = $file->getPathname();
            if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
            if (str_contains($abs, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) continue;

            $count = 0;
            $lines = file($abs);
            if ($lines === false) continue;
            foreach ($lines as $line) {
                if (!preg_match_all($rawCallRe, $line, $m)) continue;
                foreach ($m[1] as $sqlString) {
                    // Lines that pair $var interpolation with ?-binding are safe.
                    if (str_contains($sqlString, '?')) continue;
                    $count++;
                }
            }
            if ($count > 0) {
                $rel = str_replace([$base, '\\'], ['', '/'], $abs);
                $hits[$rel] = $count;
            }
        }

        ksort($hits);
        return $hits;
    }
}
