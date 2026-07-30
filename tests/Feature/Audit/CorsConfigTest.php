<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Verifies the CORS + CSRF-exempt configuration stays within the threat
 * model the audit accepted:
 *
 *  - Mobile API serves arbitrary origins, so allowed_origins=['*'] is OK
 *  - supports_credentials=false means browsers won't include cookies
 *    cross-origin, so sessions can't be hijacked via XHR
 *
 * The dangerous combination — allowed_origins=['*'] + supports_credentials
 * =true — is rejected by modern browsers but would still be a code-level
 * mistake worth catching at CI time. Also lock down the CSRF $except list
 * so a maintenance change doesn't accidentally expose a sensitive POST.
 */
class CorsConfigTest extends TestCase
{
    public function test_cors_does_not_combine_wildcard_origin_with_credentials(): void
    {
        $origins = (array) config('cors.allowed_origins');
        $creds   = (bool)  config('cors.supports_credentials');

        if (in_array('*', $origins, true) && $creds) {
            $this->fail(
                "config/cors.php has allowed_origins=['*'] AND supports_credentials=true — " .
                "this combo is the canonical CORS-CSRF bypass. Either restrict origins to a " .
                "specific allowlist, or set supports_credentials=false."
            );
        }
        $this->assertTrue(true, 'CORS combo is safe');
    }

    public function test_cors_paths_scope_is_documented(): void
    {
        // CORS only applies to the explicit path list, not the whole app.
        // /api/* is the mobile API; sanctum/csrf-cookie is the SPA primer.
        // Anything else (e.g. /admin/*, /student/*) is same-origin only.
        $paths = (array) config('cors.paths');
        sort($paths);
        $this->assertSame(['api/*', 'sanctum/csrf-cookie'], $paths,
            'CORS scope drift: cors.paths must remain api/* + sanctum/csrf-cookie. ' .
            'Adding broader paths (e.g. admin/* or *) widens the cross-origin attack ' .
            'surface and may need explicit per-route review.');
    }

    public function test_csrf_exempt_list_is_locked_down(): void
    {
        // Reflect into VerifyCsrfToken::$except — only known-safe endpoints.
        $mw = new \App\Http\Middleware\VerifyCsrfToken(app(), app('encrypter'));
        $reflection = new \ReflectionClass($mw);
        $prop = $reflection->getProperty('except');
        $prop->setAccessible(true);
        $except = $prop->getValue($mw);
        sort($except);

        $expected = [
            'tinymce-delete-image',  // auth-gated, deletes only the user's own images
            'tinymce-upload-image',  // auth-gated, image|max:2048 validation
            'webhooks/*',            // gateway-server POSTs (verified per-handler)
        ];
        sort($expected);

        $this->assertSame($expected, $except,
            'CSRF exempt list drift. Adding new entries needs a per-route security review — ' .
            'every CSRF-exempt POST/DELETE/PUT can be triggered from any origin via a logged-in ' .
            'victim\'s browser, so the endpoint must either carry its own auth (signature, ' .
            'capability check) or only do non-privileged work.');
    }
}
