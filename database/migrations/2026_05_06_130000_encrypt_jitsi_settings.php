<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypt-at-rest backfill for `jitsi_settings.api_key`.
 *
 * Pre-audit this column held the JaaS API key in plaintext. Combined with
 * the on-disk `storage/app/user_{id}/rsb_private_key.pk`, an attacker who
 * read the DB could mint moderator JWTs for the instructor's room — a
 * full meeting-takeover primitive. Encryption matches the same pattern
 * applied to zoom_credentials in 2026_05_06_120000.
 *
 * Schema change: `api_key` varchar(255) → text, so the encryption envelope
 * (typically 250-1000+ chars) fits.
 *
 * Idempotent: rows that already decrypt cleanly are skipped, so re-running
 * the migration on a partially-encrypted table is safe.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('jitsi_settings')) {
            return; // module/installation may not have it yet
        }

        // Widen api_key so the encrypted envelope fits.
        Schema::table('jitsi_settings', function (Blueprint $table) {
            $table->text('api_key')->change();
        });

        $rows = DB::table('jitsi_settings')->get(['id', 'api_key']);
        $encrypted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $value = $row->api_key;
            if ($value === null || $value === '') continue;

            if ($this->looksEncrypted($value)) {
                $skipped++;
                continue;
            }

            DB::table('jitsi_settings')
                ->where('id', $row->id)
                ->update(['api_key' => Crypt::encryptString((string) $value)]);
            $encrypted++;
        }

        \Log::info("jitsi_settings encryption backfill: encrypted=$encrypted, skipped=$skipped");
    }

    public function down(): void
    {
        if (!Schema::hasTable('jitsi_settings')) {
            return;
        }

        // Decrypt back to plaintext on rollback (matches up()'s schema-change
        // strategy: leave the column as text — a varchar shrink could truncate
        // any rows that exceed 255 chars and is irreversible without inspection).
        $rows = DB::table('jitsi_settings')->get(['id', 'api_key']);
        foreach ($rows as $row) {
            $value = $row->api_key;
            if ($value === null || $value === '') continue;
            if (!$this->looksEncrypted($value)) continue;

            try {
                DB::table('jitsi_settings')
                    ->where('id', $row->id)
                    ->update(['api_key' => Crypt::decryptString((string) $value)]);
            } catch (\Throwable $e) {
                \Log::warning("Could not decrypt jitsi_settings.api_key on row {$row->id}: " . $e->getMessage());
            }
        }
    }

    private function looksEncrypted(string $value): bool
    {
        if (strlen($value) < 100) return false;
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
