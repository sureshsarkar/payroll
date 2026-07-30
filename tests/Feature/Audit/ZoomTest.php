<?php

namespace Tests\Feature\Audit;

use App\Models\ZoomCredential;
use App\Services\ZoomSignatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Verifies the Zoom audit's hardening guarantees:
 *
 * P0-1  zoom.sdk-signature route exists, is POST, requires auth
 * P0-1  zoom.blade.php no longer renders client_secret into <script>
 * P0-2  zoom_credentials.client_secret is encrypted at rest
 * P0-3+P0-4  controller decides host vs. attendee server-side; an enrollment
 *      check exists for non-host calls
 * P1   instructor and student blade views are consolidated (one shared body)
 * P1   anti-devtools keydown handler removed from launcher view
 * P2   zoom.live.headers middleware is wired onto both live-class routes
 * Service: signature is a valid HS256 JWT carrying the Zoom-required claims
 */
class ZoomTest extends TestCase
{
    use DatabaseTransactions;

    public function test_signature_route_is_post_and_authed(): void
    {
        $routes = RouteFacade::getRoutes()->getRoutesByName();
        $this->assertArrayHasKey('zoom.sdk-signature', $routes);
        /** @var Route $route */
        $route = $routes['zoom.sdk-signature'];
        $this->assertContains('POST', $route->methods(), 'zoom.sdk-signature must be POST');

        $mw = $route->gatherMiddleware();
        $this->assertContains('auth', $mw, 'zoom.sdk-signature must require auth');
        $this->assertContains('verified', $mw, 'zoom.sdk-signature must require verified');
        $this->assertNotEmpty(
            array_filter($mw, fn ($m) => str_starts_with((string) $m, 'throttle')),
            'zoom.sdk-signature must be rate-limited'
        );
    }

    public function test_signature_service_produces_valid_hs256_jwt(): void
    {
        $svc = new ZoomSignatureService();
        $jwt = $svc->generate('SDKKEY-test', 'super-secret-32-bytes-of-random!', '12345678901', 1, 7200);

        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts, 'JWT must have 3 segments');
        [$h, $p, $s] = $parts;

        $pad = fn (string $x) => str_pad($x, strlen($x) + (4 - strlen($x) % 4) % 4, '=', STR_PAD_RIGHT);
        $dec = fn (string $x) => base64_decode(strtr($pad($x), '-_', '+/'));

        $header  = json_decode($dec($h), true);
        $payload = json_decode($dec($p), true);

        $this->assertSame('HS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);

        // Zoom requires both sdkKey and the legacy `appKey` alias
        $this->assertSame('SDKKEY-test', $payload['sdkKey']);
        $this->assertSame('SDKKEY-test', $payload['appKey']);
        $this->assertSame('12345678901', $payload['mn']);
        $this->assertSame(1, $payload['role']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('tokenExp', $payload);
        $this->assertSame($payload['exp'], $payload['tokenExp']);

        // HMAC verifies
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", 'super-secret-32-bytes-of-random!', true)), '+/', '-_'), '=');
        $this->assertSame($expected, $s, 'HMAC signature must match');
    }

    public function test_zoom_credential_model_uses_encrypted_cast(): void
    {
        $cred = new ZoomCredential();
        $casts = $cred->getCasts();

        // Sensitive S2S OAuth + Meeting SDK fields must be encrypted at rest.
        // (zoom_refresh_token was dropped on 2026-05-08 — S2S has no refresh token.
        // sdk_secret was added 2026-05-08 — Meeting SDK app credentials.)
        foreach (['account_id', 'client_secret', 'sdk_secret', 'zoom_access_token'] as $col) {
            $this->assertSame('encrypted', $casts[$col] ?? null,
                "ZoomCredential::\$casts['$col'] must be 'encrypted' (audit P0-2)");
        }
    }

    public function test_existing_zoom_credentials_rows_are_encrypted_in_db(): void
    {
        // Audit 2026-05-19 — previously skipped on empty CI DB. Now seed
        // a real fixture (with cleanup) so the encryption invariant is
        // tested regardless of seed state. This is critical because
        // ZoomCredential::$casts uses 'encrypted', which silently breaks
        // if the column type changes or the cast is removed.
        $row = DB::table('zoom_credentials')->whereNotNull('client_secret')->first();
        $seededId = null;

        if (!$row) {
            // zoom_credentials has FK on users.id, so we need a real user.
            $coach = \App\Models\User::factory()->create(['role' => 'instructor']);
            // Seed a fixture row via the model so the Eloquent encrypted
            // cast is applied (raw INSERT would store plaintext).
            $created = \App\Models\ZoomCredential::create([
                'instructor_id' => $coach->id,
                'client_id'     => 'zoom_test_client',
                'client_secret' => 'zoom_test_secret_'.uniqid(),
                'account_id'    => 'zoom_test_account',
            ]);
            $seededId = $created->id;
            $row = DB::table('zoom_credentials')->where('id', $seededId)->first();
        }

        try {
            // Raw DB value must NOT be readable as plaintext (i.e. the migration
            // ran and wrapped existing rows). Attempting Crypt::decryptString must succeed.
            $raw = $row->client_secret;
            $this->assertGreaterThan(100, strlen($raw),
                'encrypted client_secret should be a long base64 envelope');

            $plain = null;
            try {
                $plain = Crypt::decryptString($raw);
            } catch (\Throwable $e) {
                $this->fail('client_secret in DB is not Crypt-encrypted (encryption backfill migration must run first): ' . $e->getMessage());
            }
            $this->assertNotEmpty($plain);
        } finally {
            // Only delete what we ourselves seeded.
            if ($seededId !== null) {
                DB::table('zoom_credentials')->where('id', $seededId)->delete();
            }
        }
    }

    public function test_zoom_blade_view_does_not_render_client_secret(): void
    {
        $student   = file_get_contents(resource_path('views/frontend/student-dashboard/live/zoom.blade.php'));
        $this->assertStringNotContainsString('client_secret', $student,
            'student zoom.blade.php must NOT reference client_secret (was the P0 leak)');
        $this->assertStringNotContainsString('sdkSecret', $student,
            'student zoom.blade.php must NOT define sdkSecret in JS');
        $this->assertStringNotContainsString('generateSDKSignature', $student,
            'student zoom.blade.php must NOT call ZoomMtg.generateSDKSignature (signature must come from server)');
    }

    public function test_anti_devtools_keydown_handler_is_removed(): void
    {
        $student = file_get_contents(resource_path('views/frontend/student-dashboard/live/zoom.blade.php'));
        $this->assertStringNotContainsString('keyCode === 123', $student,
            'F12-blocking keydown handler must be removed (security theater + frustrates legit users)');
        $this->assertStringNotContainsString("addEventListener('contextmenu'", $student,
            'context-menu blocker must be removed too');
    }

    public function test_instructor_zoom_view_is_consolidated_with_student_view(): void
    {
        // Either the file is a thin include of the student view, OR it's a
        // 1-line @include directive — either way it must NOT independently
        // duplicate the body.
        $instructor = file_get_contents(resource_path('views/frontend/instructor-dashboard/live-classes/zoom.blade.php'));
        $this->assertStringContainsString("@include('frontend.student-dashboard.live.zoom')", $instructor,
            'instructor zoom.blade.php must @include the student view to prevent drift (audit P1)');
        $this->assertStringNotContainsString('client_secret', $instructor);
        $this->assertStringNotContainsString('sdkSecret', $instructor);
    }

    public function test_live_class_routes_have_zoom_headers_middleware(): void
    {
        $routes = RouteFacade::getRoutes()->getRoutesByName();
        foreach (['student.learning.live', 'instructor.live-class'] as $name) {
            $this->assertArrayHasKey($name, $routes, "route '$name' must exist");
            $mw = $routes[$name]->gatherMiddleware();
            $this->assertContains('zoom.live.headers', $mw,
                "route '$name' must apply zoom.live.headers middleware (COOP/COEP/CSP — P2)");
        }
    }

    public function test_zoom_live_headers_middleware_emits_isolation_headers(): void
    {
        $mw = new \App\Http\Middleware\ZoomLiveClassHeaders();
        $req = \Illuminate\Http\Request::create('/student/learning/x/1', 'GET');
        $response = $mw->handle($req, fn () => new \Illuminate\Http\Response('ok'));

        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
        $this->assertSame('credentialless', $response->headers->get('Cross-Origin-Embedder-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('source.zoom.us', $response->headers->get('Content-Security-Policy'));
    }

    /**
     * 2026-05-29 Doc-C-ZoomJoin regression pin.
     *
     * Coaches reported a console error
     * "navigator.mediaDevices.getDisplayMedia is undefined" when they
     * clicked Share Screen during a live class. The previous
     * `display-capture=(self)` policy refused to delegate to Zoom's
     * blob:/sub-frame contexts, leaving getDisplayMedia genuinely
     * undefined instead of even prompting the user.
     *
     * This test pins the new delegation contract so a future refactor
     * that re-tightens the policy fails loudly with the same root cause
     * in the assertion message.
     */
    public function test_permissions_policy_delegates_display_capture_to_zoom(): void
    {
        $mw = new \App\Http\Middleware\ZoomLiveClassHeaders();
        $req = \Illuminate\Http\Request::create('/student/learning/x/1', 'GET');
        $response = $mw->handle($req, fn () => new \Illuminate\Http\Response('ok'));

        $pp = $response->headers->get('Permissions-Policy');
        $this->assertNotEmpty($pp, 'Permissions-Policy header must be present on Zoom join pages');

        // Each of these directives MUST be delegated to (at minimum)
        // self + the Zoom CDN origins, otherwise the corresponding
        // navigator.mediaDevices APIs become `undefined` inside the
        // Zoom Component View's sub-contexts.
        foreach (['camera', 'microphone', 'display-capture', 'autoplay'] as $directive) {
            $this->assertStringContainsString(
                "$directive=(self ",
                $pp,
                "Permissions-Policy must delegate `$directive` to self + Zoom origins "
                . '(prevents Doc-C-ZoomJoin "getDisplayMedia undefined")'
            );
            $this->assertStringContainsString(
                'https://zoom.us',
                $pp,
                "Permissions-Policy `$directive` allowlist must include https://zoom.us"
            );
            $this->assertStringContainsString(
                'https://source.zoom.us',
                $pp,
                "Permissions-Policy `$directive` allowlist must include https://source.zoom.us "
                . '(the SDK static bundle origin)'
            );
        }
    }

    public function test_signature_controller_enforces_enrollment_or_ownership(): void
    {
        // Static analysis: the controller source must reference both the
        // Enrollment model and the instructor_id ownership check. We don't
        // run a full HTTP call here because our existing pattern (see
        // IdorTest) is route-registration-based; this guards against the
        // logic being deleted in a refactor.
        $src = file_get_contents(app_path('Http/Controllers/Frontend/ZoomSignatureController.php'));
        $this->assertStringContainsString('Enrollment::', $src,
            'signature controller must check Enrollment for non-host callers (P0-4)');
        $this->assertStringContainsString('instructor_id', $src,
            'signature controller must compare against course.instructor_id for host role (P0-3)');
        $this->assertStringContainsString("'has_access', 1", $src,
            'enrollment check must require has_access=1');
    }

    public function test_signature_response_includes_zak_for_host_join(): void
    {
        // Zoom's 2026-03-02 OBF/ZAK rule rejects cross-account host joins
        // without a ZAK token. The signature controller fetches the host's
        // ZAK via S2S OAuth and the launcher passes it into client.join().
        // These two static guards keep the wiring intact through refactors.
        $controllerSrc = file_get_contents(app_path('Http/Controllers/Frontend/ZoomSignatureController.php'));
        $this->assertStringContainsString("/users/me/token", $controllerSrc,
            'controller must call /users/me/token to fetch ZAK for host joins');
        $this->assertStringContainsString("'zak'", $controllerSrc,
            'controller response must carry a `zak` field (null when fetch fails or attendee role)');

        $bladeSrc = file_get_contents(resource_path('views/frontend/student-dashboard/live/zoom.blade.php'));
        $this->assertStringContainsString('cfg.zak', $bladeSrc,
            'launcher must read cfg.zak and forward it to client.join()');
        $this->assertStringContainsString('joinPayload.zak', $bladeSrc,
            'launcher must conditionally attach zak to the join payload (omit-when-falsy)');
    }
}
