<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Verifies the debug / error-handling info-disclosure guarantees.
 *
 * Pre-audit:
 *  - .env.example defaulted to APP_ENV=local + APP_DEBUG=true. A
 *    `cp .env.example .env` on a fresh prod deploy that forgot to flip
 *    these would expose Laravel Whoops error pages — full stack traces
 *    with file paths, the failing SQL query (which often contains
 *    credentials in connection strings), env var values, and class names.
 *  - Handler::$dontFlash only included password fields. After an audit
 *    that added 2FA + bearer-token-in-form-input flows, a server-side
 *    validation failure could echo those values back into the re-rendered
 *    form HTML.
 *
 * Audit changes:
 *  - .env.example now defaults to APP_ENV=production + APP_DEBUG=false
 *    + LOG_LEVEL=warning. Local devs flip them manually.
 *  - $dontFlash extended to cover 2FA codes, recovery codes, password
 *    reset tokens, and bearer tokens.
 *
 * Telescope is properly gated to admin-only in prod (existing
 * TelescopeServiceProvider.php gate). Debugbar is in require-dev only.
 */
class DebugInfoDisclosureTest extends TestCase
{
    public function test_env_example_defaults_to_production_safe_values(): void
    {
        $body = file_get_contents(base_path('.env.example'));
        $this->assertNotFalse($body);

        $this->assertMatchesRegularExpression(
            '/^APP_ENV=production\b/m',
            $body,
            ".env.example must default APP_ENV=production — if a deploy script does " .
            "`cp .env.example .env` and forgets to flip the env, prod would render Laravel " .
            "error pages with full stack traces."
        );
        $this->assertMatchesRegularExpression(
            '/^APP_DEBUG=false\b/m',
            $body,
            ".env.example must default APP_DEBUG=false — debug=true exposes Whoops error " .
            "pages with DB query strings (often containing credentials)."
        );
        $this->assertMatchesRegularExpression(
            '/^LOG_LEVEL=(warning|error|critical|alert|emergency)\b/m',
            $body,
            ".env.example LOG_LEVEL must default to warning or stricter — debug fills " .
            "disk fast under load and may persist sensitive request bodies."
        );
    }

    public function test_handler_dont_flash_covers_audit_sensitive_fields(): void
    {
        $body = file_get_contents(app_path('Exceptions/Handler.php'));

        // Original defaults — must remain.
        foreach (['password', 'current_password', 'password_confirmation'] as $field) {
            $this->assertStringContainsString("'$field'", $body, "\$dontFlash missing $field");
        }
        // Audit additions for 2FA + token fields.
        foreach (['two_factor_secret', 'recovery_code', 'code', 'forget_password_token', 'bearer_token'] as $field) {
            $this->assertStringContainsString(
                "'$field'",
                $body,
                "Handler::\$dontFlash must include '$field' so a server-side validation re-render doesn't echo it back into the form HTML"
            );
        }
    }

    public function test_telescope_is_gated_to_admin_in_production(): void
    {
        $body = file_get_contents(app_path('Providers/TelescopeServiceProvider.php'));

        // Sanity: we still reach the admin-guard check in non-local envs.
        $this->assertStringContainsString("environment('local')", $body);
        $this->assertStringContainsString("auth('admin')->user()", $body,
            'TelescopeServiceProvider::gate must check the admin guard for non-local access');
    }

    public function test_debugbar_is_dev_only(): void
    {
        // Parse composer.json to confirm laravel-debugbar is in require-dev,
        // not require. Production composer install --no-dev won't pull it.
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $this->assertArrayNotHasKey('barryvdh/laravel-debugbar', $composer['require'] ?? [],
            'laravel-debugbar must NOT be in composer.json require — its data-collector exposes session content in HTML responses');
        $this->assertArrayHasKey('barryvdh/laravel-debugbar', $composer['require-dev'] ?? [],
            'laravel-debugbar should still be available as a dev dependency');
    }
}
