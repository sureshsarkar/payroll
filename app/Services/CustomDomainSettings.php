<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Modules\GlobalSetting\app\Models\Setting;

/**
 * Typed accessor + writer for the superadmin-controlled custom-domain
 * feature settings. Values live as key/value rows in the global
 * `settings` table (so they ride the cached `setting` object) with
 * sensible config/env fallbacks.
 *
 *   custom_domain_enabled            '1' | '0'
 *   custom_domain_server_ip          public IP(s) the A record targets
 *   custom_domain_requires_approval  '1' | '0'  (strict approval mode)
 *   custom_domain_max_per_coach      integer quota
 */
class CustomDomainSettings
{
    public const KEY_ENABLED   = 'custom_domain_enabled';
    public const KEY_SERVER_IP = 'custom_domain_server_ip';
    public const KEY_APPROVAL  = 'custom_domain_requires_approval';
    public const KEY_MAX       = 'custom_domain_max_per_coach';

    /** Is the custom-domain feature switched on by the superadmin? */
    public function enabled(): bool
    {
        return $this->bool(self::KEY_ENABLED, false);
    }

    /** Strict mode: a verified domain waits for admin approval before going active. */
    public function requiresApproval(): bool
    {
        return $this->bool(self::KEY_APPROVAL, false);
    }

    /** Per-coach custom-domain quota. */
    public function maxPerCoach(): int
    {
        $v = (int) $this->raw(self::KEY_MAX, '1');
        return $v > 0 ? $v : 1;
    }

    /**
     * The single public IP shown to coaches in the A-record instruction.
     * Setting → config('app.platform_ip') → ''.
     */
    public function serverIp(): string
    {
        $ip = trim((string) $this->raw(self::KEY_SERVER_IP, ''));
        if ($ip === '') {
            $ip = trim((string) config('app.platform_ip', ''));
        }
        return $ip !== '' ? trim(explode(',', $ip)[0]) : '';
    }

    /** All configured platform IPs (comma-separated), for verification matching. */
    public function serverIps(): array
    {
        $raw = trim((string) $this->raw(self::KEY_SERVER_IP, ''));
        if ($raw === '') {
            $raw = trim((string) config('app.platform_ip', ''));
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /** Persist a batch of settings and bust the global cache. */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        Cache::forget('setting');
    }

    /* ───────── internals ───────── */

    protected function bool(string $key, bool $default): bool
    {
        $v = $this->raw($key, $default ? '1' : '0');
        return in_array((string) $v, ['1', 'true', 'on', 'yes'], true);
    }

    protected function raw(string $key, $default)
    {
        // Prefer the cached global `setting` object (already loaded per request).
        $setting = Cache::get('setting');
        if (is_object($setting) && isset($setting->{$key}) && $setting->{$key} !== null && $setting->{$key} !== '') {
            return $setting->{$key};
        }
        // Fallback: direct DB read (covers missing-from-cache / test contexts).
        $row = Setting::where('key', $key)->value('value');
        return ($row !== null && $row !== '') ? $row : $default;
    }
}
