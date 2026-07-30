<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encrypt-at-rest backfill for `zoom_credentials.client_secret`,
 * `zoom_access_token`, and `zoom_refresh_token`.
 *
 * Pre-audit these were stored plaintext. The matching Eloquent `encrypted`
 * cast on the model now handles new writes/reads transparently, but
 * existing rows must be wrapped.
 *
 * Idempotent: any value that ALREADY decrypts cleanly (i.e. is already
 * encrypted) is skipped, so re-running the migration is safe.
 */
return new class extends Migration {
    private array $columns = [
        'client_secret',
        'zoom_access_token',
        'zoom_refresh_token',
    ];

    public function up(): void
    {
        // Only encrypt columns that actually exist on this DB. The OAuth
        // token columns were a manual ALTER in some dev environments — the
        // matching precursor migration 2026_05_06_110000 backfills them,
        // but we still defend against running on a DB shape that's lacking
        // any of the three.
        $columns = array_values(array_filter(
            $this->columns,
            fn ($c) => \Illuminate\Support\Facades\Schema::hasColumn('zoom_credentials', $c)
        ));
        if (empty($columns)) {
            return;
        }

        $rows = DB::table('zoom_credentials')->get(['id', ...$columns]);

        $encrypted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $update = [];
            foreach ($columns as $col) {
                $value = $row->{$col} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                if ($this->looksEncrypted($value)) {
                    $skipped++;
                    continue;
                }

                $update[$col] = Crypt::encryptString((string) $value);
                $encrypted++;
            }

            if (!empty($update)) {
                DB::table('zoom_credentials')->where('id', $row->id)->update($update);
            }
        }

        \Log::info("zoom_credentials encryption backfill: encrypted=$encrypted, skipped=$skipped");
    }

    public function down(): void
    {
        // Reverse: decrypt back to plaintext (needed if you roll back).
        $rows = DB::table('zoom_credentials')->get(['id', ...$this->columns]);

        foreach ($rows as $row) {
            $update = [];
            foreach ($this->columns as $col) {
                $value = $row->{$col};
                if ($value === null || $value === '') continue;
                if (!$this->looksEncrypted($value)) continue;

                try {
                    $update[$col] = Crypt::decryptString((string) $value);
                } catch (\Throwable $e) {
                    \Log::warning("Could not decrypt zoom_credentials.$col on row {$row->id}: " . $e->getMessage());
                }
            }
            if (!empty($update)) {
                DB::table('zoom_credentials')->where('id', $row->id)->update($update);
            }
        }
    }

    /**
     * Laravel's encryptString output is base64 of a JSON envelope. The values
     * are non-printable random padding before that, but we can detect via
     * try-decrypt: if it decrypts successfully, it's already encrypted.
     */
    private function looksEncrypted(string $value): bool
    {
        // Cheap pre-check: encrypted strings are long and base64-ish
        if (strlen($value) < 100) return false;

        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
