<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Regression scanner for cache key injection / cache poisoning.
 *
 * 2026-05-06 audit triaged every Cache::* and cache()-> call. Verdict:
 * zero injection or poisoning vectors.
 *
 *   - Most cache keys are hardcoded literal strings ('setting',
 *     'allLanguages', 'admin.dashboard.earnings', etc.).
 *   - Variable keys are namespaced with internal IDs:
 *       'payment_due_reminder_' . \$order->id . '_d' . \$daysOpen
 *       'course_completed_notified_' . userAuth()->id . '_' . \$courseId
 *       'live_session_pre_notified_' . \$liveClass->id
 *     User-controlled IDs (\$courseId from request) are integer-typed and
 *     bound to DB rows — no injection surface.
 *
 * Forcing function: any new Cache::put(\$request->X, ...) — using raw
 * request input as a cache key — gets flagged.
 */
class CacheKeyInjectionScanTest extends TestCase
{
    private const DANGEROUS_PATTERNS = [
        'Cache::get($request->',
        'Cache::put($request->',
        'Cache::forget($request->',
        'Cache::remember($request->',
        'Cache::rememberForever($request->',
        'Cache::has($request->',
        'cache()->get($request->',
        'cache()->put($request->',
        'cache()->forget($request->',
        'cache()->remember($request->',
        'cache()->forever($request->',
        'cache($request->',  // cache($key, $value) helper
        'Cache::get(request()->',
        'Cache::put(request()->',
    ];

    public function test_no_cache_key_built_from_request_input(): void
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
                if (basename($abs) === 'CacheKeyInjectionScanTest.php') continue;

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
            "User input flowing directly into a cache key. Even bounded keys carry risk:\n" .
            "  - Multi-tenant collision: two users with similar inputs hit the same key\n" .
            "  - Cache stuffing: an attacker fills the cache backend with junk keys\n" .
            "  - When chained with `Cache::get(\$_) . ->whateverOnUntrustedValue` the\n" .
            "    poisoned value flows into application logic\n\n" .
            "Each must be either:\n" .
            "  (a) Validated + namespaced: 'pfx_' . \$validated['id']\n" .
            "  (b) Hashed: hash('sha256', \$request->X)\n" .
            "  (c) Replaced with a server-controlled identifier (auth()->id())\n\n" .
            "Sites:\n  " . implode("\n  ", $hits)
        );
    }
}
