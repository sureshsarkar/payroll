<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — Server-to-Server OAuth migration on 2026-05-08.
 *
 * Replaced the legacy User-OAuth flow (authorization_code + refresh_token)
 * because instructor 1079's refresh token died on 2026-04-07 and stayed
 * dead for 30 days, silently breaking every live class. S2S has no
 * refresh token, no redirect-based authorization step, and no decay —
 * tokens are minted on demand from account_id + client_id + secret.
 *
 * Fails the build if any of the load-bearing parts of the migration
 * regresses:
 *  - the schema columns (account_id added, zoom_refresh_token dropped)
 *  - the User-OAuth code paths (must stay deleted)
 *  - the S2S grant_type in the API service + health check
 *  - the zoom-setting form fields
 */
class ZoomS2SOAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_account_id_column_exists(): void
    {
        $cols = Schema::getColumnListing('zoom_credentials');
        $this->assertContains(
            'account_id',
            $cols,
            'zoom_credentials.account_id missing — Server-to-Server OAuth needs it'
        );
    }

    public function test_refresh_token_column_is_gone(): void
    {
        $cols = Schema::getColumnListing('zoom_credentials');
        $this->assertNotContains(
            'zoom_refresh_token',
            $cols,
            'zoom_credentials.zoom_refresh_token must be dropped — S2S OAuth has no refresh token'
        );
    }

    public function test_legacy_oauth_files_are_deleted(): void
    {
        $forbidden = [
            'app/Traits/ZoomMeetingTrait.php',
            'app/Http/Controllers/Frontend/ZoomController.php',
        ];
        foreach ($forbidden as $rel) {
            $this->assertFileDoesNotExist(
                base_path($rel),
                "{$rel} must stay deleted — User-OAuth flow obsolete after S2S migration"
            );
        }
    }

    public function test_legacy_oauth_routes_are_gone(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        foreach (['instructor.zoom-connect', 'instructor.zoom-callback'] as $name) {
            $this->assertNotContains(
                $name,
                $names,
                "Route '{$name}' must not exist — User-OAuth callback flow was removed"
            );
        }
    }

    public function test_zoom_api_service_uses_account_credentials_grant(): void
    {
        $src = (string) file_get_contents(
            app_path('Services/ZoomApiService.php')
        );

        $this->assertStringContainsString(
            "'grant_type' => 'account_credentials'",
            $src,
            'ZoomApiService must use grant_type=account_credentials (S2S OAuth)'
        );
        $this->assertStringNotContainsString(
            "'grant_type'    => 'refresh_token'",
            $src,
            'ZoomApiService must not use grant_type=refresh_token anymore'
        );
        $this->assertStringNotContainsString(
            'authorization_code',
            $src,
            'ZoomApiService must not use User-OAuth authorization_code flow'
        );
    }

    public function test_health_check_uses_account_credentials_grant(): void
    {
        $src = (string) file_get_contents(
            app_path('Console/Commands/ZoomHealthCheck.php')
        );

        $this->assertStringContainsString(
            "'grant_type' => 'account_credentials'",
            $src,
            'ZoomHealthCheck must probe via S2S account_credentials grant'
        );
        // Refresh-token grant must not appear as live code. Historical context
        // in the docstring ("the User-OAuth refresh_token died") is fine —
        // we narrow the assertion to the actual API call shape.
        $this->assertStringNotContainsString(
            "'grant_type' => 'refresh_token'",
            $src,
            'ZoomHealthCheck must not POST grant_type=refresh_token (S2S has no refresh token)'
        );
    }

    public function test_live_class_controller_uses_zoom_api_service(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );

        $this->assertStringNotContainsString(
            'use ZoomMeetingTrait',
            $src,
            'LiveClassController must not use ZoomMeetingTrait — that trait was deleted'
        );
        $this->assertStringNotContainsString(
            '$this->refreshZoomToken',
            $src,
            'LiveClassController must not call refreshZoomToken — use ZoomApiService instead'
        );
        $this->assertStringContainsString(
            'ZoomApiService',
            $src,
            'LiveClassController must use ZoomApiService for token minting'
        );
    }

    public function test_zoom_setting_form_collects_account_id(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/zoom/index.blade.php')
        );

        $this->assertStringContainsString(
            'name="account_id"',
            $view,
            'Zoom-setting form must collect account_id (S2S requirement)'
        );
        $this->assertStringNotContainsString(
            'zoom-connect',
            $view,
            'Zoom-setting view must not link to the deleted OAuth connect route'
        );
        $this->assertStringNotContainsString(
            'Create Access Token',
            $view,
            'Zoom-setting view must not show the "Create Access Token" button (OAuth flow)'
        );
    }

    public function test_zoom_credential_controller_validates_account_id(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/InstructorLiveCredentialController.php')
        );

        $this->assertStringContainsString(
            "'account_id'",
            $src,
            'InstructorLiveCredentialController::update must validate account_id'
        );
    }

    public function test_account_id_is_encrypted_at_rest(): void
    {
        $cred = new \App\Models\ZoomCredential();
        $casts = $cred->getCasts();
        $this->assertSame(
            'encrypted',
            $casts['account_id'] ?? null,
            'ZoomCredential::$casts[account_id] must be encrypted'
        );
    }

    public function test_signature_controller_uses_sdk_credentials_not_oauth(): void
    {
        // Meeting SDK signature MUST be signed with sdk_key + sdk_secret
        // from a Meeting SDK app — using the S2S OAuth client_id/secret
        // produces a JWT Zoom rejects with "Signature is invalid".
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/ZoomSignatureController.php')
        );

        $this->assertStringContainsString(
            '$cred->sdk_key',
            $src,
            'ZoomSignatureController must read sdk_key (Meeting SDK app), not client_id (S2S OAuth)'
        );
        $this->assertStringContainsString(
            '$cred->sdk_secret',
            $src,
            'ZoomSignatureController must sign with sdk_secret, not client_secret'
        );
        $this->assertStringNotContainsString(
            'sdkKey:        $cred->client_id',
            $src,
            'ZoomSignatureController must not pass client_id as sdkKey — that signs an invalid JWT'
        );
    }

    public function test_zoom_credential_form_collects_sdk_key_and_secret(): void
    {
        $view = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/zoom/index.blade.php')
        );
        $this->assertStringContainsString(
            'name="sdk_key"',
            $view,
            'Zoom-setting form must collect sdk_key (Meeting SDK app)'
        );
        $this->assertStringContainsString(
            'name="sdk_secret"',
            $view,
            'Zoom-setting form must collect sdk_secret'
        );
    }
}
