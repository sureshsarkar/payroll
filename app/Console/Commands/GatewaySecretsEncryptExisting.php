<?php

namespace App\Console\Commands;

use App\Support\SecretSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit 2026-05-18 phase 5 — one-off migrator for payment-gateway tables.
 *
 * Walks payment_gateways, bkash_p_g_models, and crypto_p_g and encrypts
 * every plaintext row whose key is in SecretSettings::TABLE_SECRETS for
 * that table.
 *
 * USAGE:
 *   php artisan gateway-secrets:encrypt-existing             (dry-run by default)
 *   php artisan gateway-secrets:encrypt-existing --commit    (apply)
 *
 * PRECONDITIONS:
 *   1. Rotate every external credential first (Stripe / Razorpay / bKash /
 *      Crypto / etc.). The current plaintext values must already be
 *      invalidated at the provider so a leaked DB snapshot is harmless.
 *   2. APP_KEY must be the PRODUCTION key (decryption is keyed to APP_KEY).
 *
 * Idempotent — already-encrypted rows (enc:v1: prefix) are skipped.
 *
 * Cache-clear step: after committing, clear the cache so the next request
 * rebuilds payment_setting / bkashConfig / cryptoConfig via the new
 * decrypt-at-build code path (BkashPG/CryptoPayment service providers
 * + helper.php).
 */
class GatewaySecretsEncryptExisting extends Command
{
    protected $signature = 'gateway-secrets:encrypt-existing
                            {--commit : Write to DB. Without this flag the command is a dry-run.}';

    protected $description = 'Encrypt at-rest the secret-keyed rows in payment_gateways / bkash_p_g_models / crypto_p_g (idempotent).';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        if (!$commit) {
            $this->warn('DRY RUN — no rows will be written. Pass --commit to apply.');
        }

        $tables = [
            'payment_gateways'  => 'key',
            'bkash_p_g_models'  => 'key',
            'crypto_p_g'        => 'key',
        ];

        $totalUpdated = 0;
        $totalAlready = 0;
        $totalBlank   = 0;
        $totalFailed  = 0;

        foreach ($tables as $table => $keyColumn) {
            $secretKeys = SecretSettings::secretKeysForTable($table);
            if (empty($secretKeys)) continue;

            $this->line('');
            $this->info("=== {$table} ===");
            $this->line('  secret keys: '.implode(', ', $secretKeys));

            $rows = DB::table($table)
                ->whereIn($keyColumn, $secretKeys)
                ->get(['id', $keyColumn, 'value']);

            if ($rows->isEmpty()) {
                $this->line('  No secret-keyed rows present.');
                continue;
            }

            foreach ($rows as $row) {
                $value = $row->value;
                $name  = $row->{$keyColumn};

                if ($value === null || $value === '') {
                    $this->line("  [skip-empty] {$name}");
                    $totalBlank++;
                    continue;
                }
                if (SecretSettings::isEncrypted($value)) {
                    $this->line("  [already-enc] {$name}");
                    $totalAlready++;
                    continue;
                }

                $encrypted = SecretSettings::encrypt($value);
                $this->line(sprintf(
                    '  [encrypt] %-25s (%d -> %d bytes)',
                    $name, strlen($value), strlen($encrypted)
                ));

                if ($commit) {
                    try {
                        DB::transaction(function () use ($table, $keyColumn, $row, $encrypted) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->where($keyColumn, $row->{$keyColumn})
                                ->where('value', $row->value)
                                ->update(['value' => $encrypted, 'updated_at' => now()]);
                        });
                        $totalUpdated++;
                    } catch (\Throwable $e) {
                        $this->error('    failed: '.$e->getMessage());
                        $totalFailed++;
                    }
                }
            }
        }

        // Bust caches so the next request rebuilds payment_setting / bkashConfig / cryptoConfig.
        if ($commit) {
            $this->line('');
            try {
                cache()->forget('payment_setting');
                cache()->forget('bkashConfig');
                cache()->forget('cryptoConfig');
                $this->info('Cache keys forgotten: payment_setting, bkashConfig, cryptoConfig');
            } catch (\Throwable $e) {
                $this->warn('Cache forget failed (run `php artisan cache:clear` manually): '.$e->getMessage());
            }
        }

        $this->line('');
        $this->info(sprintf(
            'Done. encrypted=%d  already=%d  blank=%d  failed=%d',
            $totalUpdated, $totalAlready, $totalBlank, $totalFailed
        ));

        if (!$commit && $totalAlready + $totalBlank < 999) {
            $this->line('');
            $this->warn('Re-run with --commit to apply.');
        }

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
