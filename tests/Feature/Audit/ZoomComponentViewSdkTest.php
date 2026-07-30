<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Asserts the live-class launcher uses the Zoom Web SDK Component View
 * (zoom-meeting-embedded-*.min.js + ZoomMtgEmbedded.createClient API),
 * not the legacy Client View (zoom-meeting-*.min.js + ZoomMtg.init/join).
 *
 * Migration happened on 2026-05-07. Component View renders the meeting
 * into a sized div inside our MBS-branded chrome instead of taking
 * over the entire viewport. The signature payload is identical between
 * the two views so the backend ZoomSignatureController doesn't change.
 *
 * If the launcher reverts to Client View this test fails, alongside
 * losing the course-breadcrumb header + Leave button + custom toolbar.
 */
class ZoomComponentViewSdkTest extends TestCase
{
    use DatabaseTransactions;

    private const LAUNCHER = 'resources/views/frontend/student-dashboard/live/zoom.blade.php';

    public function test_launcher_loads_component_view_sdk_bundle(): void
    {
        $src = (string) file_get_contents(base_path(self::LAUNCHER));
        $this->assertStringContainsString(
            'zoom-meeting-embedded',
            $src,
            'Launcher no longer loads zoom-meeting-embedded — Component View SDK regression.'
        );
        $this->assertStringNotContainsString(
            'zoom-meeting-3.8.5.min.js',
            $src,
            'Launcher is still loading Client View SDK (zoom-meeting-3.8.5.min.js) alongside or instead of Component View.'
        );
    }

    public function test_launcher_uses_component_view_api(): void
    {
        $src = (string) file_get_contents(base_path(self::LAUNCHER));

        $this->assertStringContainsString(
            'ZoomMtgEmbedded.createClient',
            $src,
            'Component View entrypoint ZoomMtgEmbedded.createClient() missing.'
        );
        $this->assertStringNotContainsString(
            'ZoomMtg.init(',
            $src,
            'Legacy Client View ZoomMtg.init() call present — Component View migration is incomplete.'
        );
        $this->assertStringNotContainsString(
            'ZoomMtg.join(',
            $src,
            'Legacy Client View ZoomMtg.join() call present — Component View migration is incomplete.'
        );
    }

    public function test_launcher_provides_zoom_app_root_container(): void
    {
        $src = (string) file_get_contents(base_path(self::LAUNCHER));
        // Component View needs a target div — by Zoom convention named
        // `meetingSDKElement` and passed as `zoomAppRoot` to client.init().
        $this->assertStringContainsString(
            'id="meetingSDKElement"',
            $src,
            'Launcher is missing the #meetingSDKElement target div for Component View.'
        );
        $this->assertStringContainsString(
            'zoomAppRoot:',
            $src,
            'client.init() must receive zoomAppRoot — without it the SDK has nowhere to render.'
        );
    }

    public function test_launcher_renders_mbs_chrome_around_sdk(): void
    {
        $src = (string) file_get_contents(base_path(self::LAUNCHER));
        $this->assertStringContainsString(
            'mbs-live-header',
            $src,
            'MBS Guru header chrome missing from launcher — meeting will load with no LMS context.'
        );
        $this->assertStringContainsString(
            'mbsLeaveBtn',
            $src,
            'MBS Guru Leave button missing — students can\'t exit cleanly back to course.'
        );
    }

    public function test_launcher_renders_notes_drawer(): void
    {
        // The personal-notes drawer overlays the meeting (does NOT resize
        // the SDK so video quality is unaffected). Notes persist to
        // localStorage keyed by lesson id — never sent server-side.
        $src = (string) file_get_contents(base_path(self::LAUNCHER));
        $this->assertStringContainsString(
            'mbs-notes-drawer',
            $src,
            'Notes drawer markup missing from launcher'
        );
        $this->assertStringContainsString(
            'mbsNotesText',
            $src,
            'Notes textarea missing from launcher'
        );
        $this->assertStringContainsString(
            'localStorage.setItem(STORAGE_KEY',
            $src,
            'Notes drawer no longer persists to localStorage — typed input would vanish on reload'
        );
        $this->assertStringContainsString(
            'mbs-live-notes-v1-',
            $src,
            'Notes localStorage key not namespaced by lesson — risk of leaking notes across classes'
        );
    }

    public function test_signature_payload_works_for_component_view(): void
    {
        // Component View accepts the same JWT shape as Client View
        // ({sdkKey, appKey, mn, role, iat, exp, tokenExp}). If a future
        // refactor strips any of these from ZoomSignatureService, both
        // views will silently fail to join. We assert all five are still
        // emitted.
        $src = (string) file_get_contents(app_path('Services/ZoomSignatureService.php'));
        foreach (["'sdkKey'", "'appKey'", "'mn'", "'role'", "'iat'", "'exp'", "'tokenExp'"] as $key) {
            $this->assertStringContainsString(
                $key,
                $src,
                "ZoomSignatureService payload no longer includes {$key} — Component View join() will reject the JWT."
            );
        }
    }
}
