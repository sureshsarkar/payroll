<?php

namespace App\Console\Commands;

use App\Support\SecretSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off (idempotent) migrator for Tier 3.1 Phase 3.
 *
 * Walks the `settings` table and, for every row whose key is in
 * SecretSettings::SECRET_KEYS, encrypts the plaintext value in place.
 * Rows already prefixed with `enc:v1:` are skipped.
 *
 * USAGE:
 *   php artisan settings:encrypt-existing            (dry-run by default)
 *   php artisan settings:encrypt-existing --commit   (actually write)
 *
 * PRECONDITIONS — see docs/TIER3_UPGRADE_PLANS_2026-05-15.md #3.1:
 *   - Phase 0 done: every external credential has been rotated at its
 *     dashboard (Stripe, Razorpay, bKash, AWS, Wasabi, Gmail). If the
 *     plaintext values currently in the table leak, they must already
 *     be useless.
 *   - APP_KEY is the production key (not local). Otherwise the cipher
 *     text cannot be decrypted by production app servers.
 *
 * SAFETY:
 *   - Idempotent. Running twice does not double-encrypt (PREFIX check).
 *   - Dry-run is the default. Commit requires --commit.
 *   - Each row is wrapped in a single-row transaction so a mid-loop
 *     failure cannot leave a row half-encrypted.
 */
class SettingsEncryptExisting extends Command
{
    protected $signature = 'settings:encrypt-existing
                            {--commit : Write to DB. Without this flag the command is a dry-run.}';

    protected $description = 'Encrypt at-rest the SecretSettings::SECRET_KEYS rows in the settings table (idempotent).';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        if (!$commit) {
            $this->warn('DRY RUN — no rows will be written. Pass --commit to apply.');
        }

        $this->line('');
        $this->line('Scanning settings table for secret keys...');
        $this->line('Secret keys configured: '.implode(', ', SecretSettings::SECRET_KEYS));
        $this->line('');

        $rows = DB::table('settings')
            ->whereIn('key', SecretSettings::SECRET_KEYS)
            ->get(['id', 'key', 'value']);

        if ($rows->isEmpty()) {
            $this->info('No secret-keyed rows found in settings table. Nothing to do.');
            return self::SUCCESS;
        }

        $skipped = 0;
        $blank = 0;
        $updated = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $key = $row->key;
            $value = $row->value;

            if ($value === null || $value === '') {
                $this->line("  [skip-empty] {$key} (id={$row->id})");
                $blank++;
                continue;
            }

            if (SecretSettings::isEncrypted($value)) {
                $this->line("  [already-enc] {$key} (id={$row->id})");
                $skipped++;
                continue;
            }

            $encrypted = SecretSettings::encrypt($value);

            $this->line("  [encrypt] {$key} (id={$row->id}) — len {$this->lenOf($value)} -> {$this->lenOf($encrypted)}");

            if ($commit) {
                try {
                    DB::transaction(function () use ($row, $encrypted) {
                        DB::table('settings')
                            ->where('id', $row->id)
                            ->where('value', $row->value)
                            ->update(['value' => $encrypted]);
                    });
                    $updated++;
                } catch (\Throwable $e) {
                    $this->error('    failed: '.$e->getMessage());
                    $failed++;
                }
            }
        }

        $this->line('');
        $this->info(sprintf(
            'Done. encrypted=%d  already=%d  blank=%d  failed=%d  total=%d',
            $updated,
            $skipped,
            $blank,
            $failed,
            $rows->count(),
        ));

        if (!$commit && $rows->where('value', '!=', '')->count() > $skipped) {
            $this->line('');
            $this->warn('Re-run with --commit to apply.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function lenOf(?string $s): int
    {
        return $s === null ? 0 : strlen($s);
    }
}
