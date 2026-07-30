<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * 2026-06-02 — live-class secure-context guard.
 *
 * Zoom's Web SDK (and all WebRTC media APIs) need navigator.mediaDevices, which
 * browsers expose ONLY on a secure context (HTTPS / localhost). On a plain
 * http:// origin it is undefined and the SDK throws the cryptic
 *   "Cannot use 'in' operator to search for 'getDisplayMedia' in undefined".
 * The view must pre-flight this and show a clear "needs HTTPS" message BEFORE
 * calling client.init(), instead of letting the SDK crash. This pins that
 * guard so a refactor can't drop it.
 */
class ZoomSecureContextGuardTest extends TestCase
{
    public function test_zoom_view_guards_secure_context_before_sdk_init(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/frontend/student-dashboard/live/zoom.blade.php')
        );

        $this->assertStringContainsString('isSecureContext', $src,
            'zoom view must check window.isSecureContext.');
        $this->assertStringContainsString('navigator.mediaDevices', $src,
            'zoom view must check navigator.mediaDevices availability.');

        // The guard must come BEFORE client.init() so the SDK never runs in an
        // insecure context (which is what produces the getDisplayMedia crash).
        $guardPos = strpos($src, 'isSecureContext');
        $initPos  = strpos($src, 'client.init(');
        $this->assertNotFalse($guardPos);
        $this->assertNotFalse($initPos);
        $this->assertLessThan($initPos, $guardPos,
            'the secure-context guard must run before client.init().');

        // And it must surface an HTTPS-oriented message, not the raw SDK error.
        $this->assertStringContainsString('HTTPS', $src,
            'the guard must tell the user a secure (HTTPS) connection is required.');
    }
}
