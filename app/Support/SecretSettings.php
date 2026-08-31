<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted Setting values.
 *
 * Storage convention: encrypted strings carry an `enc:v1:` prefix so readers
 * can detect "is this already encrypted?" cheaply without try/catch on every
 * read. Non-prefixed values are treated as plaintext (legacy rows pre-rollout).
 *
 * Both writers and readers must use this class to round-trip cleanly:
 *   - SecretSettings::encrypt($plaintext)  → 'enc:v1:base64-ciphertext'
 *   - SecretSettings::decrypt($value)      → plaintext (tolerant of either form)
 *
 * The backfill migration walks every row whose key is in SECRET_KEYS, encrypts
 * non-prefixed values, and skips already-prefixed ones (idempotent).
 */
final class SecretSettings
{
    private const PREFIX = 'enc:v1:';

    /**
     * Setting keys whose values must be encrypted at rest.
     * Add to this list when introducing new sensitive settings.
     */
    public const SECRET_KEYS = [
        'recaptcha_secret_key',
        'facebook_app_secret',
        'gmail_secret_id',
        'mail_password',
        'pusher_app_secret',
        'wasabi_secret_key',
        'aws_secret_key',
    ];

    public static function isSecretKey(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }
        if (self::isEncrypted($plaintext)) {
            return $plaintext;
        }
        return self::PREFIX . Crypt::encryptString($plaintext);
    }

    public static function decrypt(?string $value): ?string
    {
        if (!self::isEncrypted($value)) {
            return $value;
        }
        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (\Throwable $e) {
            \Log::warning('SecretSettings: decrypt failed', ['err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Decrypt every secret-keyed entry in a key=>value array. Used by
     * AppServiceProvider when building the `setting` cache so consumers
     * like MailSenderService see plaintext.
     */
    public static function decryptArray(array $kv): array
    {
        foreach (self::SECRET_KEYS as $k) {
            if (array_key_exists($k, $kv)) {
                $kv[$k] = self::decrypt($kv[$k]);
            }
        }
        return $kv;
    }

    /**
     * Write a Setting value, transparently encrypting if its key is secret.
     * Replaces the explicit `Setting::where('key', X)->update(['value' => Y])`
     * pattern in writer controllers so each call site doesn't need to know
     * which keys are secret.
     */
    public static function setValue(string $key, ?string $value): void
    {
        $stored = self::isSecretKey($key) ? self::encrypt($value) : $value;
        \Modules\GlobalSetting\app\Models\Setting::where('key', $key)->update(['value' => $stored]);
    }

    /**
     * LMS removal phase 2 (2026-08-31) — removed the table-aware secret
     * registry (TABLE_SECRETS + secretKeysForTable/isSecretKeyForTable/
     * decryptForTable/setValueForTable). It held the encrypted-key lists for
     * payment_gateways / bkash_p_g_models / crypto_p_g — all three tables
     * belong to deleted payment-gateway modules, and nothing called any of
     * the four methods any more (verified: zero callers outside this file).
     * The `settings` table's own secret handling above (SECRET_KEYS,
     * decryptArray, setValue) is untouched — AppServiceProvider and
     * MailSenderService still depend on it.
     */
}
