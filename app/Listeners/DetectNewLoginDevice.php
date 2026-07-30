<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\NewLoginAlertToUser;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22 — emails a security alert the first time a user signs in from a
 * NEW device/location. The first device per user is recorded silently as the
 * baseline (no alert), so existing users aren't spammed on their next login.
 * Only front-end users (web guard) are tracked; admins are skipped.
 */
class DetectNewLoginDevice
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }
        if (! ($event->user instanceof User)) {
            return;
        }

        $this->record(
            $event->user,
            (string) request()->ip(),
            (string) request()->userAgent()
        );
    }

    /**
     * Core logic, separated for testability. Returns true if an alert was sent.
     */
    public function record(User $user, ?string $ip, ?string $userAgent): bool
    {
        try {
            if (! Schema::hasTable('user_login_devices')) {
                return false;
            }

            $fingerprint = sha1(((string) $ip) . '|' . substr((string) $userAgent, 0, 255));

            $existing = DB::table('user_login_devices')
                ->where('user_id', $user->id)
                ->where('fingerprint', $fingerprint)
                ->first();

            if ($existing) {
                DB::table('user_login_devices')->where('id', $existing->id)
                    ->update(['last_seen_at' => now(), 'updated_at' => now()]);
                return false;
            }

            // New fingerprint. Alert only if the user already had a known device
            // (so the very first sign-in just establishes the baseline).
            $hadDevices = DB::table('user_login_devices')->where('user_id', $user->id)->exists();

            DB::table('user_login_devices')->insert([
                'user_id'      => $user->id,
                'fingerprint'  => $fingerprint,
                'ip'           => $ip ? substr($ip, 0, 45) : null,
                'user_agent'   => $userAgent,
                'last_seen_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            if ($hadDevices) {
                $user->notify(new NewLoginAlertToUser($ip, $userAgent));
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('DetectNewLoginDevice failed: ' . $e->getMessage());
        }

        return false;
    }
}
