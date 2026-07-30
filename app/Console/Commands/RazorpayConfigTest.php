<?php

namespace App\Console\Commands;

use App\Support\SecretSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * razorpay:test — verify the platform is ready to accept Razorpay payments.
 *
 * Probes four things in order:
 *   1. payment_gateways.razorpay_key   resolvable (and not the literal
 *                                       placeholder string)
 *   2. payment_gateways.razorpay_secret resolvable + decryptable via
 *                                       SecretSettings::decryptForTable
 *   3. payment_gateways.razorpay_status = 'active'
 *   4. RAZORPAY_WEBHOOK_SECRET env / services.razorpay.webhook_secret
 *
 * With --ping (off by default) it also calls api.razorpay.com to
 * verify the key/secret pair actually authenticates against the
 * gateway. Skipped by default because it costs a network round-trip
 * and might trip rate-limits.
 *
 * Exit code:
 *   0  — everything green, gateway ready
 *   1  — at least one FAIL — gateway NOT ready
 *   2  — only WARN — gateway will work but with caveats
 */
class RazorpayConfigTest extends Command
{
    protected $signature = 'razorpay:test
                            {--ping : Also call api.razorpay.com to verify the credentials authenticate}';

    protected $description = 'Verify Razorpay credentials, status, webhook secret, and (optionally) API reachability.';

    public function handle(): int
    {
        $pass = 0; $warn = 0; $fail = 0;

        $check = function (string $label, string $status, string $detail) use (&$pass, &$warn, &$fail) {
            switch ($status) {
                case 'OK':
                    $this->info(sprintf('  [PASS] %-30s %s', $label, $detail));
                    $pass++;
                    break;
                case 'WARN':
                    $this->warn(sprintf('  [WARN] %-30s %s', $label, $detail));
                    $warn++;
                    break;
                case 'FAIL':
                    $this->error(sprintf('  [FAIL] %-30s %s', $label, $detail));
                    $fail++;
                    break;
            }
        };

        $this->line('');
        $this->line('Razorpay readiness check');
        $this->line(str_repeat('=', 60));

        // ── 1. razorpay_key ───────────────────────────────────
        $kv = DB::table('payment_gateways')
            ->whereIn('key', ['razorpay_key', 'razorpay_secret', 'razorpay_status'])
            ->pluck('value', 'key')
            ->all();
        $decrypted = SecretSettings::decryptForTable('payment_gateways', $kv);

        $key    = $decrypted['razorpay_key']    ?? null;
        $secret = $decrypted['razorpay_secret'] ?? null;
        $status = $decrypted['razorpay_status'] ?? null;

        if (!$key || $key === 'razorpay_key') {
            $check('razorpay_key', 'FAIL', 'missing or placeholder. Set in admin → Payment Gateway.');
        } else {
            $env = str_starts_with($key, 'rzp_live_') ? 'LIVE' : (str_starts_with($key, 'rzp_test_') ? 'TEST' : 'unknown');
            $check('razorpay_key', 'OK', $env . ' key configured (' . substr($key, 0, 12) . '...)');
        }

        // ── 2. razorpay_secret ─────────────────────────────────
        if (!$secret || $secret === 'razorpay_secret') {
            $check('razorpay_secret', 'FAIL', 'missing or placeholder.');
        } else {
            // Is the at-rest row encrypted?
            $rawSecret = $kv['razorpay_secret'] ?? '';
            $encrypted = SecretSettings::isEncrypted((string) $rawSecret);
            if ($encrypted) {
                $check('razorpay_secret', 'OK', 'present + encrypted at rest (enc:v1:)');
            } else {
                $check('razorpay_secret', 'WARN',
                    'present but stored PLAINTEXT in payment_gateways table. ' .
                    'Run: php artisan gateway-secrets:encrypt-existing --commit');
            }
        }

        // ── 3. razorpay_status ─────────────────────────────────
        if ($status === 'active') {
            $check('razorpay_status', 'OK', 'active — students will see Razorpay as a payment option');
        } else {
            $check('razorpay_status', 'WARN',
                "is '$status', students won't see Razorpay. Flip to 'active' in admin → Payment Gateway.");
        }

        // ── 4. webhook secret ──────────────────────────────────
        $webhookSecret = config('services.razorpay.webhook_secret') ?: env('RAZORPAY_WEBHOOK_SECRET');
        if (empty($webhookSecret)) {
            $check('RAZORPAY_WEBHOOK_SECRET', 'FAIL',
                'env var missing. /webhooks/razorpay will return 500.');
        } elseif (strlen($webhookSecret) < 16) {
            $check('RAZORPAY_WEBHOOK_SECRET', 'WARN',
                'set but very short (' . strlen($webhookSecret) . ' chars). Razorpay-recommended length is 32+.');
        } else {
            $check('RAZORPAY_WEBHOOK_SECRET', 'OK',
                'set (' . strlen($webhookSecret) . ' chars).');
        }

        // ── 5. optional: ping the gateway ───────────────────────
        if ($this->option('ping')) {
            if (!$key || !$secret || $key === 'razorpay_key' || $secret === 'razorpay_secret') {
                $check('Razorpay API ping', 'WARN', 'skipped — credentials missing.');
            } else {
                try {
                    $api = new \Razorpay\Api\Api($key, $secret);
                    // Cheapest valid call — list 1 payment.
                    $api->payment->all(['count' => 1]);
                    $check('Razorpay API ping', 'OK', 'authenticated against api.razorpay.com');
                } catch (\Throwable $e) {
                    $check('Razorpay API ping', 'FAIL',
                        get_class($e) . ': ' . substr($e->getMessage(), 0, 100));
                }
            }
        } else {
            $check('Razorpay API ping', 'WARN',
                'skipped. Add --ping to verify credentials against api.razorpay.com.');
        }

        $this->line('');
        $this->line(str_repeat('=', 60));
        $this->line(sprintf('PASS=%d  WARN=%d  FAIL=%d', $pass, $warn, $fail));
        $this->line('');

        if ($fail > 0) {
            $this->error('Razorpay is NOT ready. Fix the FAIL items above before going live.');
            return 1;
        }
        if ($warn > 0) {
            $this->warn('Razorpay will work, but address the WARN items before launch.');
            return 2;
        }
        $this->info('All checks green — Razorpay ready for production.');
        return 0;
    }
}
