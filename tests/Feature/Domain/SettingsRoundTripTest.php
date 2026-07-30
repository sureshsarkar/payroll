<?php

namespace Tests\Feature\Domain;

use App\Support\SecretSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Domain test — Settings round-trip (Tier C category).
 *
 * Each test verifies the contract: write a setting -> cache invalidates ->
 * subsequent read returns the new value -> SECRET_KEYS land encrypted.
 *
 * No browser needed — exercises the DB <-> Cache <-> SecretSettings layer
 * end-to-end via DB::table('settings') updates.
 */
class SettingsRoundTripTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('setting');
        // Ensure the keys exercised by these tests exist in mbs_test —
        // fresh CI DBs don't have the seeder pre-run.
        foreach ([
            'app_name', 'timezone', 'mail_password', 'aws_secret_key',
            'recaptcha_secret_key', 'pusher_app_secret',
        ] as $key) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => '', 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    protected function tearDown(): void
    {
        // Cache driver is array (process-lifetime) per phpunit.xml.
        // Without this, later tests that depend on a clean / empty
        // `setting` cache (e.g. MailTemplateTest skip guards) see our
        // pollution. Forget the cache so each test class starts clean.
        Cache::forget('setting');
        parent::tearDown();
    }

    public function test_writing_app_name_persists_to_db_and_cache(): void
    {
        $new = 'AuditTestApp_'.uniqid();

        DB::table('settings')->where('key', 'app_name')->update(['value' => $new]);
        Cache::forget('setting');

        $this->assertSame($new, DB::table('settings')->where('key', 'app_name')->value('value'));

        $cached = Cache::rememberForever('setting', function () {
            return (object) SecretSettings::decryptArray(
                \Modules\GlobalSetting\app\Models\Setting::pluck('value', 'key')->all()
            );
        });
        $this->assertSame($new, $cached->app_name);
    }

    public function test_writing_a_secret_key_via_setvalue_encrypts_it(): void
    {
        $plaintext = 'super-secret-mail-pw';   // short to fit varchar(255) cipher

        SecretSettings::setValue('mail_password', $plaintext);

        $raw = DB::table('settings')->where('key', 'mail_password')->value('value');
        $this->assertNotNull($raw, 'raw value should not be null after setValue');
        $this->assertTrue(SecretSettings::isEncrypted($raw),
            'mail_password should land with enc:v1: prefix; got: '.substr($raw, 0, 40));

        $decrypted = SecretSettings::decrypt($raw);
        $this->assertSame($plaintext, $decrypted,
            'decrypt returned: '.var_export($decrypted, true).' (raw len='.strlen($raw).')');
    }

    public function test_writing_a_non_secret_key_via_setvalue_does_not_encrypt(): void
    {
        $plaintext = 'IST';

        SecretSettings::setValue('timezone', $plaintext);

        $raw = DB::table('settings')->where('key', 'timezone')->value('value');
        $this->assertSame($plaintext, $raw,
            'timezone is not a SECRET_KEY so should be stored plaintext.');
    }

    public function test_cache_returns_decrypted_secret_to_readers(): void
    {
        $plaintext = 'leaked-pw';   // keep short; settings.value is varchar(255)
        SecretSettings::setValue('mail_password', $plaintext);
        Cache::forget('setting');

        $cached = (object) SecretSettings::decryptArray(
            \Modules\GlobalSetting\app\Models\Setting::pluck('value', 'key')->all()
        );

        $this->assertSame($plaintext, $cached->mail_password,
            'Cache layer must surface decrypted secret to MailSenderService etc.');
    }

    public function test_double_encrypting_is_idempotent(): void
    {
        $plaintext = 'idempotent';
        SecretSettings::setValue('aws_secret_key', $plaintext);
        $encrypted1 = DB::table('settings')->where('key', 'aws_secret_key')->value('value');

        SecretSettings::setValue('aws_secret_key', $encrypted1);
        $encrypted2 = DB::table('settings')->where('key', 'aws_secret_key')->value('value');

        $this->assertSame($encrypted1, $encrypted2,
            'Writing an already-encrypted value should not double-wrap it.');
        $this->assertSame($plaintext, SecretSettings::decrypt($encrypted2));
    }

    public function test_decrypt_tolerates_legacy_plaintext_secret_rows(): void
    {
        DB::table('settings')->where('key', 'recaptcha_secret_key')
            ->update(['value' => 'legacy-plaintext-no-prefix']);

        $value = SecretSettings::decrypt(
            DB::table('settings')->where('key', 'recaptcha_secret_key')->value('value')
        );

        $this->assertSame('legacy-plaintext-no-prefix', $value,
            'decrypt() must pass-through plaintext to support pre-rollout rows.');
    }

    public function test_decrypt_returns_null_on_corrupt_ciphertext(): void
    {
        DB::table('settings')->where('key', 'pusher_app_secret')
            ->update(['value' => 'enc:v1:not-valid-ciphertext']);

        $value = SecretSettings::decrypt(
            DB::table('settings')->where('key', 'pusher_app_secret')->value('value')
        );

        $this->assertNull($value, 'Corrupt ciphertext should return null, not throw.');
    }

    public function test_secret_keys_list_is_complete(): void
    {
        $expected = [
            'recaptcha_secret_key',
            'facebook_app_secret',
            'gmail_secret_id',
            'mail_password',
            'pusher_app_secret',
            'wasabi_secret_key',
            'aws_secret_key',
        ];

        $this->assertEqualsCanonicalizing($expected, SecretSettings::SECRET_KEYS,
            'SECRET_KEYS drift: a new sensitive key was added or removed without test update.');
    }

    public function test_iskey_helper_matches_secret_list(): void
    {
        foreach (SecretSettings::SECRET_KEYS as $k) {
            $this->assertTrue(SecretSettings::isSecretKey($k), "Expected $k to be a secret key.");
        }
        $this->assertFalse(SecretSettings::isSecretKey('app_name'));
        $this->assertFalse(SecretSettings::isSecretKey('timezone'));
    }

    public function test_isencrypted_detects_prefix_correctly(): void
    {
        $this->assertFalse(SecretSettings::isEncrypted(null));
        $this->assertFalse(SecretSettings::isEncrypted(''));
        $this->assertFalse(SecretSettings::isEncrypted('plaintext'));
        $this->assertTrue(SecretSettings::isEncrypted('enc:v1:anything'));
        $this->assertFalse(SecretSettings::isEncrypted('enc:v2:anything'),
            'Only v1 prefix is current — future v2 should be a different code path.');
    }
}
