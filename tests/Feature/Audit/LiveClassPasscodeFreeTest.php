<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Live-class passcode invariants — revised 2026-05-11.
 *
 * BACKGROUND
 *   The 2026-05-08 overhaul tried to make live classes fully passcode-free:
 *   `password: false` on every create/update, signature controller didn't
 *   return one, launcher didn't pass one to client.join(). Zoom subsequently
 *   locked the "Require one security option" toggle for Basic/free accounts,
 *   so meetings get a passcode auto-assigned regardless of what the API
 *   request asks for. Fighting Zoom on every join was breaking real users.
 *
 * CURRENT INVARIANT
 *   The passcode is permitted *only* on the server → XHR → memory path:
 *     • LiveClassController + ZoomRecreateMeetings persist whatever Zoom
 *       returns into course_live_classes.password.
 *     • ZoomSignatureController returns it in the JSON response.
 *     • The launcher forwards cfg.password into client.join().
 *
 *   What's still forbidden (and this test enforces):
 *     • Rendering the passcode into HTML source (Blade {{ }} or attributes
 *       — anything that lands in view-source).
 *     • Asking the user to type a passcode (no passcode-recovery UI, no
 *       passcode prompt, no `?passcode=` URL param, no joinWithFallbacks).
 *     • Admin-side passcode-override UI / route / controller method.
 *     • Modal forms that collect a passcode as a user input.
 *     • Requiring `password` in ChapterLessonRequest validation.
 *
 *   If you legitimately need to re-introduce a removed bit (e.g. an
 *   admin-side override modal), delete the specific assertion AND record
 *   why in project memory.
 */
class LiveClassPasscodeFreeTest extends TestCase
{
    public function test_signature_controller_does_not_extract_pwd_from_join_url(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/ZoomSignatureController.php')
        );

        // The signature controller is allowed to return `password` — it
        // travels in the XHR response, never in HTML source. What it must
        // not do is fish `pwd=` out of join_url (that was the legacy
        // workaround when we didn't store the column).
        $this->assertStringNotContainsString(
            'parse_url',
            $src,
            'ZoomSignatureController must not extract pwd= from join_url — read course_live_classes.password instead'
        );
        $this->assertStringNotContainsString(
            "'encryptedPassword'",
            $src,
            'ZoomSignatureController must not return encryptedPassword — only the plain `password` field is forwarded'
        );
    }

    public function test_launcher_does_not_collect_passcode_from_user(): void
    {
        $launcher = (string) file_get_contents(
            base_path('resources/views/frontend/student-dashboard/live/zoom.blade.php')
        );

        // The launcher MAY pass cfg.password into client.join() (Zoom's
        // account policy forces a passcode on Basic plans and we have to
        // satisfy the handshake). What it must NOT do is ask the user to
        // type one or pull one from a URL — passcode-as-user-input was the
        // old recovery UI we deleted.
        $this->assertStringNotContainsString(
            'showPasscodeRecovery',
            $launcher,
            'Launcher must not ship a passcode recovery card — passcode flows entirely server-to-SDK'
        );
        $this->assertStringNotContainsString(
            'joinWithFallbacks',
            $launcher,
            'Launcher must not ship the passcode-fallback join helper — the server-returned passcode is authoritative'
        );
        $this->assertStringNotContainsString(
            '?passcode=',
            $launcher,
            'Launcher must not read a ?passcode= URL param — value travels through the signature endpoint, not the URL'
        );
        $this->assertStringNotContainsString(
            'cfg.encryptedPassword',
            $launcher,
            'Launcher must not pass cfg.encryptedPassword to client.join() — only the plain `password` is supported'
        );
    }

    public function test_launcher_does_not_render_password_into_html_source(): void
    {
        $launcher = (string) file_get_contents(
            base_path('resources/views/frontend/student-dashboard/live/zoom.blade.php')
        );

        // The passcode must never appear in view-source. The only legal
        // path is through the signature endpoint's XHR response (cfg.password)
        // — which lives in memory, not in HTML. Anything that Blade-prints
        // the password directly (e.g. `{{ $lesson->live->password }}`,
        // attribute interpolation, hidden inputs) defeats the whole point.
        $forbidden = [
            '$lesson->live->password',
            '$live->password',
            '$liveClass->password',
            '{{ $password',
            '{!! $password',
            'data-password=',
            'name="password"',
        ];
        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $launcher,
                "Launcher must not render `{$needle}` into HTML — passcode must stay on the XHR path only"
            );
        }
    }

    public function test_create_and_edit_modals_do_not_have_password_field(): void
    {
        $views = [
            'resources/views/frontend/instructor-dashboard/course/partials/live-create-modal.blade.php',
            'resources/views/frontend/instructor-dashboard/course/partials/live-edit-modal.blade.php',
            'resources/views/frontend/instructor-dashboard/subscription-histories/partials/live-create-modal.blade.php',
            'resources/views/frontend/instructor-dashboard/subscription-histories/partials/live-edit-modal.blade.php',
        ];

        foreach ($views as $rel) {
            $body = (string) file_get_contents(base_path($rel));

            $this->assertStringNotContainsString(
                'name="password"',
                $body,
                "{$rel} must not contain a passcode/password input — passcode comes from Zoom, never user input"
            );
            $this->assertStringNotContainsString(
                'name="join_url"',
                $body,
                "{$rel} must not contain a join_url input — meeting metadata comes from Zoom's create response"
            );
        }
    }

    public function test_index_view_does_not_have_passcode_modal_or_button(): void
    {
        $body = (string) file_get_contents(
            base_path('resources/views/frontend/instructor-dashboard/live-classes/index.blade.php')
        );

        $forbidden = [
            'lcPasscodeModal',
            'lc-passcode-btn',
            'lcPasscodeForm',
            'lcPasscodeMeetingId',
            'lcPasscodeInput',
            'live-class.passcode',
        ];

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $body,
                "Live-classes index must not reference '{$needle}' — admin-side passcode UI was removed"
            );
        }
    }

    public function test_passcode_override_route_is_gone(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        $this->assertNotContains(
            'instructor.live-class.passcode',
            $names,
            'instructor.live-class.passcode route must not be registered — passcode override was removed'
        );
    }

    public function test_controller_no_longer_has_update_passcode_method(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );

        $this->assertStringNotContainsString(
            'function updatePasscode',
            $src,
            'LiveClassController must not expose updatePasscode — passcode is auto-assigned by Zoom, not edited by hand'
        );
    }

    public function test_chapter_lesson_request_does_not_require_password(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Requests/Frontend/ChapterLessonRequest.php')
        );

        // The validation rule for 'password' must NOT be 'required'.
        // Allow 'sometimes'/'nullable' (legacy callers may still post the
        // field). Forbid 'required' specifically.
        $this->assertDoesNotMatchRegularExpression(
            "/['\"]password['\"]\s*=>\s*\[?\s*['\"]required['\"]/",
            $src,
            'ChapterLessonRequest must not mark password as required — passcode is never user-supplied'
        );
    }

    public function test_zoom_api_meeting_creation_requests_passcode_free(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );

        // Architecture: the create and update paths share one payload
        // builder (`zoomMeetingPayload`) so an edit can't silently drop
        // settings the create path applied. Pre-2026-05-09 the two paths
        // duplicated the settings array and quietly drifted.
        $this->assertMatchesRegularExpression(
            '/private function zoomMeetingPayload\s*\(/',
            $src,
            'LiveClassController must define zoomMeetingPayload() so create + update share one payload shape'
        );

        // We still ASK Zoom for a passcode-free meeting in the API call —
        // even though Zoom's account policy may override on the response.
        // Requesting `password: false` keeps the per-meeting outcome on
        // accounts where the toggle ISN'T locked, and signals intent in
        // case Zoom relaxes the policy.
        $checks = [
            ["/'password'\s*=>\s*false/",         "settings.password = false"],
            ["/'join_before_host'\s*=>\s*true/",  "settings.join_before_host = true"],
            ["/'waiting_room'\s*=>\s*false/",     "settings.waiting_room = false"],
            ["/'meeting_authentication'\s*=>\s*false/", "settings.meeting_authentication = false"],
        ];
        foreach ($checks as [$pattern, $label]) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $src,
                "Zoom meeting payload must include {$label}"
            );
        }

        // Both store() and update() must route through the helper.
        preg_match_all('/\$this->zoomMeetingPayload\s*\(/', $src, $m);
        $this->assertGreaterThanOrEqual(
            2,
            count($m[0]),
            'Both store() and update() must call $this->zoomMeetingPayload() — duplicating the settings inline lets the two paths drift'
        );

        // The update path must PATCH the existing meeting, not POST a
        // fresh one (which orphans the original on Zoom). The recovery
        // fallback to POST on PATCH 404 is allowed.
        $this->assertMatchesRegularExpression(
            "#->patch\(\s*\n?\s*'https://api\.zoom\.us/v2/meetings/#",
            $src,
            'update() must PATCH /v2/meetings/{id} rather than POST /v2/users/me/meetings'
        );
    }

    public function test_passcode_is_persisted_from_zoom_response(): void
    {
        // The whole "passcode forwarded via XHR" design only works if the
        // passcode is actually stored on the row. If a refactor strips
        // `password` from the column writes in either create or recreate,
        // the signature endpoint returns empty and joins fail again.

        $controller = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/Coach/LiveClassController.php')
        );
        $this->assertMatchesRegularExpression(
            "/['\"]password['\"]\s*=>\s*\\\$password/",
            $controller,
            'LiveClassController create/update must write Zoom-returned password to course_live_classes.password'
        );

        $recreate = (string) file_get_contents(
            app_path('Console/Commands/ZoomRecreateMeetings.php')
        );
        $this->assertStringContainsString(
            "\$body['password']",
            $recreate,
            'ZoomRecreateMeetings must capture password from Zoom create response'
        );
        $this->assertMatchesRegularExpression(
            "/['\"]password['\"]\s*=>\s*\\\$newPass/",
            $recreate,
            'ZoomRecreateMeetings must persist the captured password to course_live_classes.password'
        );
    }
}
