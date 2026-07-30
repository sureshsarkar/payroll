<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot health-check command that verifies every guarantee the
 * 2026-05-05 audit established. Run after deploys to confirm nothing
 * regressed, before deploys to confirm the local baseline is healthy.
 *
 *   php artisan audit:smoke
 *   php artisan audit:smoke --skip-routes        (faster; skip the HTTP loop)
 *
 * Exit code: 0 = all pass · 1 = one or more failures
 */
class AuditSmoke extends Command
{
    protected $signature = 'audit:smoke {--skip-routes : Skip HTTP route checks (faster but less coverage)}';
    protected $description = 'Verify the 2026-05-05 audit baseline is intact (DB, security, perf, encryption, scheduler).';

    private array $checks = [];

    public function handle(): int
    {
        $this->info('Running audit baseline smoke checks...');
        $this->newLine();

        $this->checkDb();
        $this->checkUniqueConstraints();
        $this->checkFkIndexes();
        $this->checkAuditMigrations();
        $this->checkSecretEncryption();
        $this->checkDriverConfiguration();
        $this->checkTelescopeTuning();
        $this->checkScheduler();
        $this->checkSettingsCache();
        $this->checkWebhookRoutes();
        $this->checkLoginThrottle();
        $this->checkZoomHardening();
        $this->checkSanctumApi();
        $this->checkFileUploadHardening();
        $this->checkTwoFactorThrottle();
        $this->checkWebhookHardening();
        $this->checkRawSqlInterpolation();
        $this->checkSessionAuthHardening();
        $this->checkCorsAndCsrfExempt();
        $this->checkMassAssignment();
        $this->checkOpenRedirect();
        $this->checkDangerousFunctions();
        $this->checkCoachPanelIdor();
        $this->checkBladeXss();
        $this->checkFileServe();
        $this->checkAdminPermissionCoverage();
        $this->checkDebugDefaults();
        $this->checkCronAndQueue();
        $this->checkDependencyVersions();
        $this->checkEmailInjection();
        $this->checkCacheKeyInjection();
        $this->checkBackupConfig();
        $this->checkTemplateInjection();
        $this->checkBroadcastAuth();

        if (!$this->option('skip-routes')) {
            $this->checkHttpRoutes();
        }

        $this->newLine();
        $this->printReport();

        $failed = collect($this->checks)->where('status', 'FAIL')->count();
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function record(string $section, string $name, string $status, string $detail = ''): void
    {
        $this->checks[] = compact('section', 'name', 'status', 'detail');
        $marker = match ($status) {
            'OK'   => '<fg=green>✓</>',
            'WARN' => '<fg=yellow>⚠</>',
            'FAIL' => '<fg=red>✗</>',
            default => '?',
        };
        $line = sprintf('%s [%s] %s', $marker, $section, $name);
        if ($detail !== '') $line .= ' — ' . $detail;
        $this->line($line);
    }

    private function checkDb(): void
    {
        try {
            $version = DB::selectOne('SELECT VERSION() v')->v ?? '?';
            $this->record('DB', 'connection', 'OK', "MySQL/MariaDB $version");
        } catch (\Throwable $e) {
            $this->record('DB', 'connection', 'FAIL', $e->getMessage());
        }
    }

    private function checkUniqueConstraints(): void
    {
        $expected = [
            'orders'      => ['orders_transaction_id_unique', 'orders_invoice_id_unique'],
            'enrollments' => ['enrollments_user_course_unique'],
        ];
        foreach ($expected as $table => $names) {
            $present = collect(DB::select("SHOW INDEX FROM `$table` WHERE Non_unique=0"))
                ->pluck('Key_name')->unique()->all();
            foreach ($names as $idx) {
                if (in_array($idx, $present, true)) {
                    $this->record('DB', "unique $table.$idx", 'OK');
                } else {
                    $this->record('DB', "unique $table.$idx", 'FAIL', 'missing — money-path idempotency at risk');
                }
            }
        }
    }

    private function checkFkIndexes(): void
    {
        $expected = [
            'orders'        => ['orders_buyer_id_index', 'orders_seller_id_index'],
            'order_items'   => ['order_items_product_id_index'],
            'quiz_results'  => ['quiz_results_user_id_index', 'quiz_results_quiz_id_index'],
            'courses'       => ['courses_instructor_id_index', 'courses_added_by_index'],
        ];
        foreach ($expected as $table => $names) {
            $present = collect(DB::select("SHOW INDEX FROM `$table`"))
                ->pluck('Key_name')->unique()->all();
            foreach ($names as $idx) {
                $status = in_array($idx, $present, true) ? 'OK' : 'WARN';
                $detail = $status === 'OK' ? '' : 'missing FK index — slower JOINs';
                $this->record('DB', "fk-idx $table.$idx", $status, $detail);
            }
        }
    }

    private function checkAuditMigrations(): void
    {
        $expected = [
            '2026_05_05_140000_add_idempotency_uniques_to_orders_and_enrollments',
            '2026_05_05_150000_add_fk_indexes_for_perf',
            '2026_05_05_151812_create_sessions_table',
            '2026_05_05_152319_create_cache_table',
            '2026_05_05_160000_add_usage_limits_to_coupons',
            '2026_05_05_170000_encrypt_secret_settings',
        ];
        $ran = collect(DB::table('migrations')->pluck('migration'))->all();
        foreach ($expected as $m) {
            $status = in_array($m, $ran, true) ? 'OK' : 'FAIL';
            $detail = $status === 'OK' ? '' : 'NOT RUN — `php artisan migrate` required';
            $this->record('Migrate', $m, $status, $detail);
        }
    }

    private function checkSecretEncryption(): void
    {
        $secrets = ['mail_password', 'recaptcha_secret_key', 'aws_secret_key', 'wasabi_secret_key', 'pusher_app_secret', 'gmail_secret_id', 'facebook_app_secret'];
        $rows = DB::table('settings')->whereIn('key', $secrets)->get(['key', 'value']);
        foreach ($rows as $r) {
            $isEnc = is_string($r->value) && str_starts_with($r->value, 'enc:v1:');
            $hasValue = !empty($r->value);
            if (!$hasValue) {
                $this->record('Crypt', $r->key, 'WARN', 'empty (not set yet)');
            } elseif ($isEnc) {
                $this->record('Crypt', $r->key, 'OK', 'enc:v1:');
            } else {
                $this->record('Crypt', $r->key, 'FAIL', 'PLAINTEXT — run encrypt_secret_settings migration');
            }
        }
    }

    private function checkDriverConfiguration(): void
    {
        $session = config('session.driver');
        $cache   = config('cache.default');
        $queue   = config('queue.default');

        $this->record('Drivers', 'session', $session === 'database' ? 'OK' : 'WARN', "driver=$session" . ($session !== 'database' ? ' (audit recommends database)' : ''));
        $this->record('Drivers', 'cache',   $cache === 'database' ? 'OK' : 'WARN', "driver=$cache" . ($cache !== 'database' ? ' (audit recommends database)' : ''));
        $this->record('Drivers', 'queue',   in_array($queue, ['database', 'redis'], true) ? 'OK' : 'WARN', "driver=$queue");
    }

    private function checkTelescopeTuning(): void
    {
        if (!Schema::hasTable('telescope_entries')) {
            $this->record('Telescope', 'installed', 'WARN', 'not installed (production-ready: dev-only package)');
            return;
        }
        $count = DB::table('telescope_entries')->count();
        $status = $count > 100_000 ? 'WARN' : 'OK';
        $detail = "$count entries" . ($count > 100_000 ? ' (>100k — run telescope:prune)' : '');
        $this->record('Telescope', 'entry-count', $status, $detail);

        $cacheWatcher = env('TELESCOPE_CACHE_WATCHER', 'true');
        $queryWatcher = env('TELESCOPE_QUERY_WATCHER', 'true');
        $noisy = ($cacheWatcher === 'true' || $queryWatcher === 'true') ? 'WARN' : 'OK';
        $this->record('Telescope', 'noisy watchers', $noisy, $noisy === 'OK' ? 'cache + query watchers off' : 'turn off cache/query/view watchers in .env');

        // 2026-05-29 — UI/UX audit P0-2: dev tooling exposure check.
        // On prod the master switch MUST be off; flipping it is a
        // deliberate two-key turn (TELESCOPE_ENABLED + TELESCOPE_ALLOW_PROD_VIEW
        // + email allowlist).
        if (app()->environment('production')) {
            $tEnabled = filter_var(env('TELESCOPE_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
            $dEnabled = filter_var(env('DEBUGBAR_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
            $this->record(
                'Telescope', 'TELESCOPE_ENABLED=false on prod',
                $tEnabled ? 'FAIL' : 'OK',
                $tEnabled ? 'set TELESCOPE_ENABLED=false in production .env' : ''
            );
            $this->record(
                'Debug', 'DEBUGBAR_ENABLED=false on prod',
                $dEnabled ? 'FAIL' : 'OK',
                $dEnabled ? 'set DEBUGBAR_ENABLED=false in production .env' : ''
            );
        }
    }

    private function checkScheduler(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();
        $count = count($events);
        $hasPrune = collect($events)->contains(fn ($e) => str_contains((string) $e->command, 'telescope:prune'));
        $hasBackup = collect($events)->contains(fn ($e) => str_contains((string) $e->command, 'backup:run'));

        $this->record('Scheduler', 'event count', $count >= 10 ? 'OK' : 'WARN', "$count entries");
        $this->record('Scheduler', 'telescope:prune daily', $hasPrune ? 'OK' : 'FAIL', $hasPrune ? '' : 'add to Console/Kernel.php');
        $this->record('Scheduler', 'backup:run daily', $hasBackup ? 'OK' : 'WARN', '');
    }

    private function checkSettingsCache(): void
    {
        try {
            $setting = Cache::get('setting');
            $hasMail = !empty($setting?->mail_password ?? null);
            // After the audit's encryption + cache hook, cached read should be
            // plaintext (decrypted on the fly).
            $isPlain = $hasMail && !str_starts_with((string) $setting->mail_password, 'enc:v1:');
            $this->record('Cache', 'setting cached', $setting ? 'OK' : 'WARN', '');
            if ($hasMail) {
                $this->record('Cache', 'mail_password decrypts', $isPlain ? 'OK' : 'FAIL', $isPlain ? 'plaintext via cache hook' : 'NOT decrypting — check AppServiceProvider hook');
            }
        } catch (\Throwable $e) {
            $this->record('Cache', 'setting', 'FAIL', $e->getMessage());
        }
    }

    private function checkWebhookRoutes(): void
    {
        $expected = ['webhooks.stripe', 'webhooks.razorpay', 'webhooks.bkash', 'webhooks.paypal', 'webhooks.mercadopago'];
        $registered = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName())->keys();
        foreach ($expected as $r) {
            $status = $registered->contains($r) ? 'OK' : 'FAIL';
            $detail = $status === 'OK' ? '' : 'route not registered';
            $this->record('Webhook', $r, $status, $detail);
        }
    }

    private function checkLoginThrottle(): void
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName();
        foreach (['user-login', 'admin.store-login'] as $name) {
            if (!isset($routes[$name])) {
                $this->record('Auth', "$name throttle", 'FAIL', 'route missing');
                continue;
            }
            $middleware = $routes[$name]->middleware();
            $hasThrottle = collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'throttle'));
            $this->record('Auth', "$name throttle", $hasThrottle ? 'OK' : 'FAIL', $hasThrottle ? '' : 'NO throttle:5,1');
        }
    }

    private function checkHttpRoutes(): void
    {
        $base = rtrim(config('app.url'), '/');
        $urls = ['/', '/login', '/admin/login', '/courses'];
        foreach ($urls as $path) {
            $url = $base . $path;
            $start = microtime(true);
            $code = $this->httpGet($url);
            $ms = (int) ((microtime(true) - $start) * 1000);
            if ($code === null) {
                $this->record('HTTP', $path, 'WARN', 'unreachable (start Apache?)');
            } elseif ($code >= 500) {
                $this->record('HTTP', $path, 'FAIL', "$code in {$ms}ms");
            } else {
                $this->record('HTTP', $path, 'OK', "$code in {$ms}ms");
            }
        }
    }

    private function httpGet(string $url): ?int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code ?: null;
    }

    private function checkZoomHardening(): void
    {
        // 1. Signature route registered, POST, auth.
        $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName();
        $sig = $routes['zoom.sdk-signature'] ?? null;
        if (!$sig) {
            $this->record('Zoom', 'sdk-signature route', 'FAIL', 'route not registered');
        } else {
            $okMethod = in_array('POST', $sig->methods(), true);
            $mw = $sig->gatherMiddleware();
            $okAuth = in_array('auth', $mw, true) && in_array('verified', $mw, true);
            $okThrottle = (bool) array_filter($mw, fn ($m) => str_starts_with((string) $m, 'throttle'));
            if ($okMethod && $okAuth && $okThrottle) {
                $this->record('Zoom', 'sdk-signature route', 'OK', 'POST, auth+verified+throttle');
            } else {
                $this->record('Zoom', 'sdk-signature route', 'FAIL',
                    'expected POST + auth + verified + throttle, got: ' . implode(',', $mw));
            }
        }

        // 2. Live-class routes carry zoom.live.headers middleware.
        foreach (['student.learning.live', 'instructor.live-class'] as $name) {
            $r = $routes[$name] ?? null;
            if (!$r) {
                $this->record('Zoom', "$name middleware", 'WARN', 'route not registered');
                continue;
            }
            $has = in_array('zoom.live.headers', $r->gatherMiddleware(), true);
            $this->record('Zoom', "$name middleware", $has ? 'OK' : 'FAIL',
                $has ? 'zoom.live.headers wired' : 'missing zoom.live.headers (no COOP/COEP/CSP)');
        }

        // 3. Blade view does not reference client_secret / sdkSecret.
        $studentBlade = resource_path('views/frontend/student-dashboard/live/zoom.blade.php');
        if (is_file($studentBlade)) {
            $body = file_get_contents($studentBlade);
            $leak = str_contains($body, 'client_secret')
                 || str_contains($body, 'sdkSecret')
                 || str_contains($body, 'generateSDKSignature');
            $this->record('Zoom', 'student blade leak-free', $leak ? 'FAIL' : 'OK',
                $leak ? 'view still renders client_secret / sdkSecret / generateSDKSignature' : 'no secret in view');
        } else {
            $this->record('Zoom', 'student blade leak-free', 'WARN', 'view file not found');
        }

        // 4. Encryption casts on the model. Server-to-Server OAuth (the
        //    2026-05-08 migration) dropped zoom_refresh_token entirely — there
        //    are no refresh tokens to encrypt — so we don't check for it.
        //    account_id holds the Zoom account UUID and is encrypted because
        //    leaking it next to client_secret reduces an attacker's work;
        //    sdk_secret is the Meeting SDK signing key.
        $casts = (new \App\Models\ZoomCredential())->getCasts();
        foreach (['account_id', 'client_secret', 'sdk_secret', 'zoom_access_token'] as $col) {
            $ok = ($casts[$col] ?? null) === 'encrypted';
            $this->record('Zoom', "cast.$col", $ok ? 'OK' : 'FAIL',
                $ok ? 'encrypted' : 'cast missing — secrets readable as plaintext from DB');
        }

        // 5. Existing rows are encrypted at rest (only check if any rows exist).
        try {
            $row = DB::table('zoom_credentials')->whereNotNull('client_secret')->first();
            if (!$row) {
                $this->record('Zoom', 'rows encrypted at rest', 'WARN', 'no zoom_credentials rows to inspect');
            } else {
                try {
                    \Illuminate\Support\Facades\Crypt::decryptString($row->client_secret);
                    $this->record('Zoom', 'rows encrypted at rest', 'OK', 'client_secret decrypts cleanly');
                } catch (\Throwable $e) {
                    $this->record('Zoom', 'rows encrypted at rest', 'FAIL',
                        'PLAINTEXT — run 2026_05_06_120000_encrypt_zoom_credentials');
                }
            }
        } catch (\Throwable $e) {
            $this->record('Zoom', 'rows encrypted at rest', 'WARN', $e->getMessage());
        }
    }

    private function checkSanctumApi(): void
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName();
        $authBootstrap = ['api.patient-login', 'api.register', 'api.forget-password', 'api.reset-password'];
        foreach ($authBootstrap as $name) {
            $r = $routes[$name] ?? null;
            if (!$r) {
                $this->record('API', "$name throttle", 'WARN', 'route not registered');
                continue;
            }
            $hasThrottle = (bool) array_filter($r->gatherMiddleware(),
                fn ($m) => str_starts_with((string) $m, 'throttle'));
            $this->record('API', "$name throttle", $hasThrottle ? 'OK' : 'FAIL',
                $hasThrottle ? 'rate-limited' : 'OPEN — bruteforce / spam exposure');
        }

        $exp = config('sanctum.expiration');
        if ($exp === null) {
            $this->record('API', 'sanctum.expiration', 'FAIL',
                'null = tokens never expire — stolen device tokens stay valid forever');
        } else {
            $this->record('API', 'sanctum.expiration', 'OK', "$exp minutes");
        }

        $authedSrc = @file_get_contents(app_path('Http/Controllers/API/AuthenticatedController.php')) ?: '';
        $this->record('API', 'register role allowlist',
            str_contains($authedSrc, "'in:student,instructor'") ? 'OK' : 'FAIL',
            str_contains($authedSrc, "'in:student,instructor'") ? 'role limited' : "open 'role' validation lets POST role=admin self-elevate");

        $this->record('API', 'password min length',
            str_contains($authedSrc, "'min:8'") && !str_contains($authedSrc, "'min:4'") ? 'OK' : 'FAIL',
            str_contains($authedSrc, "'min:4'") ? 'min:4 still present' : 'min:8');

        $this->record('API', 'forget-password no enumeration',
            !str_contains($authedSrc, 'Email does not exist') ? 'OK' : 'FAIL',
            str_contains($authedSrc, 'Email does not exist') ? 'reveals registered emails' : 'uniform response');

        $this->record('API', 'token abilities scoped',
            !str_contains($authedSrc, "createToken('student', ['*'])") ? 'OK' : 'FAIL',
            str_contains($authedSrc, "createToken('student', ['*'])") ? "wildcard ['*'] abilities" : 'role-scoped');

        try {
            $hasCol = \Illuminate\Support\Facades\Schema::hasColumn('users', 'forget_password_token_expires_at');
            $this->record('API', 'forget_password_token TTL column', $hasCol ? 'OK' : 'FAIL',
                $hasCol ? 'expires_at column present' : 'run migration 2026_05_06_140000_add_forget_password_token_expiry');
        } catch (\Throwable $e) {
            $this->record('API', 'forget_password_token TTL column', 'WARN', $e->getMessage());
        }
    }

    private function checkFileUploadHardening(): void
    {
        // 1. public/uploads/.htaccess presence + key directives.
        $ht = public_path('uploads/.htaccess');
        if (!is_file($ht)) {
            $this->record('Upload', 'uploads/.htaccess', 'FAIL',
                'missing — runtime backstop against webshell execution gone');
        } else {
            $body = (string) @file_get_contents($ht);
            $hasEngineOff = str_contains($body, 'php_flag engine off');
            $hasDeny      = (bool) preg_match('/Require\s+all\s+denied/i', $body);
            $hasNoIndex   = str_contains($body, 'Options -Indexes');
            if ($hasEngineOff && $hasDeny && $hasNoIndex) {
                $this->record('Upload', 'uploads/.htaccess', 'OK', 'engine-off + Require denied + -Indexes');
            } else {
                $this->record('Upload', 'uploads/.htaccess', 'FAIL',
                    sprintf('engine_off=%s deny=%s noindex=%s',
                        $hasEngineOff ? 'Y' : 'N', $hasDeny ? 'Y' : 'N', $hasNoIndex ? 'Y' : 'N'));
            }
        }

        // 2. Helpers carry the allowlist + sanitizer.
        $src = (string) @file_get_contents(app_path('Helpers/helper.php'));
        $hasAllowlist = str_contains($src, 'FILE_UPLOAD_ALLOWED_EXTS');
        $hasSanitizer = str_contains($src, '_sanitize_upload_basename');
        $this->record('Upload', 'helper allowlist',
            $hasAllowlist ? 'OK' : 'FAIL',
            $hasAllowlist ? 'extension allowlist defined' : 'no allowlist — uploads accept any extension');
        $this->record('Upload', 'helper sanitizer',
            $hasSanitizer ? 'OK' : 'FAIL',
            $hasSanitizer ? 'filename sanitizer present' : 'sanitizer missing — path traversal possible');

        // 3. LFM disallowed_extensions covers the broader set.
        $disallowed = (array) config('lfm.disallowed_extensions');
        $required = ['php', 'phtml', 'phar', 'php5', 'svg', 'htaccess', 'exe'];
        $missing = array_values(array_diff($required, $disallowed));
        $this->record('Upload', 'LFM disallowed_extensions',
            empty($missing) ? 'OK' : 'FAIL',
            empty($missing) ? 'covers php-variants + svg + executables' : 'missing: ' . implode(', ', $missing));
    }

    private function checkTwoFactorThrottle(): void
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName();
        $names = [
            'admin.2fa.challenge.verify',
            'admin.2fa.challenge.recovery',
            'admin.2fa.confirm',
            'admin.2fa.disable',
            'admin.2fa.regenerate',
            'web.2fa.challenge.verify',
            'web.2fa.challenge.recovery',
            'web.2fa.confirm',
            'web.2fa.disable',
            'web.2fa.regenerate',
        ];
        foreach ($names as $name) {
            $r = $routes[$name] ?? null;
            if (!$r) {
                $this->record('2FA', "$name throttle", 'WARN', 'route not registered');
                continue;
            }
            $hasThrottle = (bool) array_filter($r->gatherMiddleware(),
                fn ($m) => str_starts_with((string) $m, 'throttle'));
            $this->record('2FA', "$name throttle", $hasThrottle ? 'OK' : 'FAIL',
                $hasThrottle ? 'rate-limited' : 'OPEN — TOTP bruteforceable');
        }

        // Static check that input lengths are bounded.
        foreach (['Frontend' => 'frontend', 'Admin' => 'admin'] as $ns => $tag) {
            $src = (string) @file_get_contents(app_path("Http/Controllers/$ns/TwoFactorController.php"));
            $bounded = str_contains($src, "'code' => ['required', 'string', 'size:6']");
            $this->record('2FA', "$tag code input bounded", $bounded ? 'OK' : 'FAIL',
                $bounded ? 'size:6' : 'unbounded — DoS risk');
        }
    }

    private function checkWebhookHardening(): void
    {
        // 1. The Order model used by every webhook controller must actually exist.
        //    Pre-audit they all imported App\Models\Order, which doesn't.
        $bad  = class_exists(\App\Models\Order::class);
        $good = class_exists(\Modules\Order\app\Models\Order::class);
        $this->record('Webhook', 'Order model resolves',
            (!$bad && $good) ? 'OK' : 'FAIL',
            $good ? 'Modules\\Order\\app\\Models\\Order present' : 'real Order class missing');

        // 2. No webhook controller still references the broken namespace.
        foreach ([
            'StripeWebhookController.php',
            'RazorpayWebhookController.php',
            'BkashWebhookController.php',
            'PaypalWebhookController.php',
            'MercadoPagoWebhookController.php',
        ] as $file) {
            $src = (string) @file_get_contents(app_path("Http/Controllers/Webhook/$file"));
            $broken = str_contains($src, 'App\\Models\\Order');
            $this->record('Webhook', "$file uses real Order",
                $broken ? 'FAIL' : 'OK',
                $broken ? 'still references App\\Models\\Order — Order::find() will crash on payment success' : 'imports Modules namespace');
        }

        // 2b. ALSO scan the non-webhook payment + checkout controllers.
        // The regression that brought this check in here originally was a
        // \App\Models\Order::lockForUpdate() call in PaymentController.php
        // line 973 — payment_success route 500'd because that class
        // doesn't exist in this codebase. Every controller that touches
        // Order rows must resolve them via Modules\Order\app\Models\Order.
        foreach ([
            'Modules/BasicPayment/app/Http/Controllers/PaymentController.php',
            'Modules/Order/app/Http/Controllers/CheckoutController.php',
        ] as $rel) {
            $abs = base_path($rel);
            if (! is_file($abs)) continue;   // module not installed → skip
            $src = (string) @file_get_contents($abs);
            // Match the fully-qualified bad reference (with or without
            // leading backslash). The valid `use Modules\...\Order` line
            // does not contain "App\Models\Order", so this is precise.
            $broken = (bool) preg_match('/\\\\?App\\\\Models\\\\Order(?:::|;|\s|$)/', $src);
            $base = basename($rel);
            $this->record('Webhook', "$base uses real Order",
                $broken ? 'FAIL' : 'OK',
                $broken ? 'still references App\\Models\\Order — payment-success will 500' : 'imports Modules namespace');
        }

        // 3. Every webhook route is throttled.
        $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName();
        foreach (['webhooks.stripe','webhooks.razorpay','webhooks.bkash','webhooks.paypal','webhooks.mercadopago'] as $name) {
            $r = $routes[$name] ?? null;
            if (!$r) {
                $this->record('Webhook', "$name throttle", 'FAIL', 'route missing');
                continue;
            }
            $hasThrottle = (bool) array_filter($r->gatherMiddleware(),
                fn ($m) => str_starts_with((string) $m, 'throttle'));
            $this->record('Webhook', "$name throttle",
                $hasThrottle ? 'OK' : 'FAIL',
                $hasThrottle ? 'rate-limited' : 'no throttle — burst-spam exposure');
        }
    }

    private function checkRawSqlInterpolation(): void
    {
        // Quick scan — full triage lives in RawSqlInjectionScanTest, but
        // surfacing the count here makes regressions visible at deploy time.
        // Audited allowed total: 9
        //   5 in DashboardController $expr (admin earnings formula)
        //   2 in InstructorDashboardController $earningsExpr (coach pulse,
        //     added 2026-05-19 phase 3)
        //   2 in this AuditSmoke command's `SHOW INDEX FROM \`$table\``
        // Every interpolant is a hardcoded internal constant, not user input.
        // Must mirror RawSqlInjectionScanTest::AUDITED_INTERPOLATIONS totals.
        $allowed = 9;
        $hits = 0;
        // Only double-quoted PHP strings interpolate $variables (single
        // quotes are literal). Same regex as RawSqlInjectionScanTest.
        $rawRe = '/(?:selectRaw|whereRaw|orWhereRaw|orderByRaw|havingRaw|groupByRaw|DB::raw|DB::statement|DB::select|DB::unprepared)\s*\(\s*"((?:[^"\\\\]|\\\\.)*\$(?:[^"\\\\]|\\\\.)*)"/';

        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                $body = (string) @file_get_contents($abs);
                if (preg_match_all($rawRe, $body, $m)) {
                    foreach ($m[1] as $sqlString) {
                        if (str_contains($sqlString, '?')) continue; // properly parameterised
                        $hits++;
                    }
                }
            }
        }

        if ($hits <= $allowed) {
            $this->record('SQL', 'raw-SQL interpolation count', 'OK',
                "$hits site(s) — within audited allowlist ($allowed)");
        } else {
            $this->record('SQL', 'raw-SQL interpolation count', 'FAIL',
                "$hits site(s) — exceeds audited allowlist ($allowed). Run RawSqlInjectionScanTest for details.");
        }
    }

    private function checkSessionAuthHardening(): void
    {
        $checks = [
            'web login regenerate' => [
                'file' => 'Http/Controllers/Auth/AuthenticatedSessionController.php',
                'needles' => ['$request->session()->regenerate();'],
                'msg' => 'session-fixation defense on web login',
            ],
            'admin login regenerate' => [
                'file' => 'Http/Controllers/Admin/Auth/AuthenticatedSessionController.php',
                'needles' => ['$request->session()->regenerate();'],
                'msg' => 'session-fixation defense on admin login',
            ],
            'web logout invalidate' => [
                'file' => 'Http/Controllers/Auth/AuthenticatedSessionController.php',
                'needles' => ['$request->session()->invalidate();', '$request->session()->regenerateToken();'],
                'msg' => 'session destroyed + CSRF rotated on logout',
            ],
            'admin logout invalidate' => [
                'file' => 'Http/Controllers/Admin/Auth/AuthenticatedSessionController.php',
                'needles' => ['$request->session()->invalidate();', '$request->session()->regenerateToken();'],
                'msg' => 'admin session destroyed + CSRF rotated',
            ],
            'forgot-password no enumeration' => [
                'file' => 'Http/Controllers/Auth/PasswordResetLinkController.php',
                'needles' => ['If that email is registered', 'forget_password_token_expires_at'],
                'msg' => 'web forgot-password matches API parity',
            ],
            'reset-password expiry + min:8' => [
                'file' => 'Http/Controllers/Auth/NewPasswordController.php',
                'needles' => ["'required|min:8|confirmed'", 'forget_password_token_expires_at', 'PersonalAccessToken::where'],
                'msg' => 'web reset-password gates on TTL + revokes tokens',
            ],
        ];

        foreach ($checks as $name => $cfg) {
            $src = (string) @file_get_contents(app_path($cfg['file']));
            $missing = array_filter($cfg['needles'], fn ($n) => !str_contains($src, $n));
            $this->record('Session', $name, empty($missing) ? 'OK' : 'FAIL',
                empty($missing) ? $cfg['msg'] : 'missing: ' . implode(', ', $missing));
        }

        // Cookie flags.
        $cfg = (string) @file_get_contents(config_path('session.php'));
        $secureOk = preg_match("/'secure'\s*=>\s*env\(\s*'SESSION_SECURE_COOKIE'\s*,\s*true\s*\)/", $cfg);
        $this->record('Session', 'cookie secure default',
            $secureOk ? 'OK' : 'FAIL',
            $secureOk ? 'env() default true' : "config/session.php 'secure' must default to env(..., true)");
        $httpOnly = config('session.http_only');
        $this->record('Session', 'cookie http_only',
            $httpOnly ? 'OK' : 'FAIL',
            $httpOnly ? 'on' : 'JS-readable cookie — XSS exposure');
    }

    private function checkCorsAndCsrfExempt(): void
    {
        // 1. The dangerous combo: allowed_origins=['*'] + supports_credentials=true.
        $origins = (array) config('cors.allowed_origins');
        $creds   = (bool)  config('cors.supports_credentials');
        $danger  = in_array('*', $origins, true) && $creds;
        $this->record('CORS', 'origin/credentials combo',
            $danger ? 'FAIL' : 'OK',
            $danger ? "allowed_origins=['*'] + supports_credentials=true is a CSRF-via-CORS bypass" : 'safe combo');

        // 2. CORS scope. Pinning to api/* + sanctum/csrf-cookie keeps the
        //    cross-origin attack surface bounded.
        $paths = (array) config('cors.paths');
        sort($paths);
        $expectedPaths = ['api/*', 'sanctum/csrf-cookie'];
        $this->record('CORS', 'cors.paths scope',
            $paths === $expectedPaths ? 'OK' : 'WARN',
            $paths === $expectedPaths ? 'api/* + sanctum/csrf-cookie' : 'unexpected: ' . implode(', ', $paths));

        // 3. CSRF exempt list lock — only known-safe endpoints.
        try {
            $mw = new \App\Http\Middleware\VerifyCsrfToken(app(), app('encrypter'));
            $r  = new \ReflectionClass($mw);
            $p  = $r->getProperty('except');
            $p->setAccessible(true);
            $except = $p->getValue($mw);
            sort($except);
            $expectedExcept = ['tinymce-delete-image', 'tinymce-upload-image', 'webhooks/*'];
            sort($expectedExcept);
            $this->record('CORS', 'CSRF exempt list',
                $except === $expectedExcept ? 'OK' : 'WARN',
                $except === $expectedExcept ? 'no drift' : 'drift: ' . implode(', ', $except));
        } catch (\Throwable $e) {
            $this->record('CORS', 'CSRF exempt list', 'WARN', $e->getMessage());
        }
    }

    private function checkMassAssignment(): void
    {
        // Same pattern set as MassAssignmentScanTest — full triage there,
        // surfacing here for ops visibility.
        $patterns = [
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

        $hits = 0;
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                // Skip this file — the pattern array below would self-match.
                if (basename($abs) === 'AuditSmoke.php') continue;
                $lines = @file($abs) ?: [];
                foreach ($lines as $line) {
                    if (preg_match('/^\s*(?:\/\/|\*|#)/', $line)) continue;
                    foreach ($patterns as $pat) {
                        if (str_contains($line, $pat)) $hits++;
                    }
                }
            }
        }

        $this->record('MassAssign', 'request->all() in writers',
            $hits === 0 ? 'OK' : 'FAIL',
            $hits === 0 ? '0 sites — every writer uses validate() / validated() / explicit fields'
                       : "$hits site(s) — run MassAssignmentScanTest for details");
    }

    private function checkOpenRedirect(): void
    {
        // Same pattern set as OpenRedirectScanTest. See that file for the
        // full triage; surfacing here for ops visibility at deploy time.
        $patterns = [
            'redirect($request->',
            'redirect(request()->',
            '->away($request->',
            '->away(request()->',
            '->to($request->',
            '->to(request()->',
            "session(['url.intended' => \$request",
            "session()->put('url.intended', \$request",
        ];

        $hits = 0;
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                // Self-match avoidance — pattern strings live in this file.
                if (basename($abs) === 'AuditSmoke.php') continue;
                $lines = @file($abs) ?: [];
                foreach ($lines as $line) {
                    if (preg_match('/^\s*(?:\/\/|\*|#)/', $line)) continue;
                    foreach ($patterns as $pat) {
                        if (str_contains($line, $pat)) $hits++;
                    }
                }
            }
        }

        $this->record('Redirect', 'user-input redirect targets',
            $hits === 0 ? 'OK' : 'FAIL',
            $hits === 0 ? '0 sites — every redirect target is server-built or gateway-API'
                       : "$hits site(s) — run OpenRedirectScanTest for details");
    }

    private function checkDangerousFunctions(): void
    {
        // Same regex set as DangerousFunctionsScanTest. Lines bearing the
        // explicit `PHPCS: audited-safe` marker are exempt (per-line audit
        // signoff). One known exemption today: ManageAddonController::490.
        $patterns = [
            '/\beval\s*\(/',
            '/\bunserialize\s*\(/',
            '/\bshell_exec\s*\(/',
            '/(?<![A-Za-z0-9_])exec\s*\(/',
            '/(?<![A-Za-z0-9_])system\s*\(/',
            '/\bpassthru\s*\(/',
            '/\bpopen\s*\(/',
            '/\bproc_open\s*\(/',
            '/\bcreate_function\s*\(/',
        ];

        $hits = 0;
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                $lines = @file($abs) ?: [];
                foreach ($lines as $line) {
                    if (preg_match('/^\s*(?:\/\/|\*|#)/', $line)) continue;
                    if (str_contains($line, 'PHPCS: audited-safe')) continue;
                    foreach ($patterns as $regex) {
                        if (preg_match($regex, $line)) { $hits++; break; }
                    }
                }
            }
        }

        $this->record('DangerousFn', 'eval/unserialize/shell',
            $hits === 0 ? 'OK' : 'FAIL',
            $hits === 0 ? '0 unmarked sites — every dangerous-fn call is either absent or audited-safe'
                       : "$hits site(s) — run DangerousFunctionsScanTest for details");
    }

    private function checkCoachPanelIdor(): void
    {
        // Per-coach-controller ownership-check primitive presence.
        // Same primitives as CoachPanelIdorTest. CoachStaffPermissionController
        // is intentionally read-only on the coach side (per 2026-05-01 audit)
        // and exempt.
        $primitives = [
            'effectiveCoachId(',
            "where('instructor_id', \$coachId",
            "where('instructor_id', userAuth()",
            "where('coach_id', \$coachId",
            "where('coach_id', userAuth()",
            // 2026-05-22 — explicit-cast variants used by CoachDomainController
            // and similar files that cast userAuth()->id to int defensively.
            "where('coach_id', (int) userAuth()",
            "where('instructor_id', (int) userAuth()",
            "where('user_id', (int) userAuth()",
            "where('added_by', (int) userAuth()",
            "where('user_id', \$coachId",
            "where('user_id', userAuth()",
            "where('user_id', auth('web')",
            "where('added_by', \$coachId",
            "where('added_by', userAuth()",
            'instructor_id == userAuth()',
            'instructor_id === userAuth()',
            "'instructor_id' => userAuth()->id",
            "'user_id' => userAuth()->id",
            "'instructor_id' => \$coachId",
            "instructor_id != auth('web')->user()->id",
            "instructor_id !== auth('web')->user()->id",
        ];
        $exempt = [
            app_path('Http/Controllers/Frontend/Coach/CoachStaffPermissionController.php'),
            app_path('Http/Controllers/Frontend/Coach/CoachBrandSettingController.php'),
            // OnboardingController: all methods scope via userAuth()
            // helper; no URL id parameters expose another coach. See
            // CoachPanelIdorTest::EXEMPT_FILES for the full rationale.
            app_path('Http/Controllers/Frontend/Coach/OnboardingController.php'),
            // 2026-05-26 white-label Phase 2 — student-facing commerce
            // controllers. The {coachSlug} URL param is resolved by the
            // TenantContext middleware against server-owned tables (no
            // attacker-controlled id substitution). All queries scope by
            // userAuth() (the LOGGED-IN STUDENT), not by coachSlug. See
            // CoachPanelIdorTest::EXEMPT_FILES for the full rationale.
            app_path('Http/Controllers/Frontend/Coach/CoachCartController.php'),
            app_path('Http/Controllers/Frontend/Coach/CoachCheckoutController.php'),
            app_path('Http/Controllers/Frontend/Coach/CoachAuthController.php'),
        ];

        $candidates = array_merge(
            glob(app_path('Http/Controllers/Frontend/Coach/*.php')) ?: [],
            glob(app_path('Http/Controllers/Frontend/Instructor*.php')) ?: []
        );

        $missing = 0;
        foreach ($candidates as $abs) {
            if (in_array($abs, $exempt, true)) continue;
            $body = (string) @file_get_contents($abs);
            $found = false;
            foreach ($primitives as $needle) {
                if (str_contains($body, $needle)) { $found = true; break; }
            }
            if (!$found) $missing++;
        }

        $total = count($candidates) - count($exempt);
        $this->record('CoachIDOR', 'ownership-check primitive coverage',
            $missing === 0 ? 'OK' : 'FAIL',
            $missing === 0 ? "$total/$total controllers carry an ownership-check primitive"
                          : "$missing/$total controller(s) missing — run CoachPanelIdorTest");
    }

    private function checkBladeXss(): void
    {
        // Surface any new unsanitised `{!! $var !!}` site that's not on
        // BladeXssScanTest::ALLOWLIST. Full triage there; this is the
        // ops-time visibility hook.
        $safe = ['clean(','strip_tags(','asset(','route(','url(','config(',
                 'json_encode(','sprintf(','__(','csrf_field(','method_field(','csrfToken(',
                 // Coach Marketing Website 2026-05-25 — see BladeXssScanTest::SAFE_WRAPPERS
                 'Str::markdown(', 'markdown(', 'nl2br(e('];
        $allowlist = [
            // admin line shifted 67 → 129 in the 2026-05-12 M10 CSS-extraction
            // rewrite (markup moved down by ~60 lines under a new <style>).
            'resources/views/admin/two-factor/setup.blade.php:129',
            'resources/views/frontend/two-factor/setup.blade.php:61',
            'resources/views/admin/partials/stat-trend.blade.php:9',
            'resources/views/frontend/instructor-dashboard/landing-page/create.blade.php:74',
            'resources/views/frontend/instructor-dashboard/landing-page/publish.blade.php:9',
            'resources/views/frontend/instructor-dashboard/landing-page/publish.blade.php:96',
            'resources/views/frontend/layouts/header-scripts.blade.php:70',
            // 2026-05-22 — master.blade.php line shifts from new <link>/<script>
            // tags inserted above customCode() outputs. Same admin-configured
            // CSS/JS content, just lower in the file.
            'resources/views/frontend/layouts/master.blade.php:29',
            'resources/views/frontend/layouts/master.blade.php:138',
            // 2026-05-19 phase 3 — admin operator-strip $renderDelta() builds
            // server-side HTML from cached numeric delta_pct values via
            // sprintf + number_format. No user input flows in.
            'resources/views/admin/partials/operator-strip.blade.php:66',
            'resources/views/admin/partials/operator-strip.blade.php:75',
            'resources/views/admin/partials/operator-strip.blade.php:84',
            'Modules/BasicPayment/resources/views/gateway-actions/flutterwave.blade.php:46',
            'Modules/BasicPayment/resources/views/gateway-actions/paystack.blade.php:45',
            'Modules/MercadoPagoPG/resources/views/payment-button.blade.php:29',
            'Modules/MercadoPagoPG/resources/views/payment-button.blade.php:31',
            // 2026-05-22 — coach dashboard $delta() closures render server-built
            // <span> with sprintf'd icon/colour + number_format($pct). Reviewed:
            // input is computed from cached numeric aggregates, no user input
            // flows in. Mirror of BladeXssScanTest::ALLOWLIST entries.
            // Lines shifted +17 on 2026-05-26 when the dashboard added
            // the hasActiveNewMembership check (so Lifetime / Trial
            // coaches no longer see the upsell modal).
            'resources/views/frontend/instructor-dashboard/index.blade.php:591',
            'resources/views/frontend/instructor-dashboard/index.blade.php:596',
            // Coach Marketing Website 2026-05-25 — see BladeXssScanTest::ALLOWLIST
            // for full justifications. $bodyHtml is server-composed from
            // SectionRenderer; html_passthrough_v1 is the legacy GrapesJS migration
            // escape hatch; custom_css/head/body are coach-input power-user code
            // (same risk as the existing landing-page/publish.blade.php allowlist).
            // Line numbers shift as the master layout grows. Latest pass
            // (2026-05-26) wired cart-icon + drawer URLs to route(coach.*)
            // for the white-label flow, shifting bodyHtml + body_scripts
            // down further. The {!! !!} usages themselves are unchanged.
            'resources/views/frontend/coach-site/layouts/master.blade.php:278',
            'resources/views/frontend/coach-site/layouts/master.blade.php:138',
            'resources/views/frontend/coach-site/layouts/master.blade.php:143',
            'resources/views/frontend/coach-site/layouts/master.blade.php:459',
            'resources/views/frontend/coach-site/sections/html_passthrough_v1.blade.php:5',
            'resources/views/frontend/coach-site/sections/html_passthrough_v1.blade.php:8',
        ];

        $hits = 0;
        foreach ([base_path('resources/views'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                if (!str_ends_with($abs, '.blade.php')) continue;

                $body = (string) @file_get_contents($abs);
                if (!preg_match_all('/\{!!\s*(.*?)\s*!!\}/s', $body, $m, PREG_OFFSET_CAPTURE)) continue;

                foreach ($m[1] as [$expr, $offset]) {
                    $line = substr_count($body, "\n", 0, $offset) + 1;
                    $rel = str_replace([base_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $abs);
                    if (in_array("$rel:$line", $allowlist, true)) continue;
                    $isSafe = false;
                    foreach ($safe as $needle) {
                        if (str_contains($expr, $needle)) { $isSafe = true; break; }
                    }
                    if (!$isSafe) $hits++;
                }
            }
        }

        $this->record('XSS', 'unsanitised {!! !!} sites',
            $hits === 0 ? 'OK' : 'FAIL',
            $hits === 0 ? '0 unaudited sites — every print uses clean() or is allowlisted'
                       : "$hits unaudited site(s) — run BladeXssScanTest for details");
    }

    private function checkFileServe(): void
    {
        $checks = [
            'download-resource enrollment + traversal guard' => [
                'file' => 'Http/Controllers/Frontend/LearningController.php',
                'needles' => [
                    'function downloadResource',
                    "->where('course_id', \$resource->course_id)",
                    "->where('has_access', 1)",
                    'realpath(public_path(',
                    "realpath(public_path('uploads')",
                    'str_starts_with($real, $allowed)',
                ],
            ],
            // 2026-06-01: printInvoice now scopes by course ownership
            // (coachOwnedOrderItemQuery) instead of seller_id — the latter
            // 404'd legitimate student-purchased orders. Same PII protection,
            // resolved as an order-ITEM id like the "View order" action.
            'instructor printInvoice ownership scope' => [
                'file' => 'Http/Controllers/Frontend/InstructorDashboardController.php',
                'needles' => ['coachOwnedOrderItemQuery()'],
            ],
            'API downloadCertificate escape' => [
                'file' => 'Http/Controllers/API/DashboardController.php',
                'needles' => [
                    'htmlspecialchars((string) $s',
                    "'[student_name]',    \$esc(\$user->name)",
                ],
            ],
        ];

        foreach ($checks as $name => $cfg) {
            $src = (string) @file_get_contents(app_path($cfg['file']));
            $missing = array_filter($cfg['needles'], fn ($n) => !str_contains($src, $n));
            $this->record('FileServe', $name,
                empty($missing) ? 'OK' : 'FAIL',
                empty($missing) ? 'guard present' : 'missing: ' . implode(', ', array_slice($missing, 0, 2)));
        }
    }

    private function checkAdminPermissionCoverage(): void
    {
        // Mirror of AdminPermissionTest. Files in EXEMPT only operate on
        // the calling admin's own data (e.g. own 2FA setup) and so don't
        // need a per-permission check.
        $exempt = [
            'TwoFactorController.php',
        ];
        $sensitive = [
            'index','create','store','edit','update','destroy','show',
            'changeStatus','approve','reject','pay','reverse','extend',
            'refund','cancel','confirm','updatePercent','updateSettings',
            'settings','conversionReport',
        ];
        $namePattern = implode('|', array_map('preg_quote', $sensitive));

        $missing = 0;
        foreach (glob(app_path('Http/Controllers/Admin/*.php')) ?: [] as $abs) {
            if (in_array(basename($abs), $exempt, true)) continue;
            $body = (string) @file_get_contents($abs);
            if (!preg_match_all(
                "/^\s*public\s+function\s+($namePattern)\s*\([^)]*\)[^{]*\{(.*?)(?=^\s*public\s+function|\z)/sm",
                $body, $m, PREG_SET_ORDER
            )) continue;
            foreach ($m as $hit) {
                if (!str_contains($hit[2], 'checkAdminHasPermissionAndThrowException')) {
                    $missing++;
                }
            }
        }

        $this->record('AdminPerms', 'permission-check coverage',
            $missing === 0 ? 'OK' : 'FAIL',
            $missing === 0 ? 'every sensitive admin method calls the check'
                          : "$missing method(s) missing — run AdminPermissionTest");
    }

    private function checkDebugDefaults(): void
    {
        // .env.example default-safety. The actual runtime APP_DEBUG /
        // APP_ENV are checked separately at the [Drivers] block (which
        // also handles the live env). This block guards against the
        // OTHER threat model: a future maintainer flipping the default
        // back to local/true and a fresh deploy inheriting it.
        $body = (string) @file_get_contents(base_path('.env.example'));

        $envOk    = (bool) preg_match('/^APP_ENV=production\b/m',   $body);
        $debugOk  = (bool) preg_match('/^APP_DEBUG=false\b/m',      $body);
        $logOk    = (bool) preg_match('/^LOG_LEVEL=(warning|error|critical|alert|emergency)\b/m', $body);

        $this->record('Debug', '.env.example APP_ENV=production', $envOk ? 'OK' : 'FAIL',
            $envOk ? 'safe default' : 'must be APP_ENV=production');
        $this->record('Debug', '.env.example APP_DEBUG=false',    $debugOk ? 'OK' : 'FAIL',
            $debugOk ? 'safe default' : 'must be APP_DEBUG=false');
        $this->record('Debug', '.env.example LOG_LEVEL safe',     $logOk ? 'OK' : 'FAIL',
            $logOk ? 'warning or stricter' : 'must be warning/error/critical');

        // Live env sanity: in prod, APP_DEBUG must be false.
        if (app()->environment('production')) {
            $this->record('Debug', 'live APP_DEBUG=false in prod',
                config('app.debug') ? 'FAIL' : 'OK',
                config('app.debug') ? 'APP_DEBUG=true on a production server — Whoops will leak DB queries' : 'debug off');
        }

        // Telescope sanity. 2026-06-17 — Telescope was REMOVED from the project
        // (it shipped to prod and exhausted DB connections). The desired state is
        // now its absence: no provider file + no registration. If the provider
        // file ever reappears AND is registered, flag it.
        $tsExists = file_exists(app_path('Providers/TelescopeServiceProvider.php'));
        $tsRegistered = str_contains((string) @file_get_contents(config_path('app.php')), 'TelescopeServiceProvider::class');
        $this->record('Debug', 'Telescope removed', (! $tsExists && ! $tsRegistered) ? 'OK' : 'FAIL',
            (! $tsExists && ! $tsRegistered)
                ? 'Telescope fully removed (no provider / not registered)'
                : 'Telescope provider has reappeared — remove it; it must not run on prod');
    }

    private function checkCronAndQueue(): void
    {
        // Database clear LIVE-mode lockout — see CronQueueTest.
        $src = (string) @file_get_contents(base_path(
            'Modules/GlobalSetting/app/Http/Controllers/GlobalSettingController.php'
        ));
        $hasLockout = (bool) preg_match(
            '/function\s+database_clear_success[^}]+strtoupper\(config\([\'"]app\.app_mode[\'"]\)\)\s*===\s*[\'"]LIVE[\'"]/s',
            $src
        );
        $this->record('Cron', 'database_clear LIVE mode lockout',
            $hasLockout ? 'OK' : 'FAIL',
            $hasLockout ? 'abort(403) when APP_MODE=LIVE'
                       : 'migrate:fresh exposed to admin HTTP — would destroy prod data');

        // Schedule prunes failed jobs.
        $kernel = (string) @file_get_contents(app_path('Console/Kernel.php'));
        $this->record('Cron', 'queue:prune-failed in schedule',
            str_contains($kernel, 'queue:prune-failed') ? 'OK' : 'FAIL',
            str_contains($kernel, 'queue:prune-failed') ? 'failed jobs rotate' : 'failed_jobs grows unbounded');
    }

    private function checkDependencyVersions(): void
    {
        // Same minima as DependencyAdvisoryTest. Surfacing here so a deploy
        // that forgot composer install reports the gap at smoke-time.
        $minima = [
            'unisharp/laravel-filemanager' => '2.9.1',
            'paragonie/sodium_compat'      => '2.5.0',
            'phpunit/phpunit'              => '10.5.62',
            'psy/psysh'                    => '0.12.18',
        ];

        $installed = [];
        $manifest = base_path('vendor/composer/installed.json');
        if (is_file($manifest)) {
            $data = json_decode((string) @file_get_contents($manifest), true);
            foreach ($data['packages'] ?? [] as $p) {
                $installed[$p['name']] = ltrim($p['version'], 'v');
            }
        }

        foreach ($minima as $name => $min) {
            $cur = $installed[$name] ?? null;
            if ($cur === null) {
                $this->record('Deps', $name, 'WARN', 'not installed');
                continue;
            }
            $ok = version_compare($cur, $min, '>=');
            $this->record('Deps', $name, $ok ? 'OK' : 'FAIL',
                $ok ? "v$cur >= $min" : "v$cur < required $min — `composer update`");
        }
    }

    private function checkEmailInjection(): void
    {
        // Scan for user input flowing into Mail recipients/subject/headers.
        $patterns = [
            'Mail::to($request->',
            'Mail::to(request()->',
            'Mail::cc($request->',
            'Mail::bcc($request->',
            '->subject($request->input',
            'addCustomHeaders($request',
            'addTextHeader($request',
        ];

        $hits = 0;
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                if (basename($abs) === 'AuditSmoke.php') continue;
                $body = (string) @file_get_contents($abs);
                foreach ($patterns as $pat) {
                    if (str_contains($body, $pat)) $hits++;
                }
            }
        }

        $this->record('Email', 'user-input mail recipient/header',
            $hits === 0 ? 'OK' : 'FAIL',
            $hits === 0 ? '0 sites — recipients all from validated input or DB' : "$hits site(s) — see EmailInjectionScanTest");
    }

    private function checkCacheKeyInjection(): void
    {
        $patterns = [
            'Cache::get($request->','Cache::put($request->','Cache::forget($request->',
            'Cache::remember($request->','Cache::rememberForever($request->','Cache::has($request->',
            'cache()->get($request->','cache()->put($request->','cache()->forget($request->',
            'cache()->remember($request->','cache()->forever($request->',
            'Cache::get(request()->','Cache::put(request()->',
        ];
        $hits = 0;
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                if (basename($abs) === 'AuditSmoke.php') continue;
                $body = (string) @file_get_contents($abs);
                foreach ($patterns as $pat) {
                    if (str_contains($body, $pat)) $hits++;
                }
            }
        }
        $this->record('Cache', 'request input as key',
            $hits === 0 ? 'OK' : 'FAIL',
            $hits === 0 ? '0 sites — every cache key is hardcoded or built from internal IDs' : "$hits site(s) — see CacheKeyInjectionScanTest");
    }

    private function checkBackupConfig(): void
    {
        // .env.example documents both backup-critical env vars.
        $env = (string) @file_get_contents(base_path('.env.example'));
        $hasPwd   = str_contains($env, 'BACKUP_ARCHIVE_PASSWORD');
        $hasMail  = str_contains($env, 'BACKUP_NOTIFY_EMAIL');
        $this->record('Backup', 'BACKUP_ARCHIVE_PASSWORD documented',
            $hasPwd ? 'OK' : 'FAIL',
            $hasPwd ? '.env.example notes it' : 'unset env → unencrypted backups');
        $this->record('Backup', 'BACKUP_NOTIFY_EMAIL documented',
            $hasMail ? 'OK' : 'FAIL',
            $hasMail ? '.env.example notes it' : 'failure alerts go to /dev/null');

        // The notification 'to' isn't the placeholder.
        $to = config('backup.notifications.mail.to');
        $this->record('Backup', 'notifications.mail.to set',
            $to !== 'your@example.com' ? 'OK' : 'FAIL',
            $to !== 'your@example.com' ? "→ $to" : 'placeholder — alerts vanish');

        // Live env: when running on prod, encryption password should resolve.
        if (app()->environment('production')) {
            $pwd = env('BACKUP_ARCHIVE_PASSWORD');
            $this->record('Backup', 'live BACKUP_ARCHIVE_PASSWORD set',
                !empty($pwd) ? 'OK' : 'FAIL',
                !empty($pwd) ? 'set' : 'EMPTY in prod env — backups land in cleartext');
        }
    }

    private function checkTemplateInjection(): void
    {
        $patterns = [
            'view($request->','view(request()->',
            '->view($request->','->view(request()->',
            'Blade::render($request->','Blade::render(request()->',
            'Blade::compileString($request->','Blade::compileString(request()->',
        ];
        $hits = 0;
        foreach ([base_path('app'), base_path('Modules')] as $root) {
            if (!is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $abs = $file->getPathname();
                if (str_contains($abs, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
                if (basename($abs) === 'AuditSmoke.php') continue;
                $body = (string) @file_get_contents($abs);
                foreach ($patterns as $pat) if (str_contains($body, $pat)) $hits++;
            }
        }
        $this->record('SSTI', 'view/Blade::render with request input',
            $hits === 0 ? 'OK' : 'FAIL',
            $hits === 0 ? '0 sites — every view() call uses a literal template name' : "$hits — see TemplateInjectionScanTest");
    }

    private function checkBroadcastAuth(): void
    {
        // routes/channels.php must define per-user PrivateChannel auth.
        $body = (string) @file_get_contents(base_path('routes/channels.php'));
        $hasUserChannel = str_contains($body, "Broadcast::channel('App.Models.User.{id}'");
        $hasIdCompare = str_contains($body, '$user->id') && str_contains($body, '$id');
        $this->record('Broadcast', 'per-user channel auth',
            ($hasUserChannel && $hasIdCompare) ? 'OK' : 'FAIL',
            ($hasUserChannel && $hasIdCompare) ? 'App.Models.User.{id} authed by user.id == id' : 'channel auth missing or unscoped');
    }

    private function printReport(): void
    {
        $this->newLine();
        $this->line('================================================');
        $sections = collect($this->checks)->groupBy('section');
        foreach ($sections as $section => $rows) {
            $ok = $rows->where('status', 'OK')->count();
            $warn = $rows->where('status', 'WARN')->count();
            $fail = $rows->where('status', 'FAIL')->count();
            $this->line(sprintf('%-12s  %d OK  %d WARN  %d FAIL', $section, $ok, $warn, $fail));
        }
        $this->line('================================================');
        $totalOk = collect($this->checks)->where('status', 'OK')->count();
        $totalWarn = collect($this->checks)->where('status', 'WARN')->count();
        $totalFail = collect($this->checks)->where('status', 'FAIL')->count();
        $this->line(sprintf('TOTAL:        %d OK  %d WARN  %d FAIL  /  %d checks',
            $totalOk, $totalWarn, $totalFail, count($this->checks)));

        if ($totalFail > 0) {
            $this->newLine();
            $this->error("Audit baseline has $totalFail failure(s). Investigate before deploying.");
        } elseif ($totalWarn > 0) {
            $this->newLine();
            $this->warn("Audit baseline OK with $totalWarn warnings. Review them.");
        } else {
            $this->newLine();
            $this->info('All audit baseline checks pass.');
        }
    }
}
