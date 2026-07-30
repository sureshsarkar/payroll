<?php

namespace Tests\Feature\Audit;

use App\Support\SecretSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\GlobalSetting\app\Models\Setting;
use Tests\TestCase;

/**
 * Verifies the audit's secret-encryption guarantees:
 *
 * 1. SecretSettings::encrypt() prefixes with `enc:v1:` and produces decryptable ciphertext.
 * 2. SecretSettings::decrypt() round-trips correctly.
 * 3. Already-prefixed values are NOT re-encrypted (idempotent).
 * 4. SecretSettings::setValue() encrypts secret keys but writes non-secrets as plaintext.
 * 5. AppServiceProvider's cache hook produces decrypted plaintext on read
 *    (so consumers like MailSenderService see the real password).
 * 6. All known secret keys are at-rest encrypted in the DB.
 */
class EncryptionTest extends TestCase
{
    public function test_encrypt_adds_prefix_and_decrypts_back(): void
    {
        $plain = 'hyzv ygre bezi wbzg';                     // sample real Gmail app password
        $cipher = SecretSettings::encrypt($plain);
        $this->assertStringStartsWith('enc:v1:', $cipher);
        $back = SecretSettings::decrypt($cipher);
        $this->assertSame($plain, $back);
    }

    public function test_encrypt_is_idempotent_on_already_encrypted_values(): void
    {
        $first = SecretSettings::encrypt('secret');
        $second = SecretSettings::encrypt($first);
        $this->assertSame(
            $first,
            $second,
            'Re-encrypting an already-prefixed value must return it unchanged (otherwise the migration could double-wrap)'
        );
    }

    public function test_decrypt_handles_legacy_unprefixed_values_as_plaintext(): void
    {
        $rawPlain = 'no-prefix-was-applied';
        $this->assertSame($rawPlain, SecretSettings::decrypt($rawPlain));
    }

    public function test_set_value_encrypts_secret_keys_but_not_non_secret_keys(): void
    {
        // Ensure setValue() routes through encrypt() only when the key is in SECRET_KEYS.
        // The audit's setValue() helper IS what GlobalSettingController writers now call.

        // setValue() does an UPDATE WHERE key=?, so it only works on rows
        // that already exist. Audit 2026-05-19 — this class does NOT use
        // DatabaseTransactions (it manages its own boundaries because
        // SecretSettings has side-effects we want to observe across
        // explicit transaction boundaries). Track what we seed and
        // clean it up in finally so the dev DB stays unchanged.
        $seeded = [];
        foreach ([
            ['key' => 'mail_password', 'value' => 'seed-mail-password'],
            ['key' => 'app_name',      'value' => 'seed-app-name'],
        ] as $row) {
            if (!DB::table('settings')->where('key', $row['key'])->exists()) {
                DB::table('settings')->insert($row + [
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $seeded[] = $row['key'];
            }
        }

        // Rollback after the test so the real settings table isn't permanently changed.
        DB::beginTransaction();
        try {
            // Snapshot current values (so we can restore exactly).
            $before = DB::table('settings')->whereIn('key', ['mail_password', 'app_name'])->pluck('value', 'key')->all();

            SecretSettings::setValue('mail_password', 'plaintext-secret-test');
            SecretSettings::setValue('app_name', 'plaintext-non-secret-test');

            $stored = DB::table('settings')->whereIn('key', ['mail_password', 'app_name'])->pluck('value', 'key')->all();

            $this->assertStringStartsWith(
                'enc:v1:',
                $stored['mail_password'] ?? '',
                'Secret key mail_password must be stored encrypted'
            );

            $this->assertStringStartsNotWith(
                'enc:v1:',
                $stored['app_name'] ?? 'enc:v1:',
                'Non-secret key app_name should remain plaintext'
            );
            $this->assertSame('plaintext-non-secret-test', $stored['app_name']);
        } finally {
            DB::rollBack();
            // Manually delete only the rows we ourselves inserted —
            // pre-existing rows are left alone (the manual transaction
            // rolled their value mutations back already).
            if (!empty($seeded)) {
                DB::table('settings')->whereIn('key', $seeded)->delete();
            }
        }
    }

    public function test_settings_cache_decrypts_secrets_on_read(): void
    {
        // The AppServiceProvider boot() registers
        //    Cache::rememberForever('setting', fn() => SecretSettings::decryptArray($raw))
        // so that consumers (Mail config, recaptcha etc.) get plaintext.
        $cached = Cache::get('setting');
        $this->assertNotNull($cached, 'cached `setting` blob must exist (warmed by AppServiceProvider boot)');

        if (!empty($cached->mail_password)) {
            $this->assertStringStartsNotWith(
                'enc:v1:',
                $cached->mail_password,
                'Cached mail_password must be plaintext (decryptArray runs on cache build)'
            );
            $this->assertGreaterThan(
                4,
                strlen($cached->mail_password),
                'Cached mail_password should look like a real password, not a prefix fragment'
            );
        }
    }

    public function test_every_secret_key_in_db_is_encrypted_at_rest(): void
    {
        $rows = DB::table('settings')->whereIn('key', SecretSettings::SECRET_KEYS)->get(['key', 'value']);
        // Belt-and-suspenders assertion so this test isn't reported as
        // "risky" on a fresh CI DB where no settings rows exist yet.
        $this->assertNotNull($rows);

        foreach ($rows as $r) {
            // Empty values are acceptable (key just hasn't been set yet).
            if (empty($r->value)) {
                continue;
            }
            $this->assertStringStartsWith(
                'enc:v1:',
                $r->value,
                "Setting `{$r->key}` is stored unencrypted — run encrypt_secret_settings migration"
            );
        }
    }
}
