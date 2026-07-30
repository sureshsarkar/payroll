<?php

use App\Support\SecretSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: encrypt at-rest the existing plaintext values for every Setting
 * whose key is in SecretSettings::SECRET_KEYS. Idempotent — rows already
 * carrying the `enc:v1:` prefix are skipped.
 *
 * `down()` decrypts in place. After this migration runs, app code reads the
 * Setting cache via AppServiceProvider which transparently decrypts.
 */
return new class extends Migration {
    public function up(): void
    {
        $rows = DB::table('settings')
            ->whereIn('key', SecretSettings::SECRET_KEYS)
            ->get();

        $encrypted = 0;
        foreach ($rows as $row) {
            if ($row->value === null || $row->value === '' || SecretSettings::isEncrypted($row->value)) {
                continue;
            }
            DB::table('settings')
                ->where('id', $row->id)
                ->update(['value' => SecretSettings::encrypt($row->value)]);
            $encrypted++;
        }

        \Log::info("Settings backfill: encrypted $encrypted secret-keyed rows");

        // Force the cached `setting` blob to rebuild on next request so consumers
        // see the new encrypted-then-decrypted values via the AppServiceProvider hook.
        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    public function down(): void
    {
        $rows = DB::table('settings')
            ->whereIn('key', SecretSettings::SECRET_KEYS)
            ->get();

        $decrypted = 0;
        foreach ($rows as $row) {
            if (!SecretSettings::isEncrypted($row->value)) {
                continue;
            }
            $plain = SecretSettings::decrypt($row->value);
            DB::table('settings')->where('id', $row->id)->update(['value' => $plain]);
            $decrypted++;
        }
        \Log::info("Settings backfill: decrypted $decrypted secret-keyed rows (rollback)");
        \Illuminate\Support\Facades\Cache::forget('setting');
    }
};
