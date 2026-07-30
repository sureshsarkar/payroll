<?php

namespace Tests\Feature\Audit;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire for audit findings C1 + C2 (2026-05-12).
 *
 * C1 — the shadow `cache/clear-cache` closure inside the admin auth+2fa
 *      group ran cache:clear / route:clear / config:clear / view:clear
 *      with NO permission check, via a mutating GET. The legitimate
 *      cache-clear flow lives in the GlobalSetting module
 *      (admin.cache-clear / admin.cache-clear-confirm) and is
 *      permission-gated + POST. The shadow closure was removed; this
 *      tripwire stops it from creeping back.
 *
 * C2 — admins.forget_password_token was stored in plaintext, no expiry.
 *      A read-only DB leak would hand an attacker working reset links
 *      that never auto-expired. The token is now hashed (sha256) before
 *      storage with a 1-hour TTL. This tripwire locks down all three
 *      pieces of the contract: column exists, raw token never persists,
 *      verification hashes before comparing.
 */
class AdminAuthHardeningTest extends TestCase
{
    use DatabaseTransactions;

    /* ───────────────────────────────────────────────────────── C1 ── */

    public function test_shadow_cache_clear_closure_is_gone_from_admin_routes(): void
    {
        $src = (string) file_get_contents(base_path('routes/admin.php'));

        // The original closure literal — any subset reintroduces the issue.
        $this->assertDoesNotMatchRegularExpression(
            "/Route::get\(\s*'cache\/clear-cache'\s*,\s*function/",
            $src,
            "Shadow Route::get('cache/clear-cache', function ...) closure is back — that endpoint clears cache with no permission check via a mutating GET"
        );

        $this->assertStringNotContainsString(
            "Artisan::call('cache:clear')",
            $src,
            'routes/admin.php must not call Artisan::call() inline — mutating ops belong in a permission-gated controller'
        );
    }

    /* ───────────────────────────────────────────────────────── C2 ── */

    public function test_admin_token_expiry_column_exists(): void
    {
        $this->assertTrue(
            Schema::hasColumn('admins', 'forget_password_token_expires_at'),
            'admins.forget_password_token_expires_at missing — tokens cannot auto-expire, run the C2 migration'
        );
    }

    public function test_reset_link_controller_hashes_token_before_storage(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Admin/Auth/PasswordResetLinkController.php')
        );

        // sha256 hash of the raw token before save() — the contract.
        $this->assertMatchesRegularExpression(
            '/hash\(\s*[\'"]sha256[\'"]\s*,\s*\$rawToken\s*\)/',
            $src,
            "PasswordResetLinkController must hash the raw token via sha256 before storing — plaintext storage exposes every in-flight link to DB-read leaks"
        );

        // Mustn't store the raw token directly — that's what we're fixing.
        $this->assertDoesNotMatchRegularExpression(
            '/->forget_password_token\s*=\s*Str::random/',
            $src,
            "PasswordResetLinkController must not assign Str::random() directly to forget_password_token — that's the pre-C2 behavior that stored plaintext"
        );

        // 1-hour TTL set at write time.
        $this->assertMatchesRegularExpression(
            '/forget_password_token_expires_at\s*=\s*now\(\)->addHour\(\)/',
            $src,
            'PasswordResetLinkController must set a 1-hour expiry on every issued token — without it, abandoned tokens stay valid forever'
        );

        // Email-enumeration defense: identical response regardless of
        // whether the email exists. (Tightening of the original flow.)
        $this->assertStringContainsString(
            'If that email is registered',
            $src,
            'PasswordResetLinkController must return an identical message whether the email exists or not — pre-C2 behavior leaked which emails were registered admins'
        );
    }

    public function test_password_reset_controllers_compare_hashed_tokens(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Admin/Auth/NewPasswordController.php')
        );

        // Hashes the URL-supplied token before the WHERE clause.
        $hashCount = preg_match_all('/hash\(\s*[\'"]sha256[\'"]\s*,\s*\(string\)\s*\$token\s*\)/', $src);
        $this->assertGreaterThanOrEqual(
            2, $hashCount,
            "NewPasswordController must hash the URL token via sha256 in BOTH the page-render and store methods — comparing raw to hash would always miss"
        );

        // No direct ->where('forget_password_token', $token) left — that's
        // the unhashed comparison this fix replaces.
        $this->assertDoesNotMatchRegularExpression(
            '/->where\([\'"]forget_password_token[\'"]\s*,\s*\$token\s*\)/',
            $src,
            'NewPasswordController must not compare $token (raw URL value) directly to forget_password_token (hashed in DB) — that comparison would always fail after the C2 fix and silently break the flow'
        );

        // Expiry check must exist somewhere in the controller.
        $this->assertStringContainsString(
            'isTokenExpired',
            $src,
            'NewPasswordController must check token expiry — pre-C2 a stale token would still pass verification'
        );
    }

    public function test_admin_model_hides_token_columns_from_serialization(): void
    {
        // forget_password_token is sensitive — it shouldn't leak through
        // ->toArray() / ->toJson() / api responses. The Admin model
        // declares $hidden; verify the column is in there.
        $admin = new Admin();
        $hidden = (array) $admin->getHidden();

        $this->assertContains(
            'forget_password_token', $hidden,
            'Admin model must hide forget_password_token from serialization — otherwise an inadvertent toArray() leaks the hash to API consumers (and pre-C2, the raw token)'
        );
    }
}
