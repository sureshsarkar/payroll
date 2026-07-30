<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Tier-A admin smoke test runner.
 *
 * Per docs/MBSGuru1_Audit_Report_2026-05-15.xlsx + the testing-plan
 * conversation: this is the "pre-release sanity check" — just verify
 * every static admin GET URL renders without throwing and without a
 * permission-denied toast.
 *
 * What it does:
 *   1. Logs in as the Super Admin (admin id=1) via the in-process Auth guard.
 *   2. Enumerates every GET route whose URI starts with 'admin/' and
 *      contains no path parameters.
 *   3. Dispatches each through the Laravel HTTP kernel as a real Request.
 *   4. Captures status code + duration + any session 'alert-type=error'
 *      flash (the "Permission Denied" pattern).
 *   5. Outputs a table + summary; exits non-zero if any FAIL.
 *
 * What it does NOT do:
 *   - Hit routes with path params ({id}, {slug}) — those need fixture data.
 *   - Submit forms / CRUD writes — that's Tier B.
 *   - Cross-browser testing — that's Tier D.
 *
 * Usage:
 *   php artisan admin:smoke-tier-a            # human-readable table
 *   php artisan admin:smoke-tier-a --json     # JSON output
 *   php artisan admin:smoke-tier-a --bail     # stop on first failure
 */
class AdminSmokeTier extends Command
{
    protected $signature = 'admin:smoke-tier-a
                            {--json : Output machine-readable JSON}
                            {--bail : Stop on the first failure}
                            {--filter= : Substring filter on URL}';

    protected $description = 'Tier-A admin smoke: visit every static admin GET URL as Super Admin and assert no error.';

    public function handle(): int
    {
        $admin = Admin::find(1);
        if (!$admin) {
            $this->error('No Admin with id=1 found. Seed an admin first.');
            return self::FAILURE;
        }

        Auth::guard('admin')->loginUsingId($admin->id);
        $this->info("Logged in as: {$admin->email} (role=Super Admin)");
        $this->line('');

        // Collect static admin GET routes
        $urls = [];
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            $methods = $route->methods();
            $uri = $route->uri();
            if (!in_array('GET', $methods, true)) continue;
            if (!str_starts_with($uri, 'admin/')) continue;
            if (str_contains($uri, '{')) continue;       // skip parameterized
            if (str_contains($uri, 'logout')) continue;  // would log us out
            if (str_contains($uri, '_debugbar')) continue;

            // Known smoke-can't-test-cleanly:
            // - admin/slider-section: aborts 404 unless DEFAULT_HOMEPAGE is 'business'
            //   (theme-gated; correct behaviour for non-business themes)
            // - admin/course-chapter/lesson/edit: parameterless GET that the
            //   view consumes with ?course_id=. Without the query param,
            //   the partial renders against null. Needs a fixture.
            if (in_array($uri, [
                'admin/slider-section',
                'admin/course-chapter/lesson/edit',
            ], true)) continue;

            $urls[] = $uri;
        }
        $urls = array_unique($urls);
        sort($urls);

        $filter = (string) $this->option('filter');
        if ($filter !== '') {
            $urls = array_values(array_filter($urls, fn($u) => str_contains($u, $filter)));
        }

        $bail = (bool) $this->option('bail');
        $jsonMode = (bool) $this->option('json');

        $results = [];
        $pass = 0; $fail = 0; $warn = 0;

        foreach ($urls as $uri) {
            $url = '/' . $uri;
            $t0 = microtime(true);

            // Build an in-process request. We dispatch through the kernel so
            // middleware (auth, throttle, permission) actually runs.
            $request = Request::create($url, 'GET');
            // Carry over the admin session
            $request->setLaravelSession(app('session.store'));
            session()->setPreviousUrl(url($url));

            try {
                $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($request);
                $status = $response->getStatusCode();
                $body = (string) $response->getContent();
                $duration = (int) ((microtime(true) - $t0) * 1000);

                $flashError = $this->extractFlashError($response);
                $perm = stripos($body, 'permission denied') !== false
                    || stripos($body, 'you can not perform') !== false;

                if ($status >= 500) {
                    $label = 'FAIL'; $fail++;
                    $detail = "HTTP $status (server error)";
                } elseif ($status === 404) {
                    $label = 'FAIL'; $fail++;
                    $detail = 'HTTP 404 (route resolved but body says not found?)';
                } elseif ($flashError) {
                    $label = 'WARN'; $warn++;
                    $detail = "flash: $flashError";
                } elseif ($perm) {
                    $label = 'FAIL'; $fail++;
                    $detail = 'Permission Denied in body';
                } elseif ($status >= 400) {
                    $label = 'WARN'; $warn++;
                    $detail = "HTTP $status";
                } elseif ($status === 302) {
                    $label = 'PASS'; $pass++;
                    $detail = '302 -> ' . ($response->headers->get('Location') ?: '?');
                } else {
                    $label = 'PASS'; $pass++;
                    $detail = "HTTP $status (".strlen($body)." bytes)";
                }

                $results[] = compact('url', 'status', 'duration', 'label', 'detail');

                if (!$jsonMode) {
                    $this->line(sprintf(
                        "  [%s] %-45s %4dms  %s",
                        $label, $url, $duration, $detail
                    ));
                }

                if ($bail && $label === 'FAIL') break;

            } catch (\Throwable $e) {
                $fail++;
                $duration = (int) ((microtime(true) - $t0) * 1000);
                $results[] = [
                    'url' => $url, 'status' => 0, 'duration' => $duration,
                    'label' => 'FAIL',
                    'detail' => 'EXCEPTION '.get_class($e).': '.$e->getMessage(),
                ];
                if (!$jsonMode) {
                    $this->error(sprintf(
                        "  [FAIL] %-45s %4dms  %s",
                        $url, $duration,
                        get_class($e) . ': ' . substr($e->getMessage(), 0, 80)
                    ));
                }
                if ($bail) break;
            }
        }

        if ($jsonMode) {
            $this->line(json_encode([
                'pass' => $pass, 'warn' => $warn, 'fail' => $fail,
                'total' => count($urls),
                'results' => $results,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->line('');
            $this->line(str_repeat('=', 50));
            $this->info(sprintf(
                'PASS=%d  WARN=%d  FAIL=%d  /  total=%d',
                $pass, $warn, $fail, count($urls),
            ));
            $this->line(str_repeat('=', 50));
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function extractFlashError($response): ?string
    {
        // Look at session flash (set by AccessPermissionDeniedException etc.)
        if (!session()->has('alert-type') || session('alert-type') !== 'error') {
            return null;
        }
        return (string) session('messege', session('message', 'unknown'));
    }
}
