<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Tests\TestCase;

/**
 * Regression tripwire for the user-side password-reset hardening, task C
 * (2026-05-12) — mirror of the admin-side C2 fix. The user API
 * (AuthenticatedController) had the same plaintext-token vulnerability:
 *
 *   - forgetPassword() stored Str::random(100) directly in
 *     users.forget_password_token.
 *   - resetPassword() compared the URL token against the stored value
 *     verbatim.
 *
 * A read-only DB leak (slow-query log, backup file, support read access)
 * would hand an attacker every in-flight reset link in the same way.
 * Now hashed before storage; URL token hashed before comparison.
 */
class UserApiAuthHardeningTest extends TestCase
{
    public function test_forgot_password_hashes_token_before_storage(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/API/AuthenticatedController.php')
        );

        $offset = strpos($src, 'public function forgetPassword');
        $this->assertNotFalse($offset, 'forgetPassword method missing');
        $body = substr($src, $offset, 3000);

        // sha256 hash of the raw token before save() — the contract.
        $this->assertMatchesRegularExpression(
            '/hash\(\s*[\'"]sha256[\'"]\s*,\s*\$rawToken\s*\)/',
            $body,
            'forgetPassword() must hash the raw token via sha256 before storing — plaintext storage exposes every in-flight link to DB-read leaks'
        );

        // Mustn't store raw Str::random() directly anymore.
        $this->assertDoesNotMatchRegularExpression(
            '/->forget_password_token\s*=\s*Str::random/',
            $body,
            "forgetPassword() must not assign Str::random() directly to forget_password_token — that's the pre-fix behavior that stored plaintext"
        );

        // Must use random_bytes(32) (256-bit entropy) for the raw token.
        $this->assertStringContainsString(
            'random_bytes(32)', $body,
            'forgetPassword() must use random_bytes(32) — 256-bit entropy, cryptographically secure source'
        );
    }

    public function test_reset_password_compares_hashed_tokens(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/API/AuthenticatedController.php')
        );

        $offset = strpos($src, 'public function resetPassword');
        $this->assertNotFalse($offset);
        $body = substr($src, $offset, 3000);

        // Hashes the request token before the WHERE clause.
        $this->assertMatchesRegularExpression(
            '/\$hashedToken\s*=\s*hash\(\s*[\'"]sha256[\'"]/',
            $body,
            'resetPassword() must hash the request token via sha256 before comparing — comparing raw to hash always misses post-fix'
        );

        // No direct ->where('forget_password_token', $request->forget_password_token) left.
        $this->assertDoesNotMatchRegularExpression(
            '/->where\([\'"]forget_password_token[\'"]\s*,\s*\$request->forget_password_token\s*\)/',
            $body,
            'resetPassword() must not compare the raw request value directly to forget_password_token (now hashed in DB) — comparison would silently always fail'
        );
    }

    public function test_user_model_hides_token_columns_from_serialization(): void
    {
        $user = new User();
        $hidden = (array) $user->getHidden();

        foreach (['forget_password_token', 'forget_password_token_expires_at', 'verification_token'] as $col) {
            $this->assertContains(
                $col, $hidden,
                "User model must hide '{$col}' from serialization — pre-C2 fix the raw token was here, post-fix the sha256 hash is. Either way an accidental toArray() leaks it to API responses."
            );
        }
    }
}
