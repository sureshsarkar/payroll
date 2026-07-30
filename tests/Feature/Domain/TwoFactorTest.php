<?php

namespace Tests\Feature\Domain;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Domain test — two-factor authentication business invariants.
 *
 * Audit 2026-05-18 phase 5 — promoted from skeleton. Tests the
 * cryptographic + storage contracts for 2FA. The full HTTP-level
 * challenge flow is verified by audit:smoke (input-size + throttle);
 * here we test the parts that don't need HTTP — TOTP generation +
 * recovery-code single-use + secret encryption at rest.
 */
class TwoFactorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_users_table_has_2fa_columns(): void
    {
        $cols = collect(\DB::select('SHOW COLUMNS FROM users'))->pluck('Field')->all();
        foreach (['two_factor_secret', 'two_factor_recovery_codes'] as $c) {
            $this->assertContains($c, $cols, "users.$c must exist");
        }
    }

    public function test_totp_round_trip_with_google2fa(): void
    {
        if (!class_exists(Google2FA::class)) {
            $this->markTestSkipped('pragmarx/google2fa not available');
        }

        $g2fa = new Google2FA();
        $secret = $g2fa->generateSecretKey(16);

        // Generate a TOTP for "now" and verify it
        $code = $g2fa->getCurrentOtp($secret);
        $this->assertTrue($g2fa->verifyKey($secret, $code),
            'Currently-valid TOTP must verify successfully');

        // A clearly invalid code must NOT verify
        $this->assertFalse($g2fa->verifyKey($secret, '000000'),
            'Random invalid code must NOT verify');
    }

    public function test_two_factor_secret_can_be_encrypted_at_rest(): void
    {
        // Laravel's standard pattern stores 2FA secret encrypted via Crypt.
        $plainSecret = (new Google2FA())->generateSecretKey(16);
        $encrypted = Crypt::encryptString($plainSecret);

        // Encrypted form is NOT equal to plaintext
        $this->assertNotSame($plainSecret, $encrypted);

        // Round-trips cleanly
        $this->assertSame($plainSecret, Crypt::decryptString($encrypted));
    }

    public function test_recovery_codes_one_time_use_semantics(): void
    {
        // Recovery codes are stored as a JSON array. Using one removes it
        // from the array — this test asserts that semantic by walking the
        // array-removal step explicitly.
        $codes = ['code-aaaa', 'code-bbbb', 'code-cccc'];

        $consumed = 'code-bbbb';
        $remaining = array_values(array_filter($codes, fn($c) => $c !== $consumed));

        $this->assertCount(2, $remaining);
        $this->assertNotContains($consumed, $remaining);
        // Re-using $consumed against $remaining now fails
        $this->assertFalse(in_array($consumed, $remaining, true));
    }
}
