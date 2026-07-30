<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — Skillgro template installer/addon-manager and
 * Jitsi integration were removed on 2026-05-07. If any of these files,
 * routes, classes, or DB tables come back, this test fails the build
 * so the re-introduction is loud rather than silent.
 *
 * If you legitimately need any of these things back, delete the
 * specific assertion AND update project_skillgro_removed_2026-05-07.md
 * + project_jitsi_removed_2026-05-07.md in the operator memory.
 */
class SkillgroAndJitsiPermanentlyRemovedTest extends TestCase
{
    public function test_skillgro_installer_module_is_absent(): void
    {
        $base = base_path();

        $forbiddenPaths = [
            'Modules/Installer',
            'Modules/Installer/app/Providers/InstallerServiceProvider.php',
            'Modules/Installer/app/Enums/InstallerInfo.php',
            'Modules/Installer/app/Helpers/helper.php',
            'Modules/Installer/app/Http/Middleware/SetupMiddleware.php',
            'Modules/Installer/app/Http/Middleware/PurchaseVerifyMiddleware.php',
            'Modules/GlobalSetting/app/Http/Controllers/ManageAddonController.php',
            'Modules/GlobalSetting/resources/views/addons',
            'Modules/GlobalSetting/resources/views/auto-update.blade.php',
            'app/Http/Controllers/Admin/AddonsController.php',
            'app/Models/CustomAddon.php',
            'bootstrap/cache/installer_module.php',
        ];

        foreach ($forbiddenPaths as $rel) {
            $abs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $this->assertFileDoesNotExist($abs, "Skillgro artifact came back: $rel");
        }
    }

    public function test_jitsi_integration_files_are_absent(): void
    {
        $base = base_path();

        $forbiddenPaths = [
            'app/Models/JitsiSetting.php',
            'app/Services/JitsiTokenService.php',
            'resources/views/frontend/instructor-dashboard/jitsi',
            'resources/views/frontend/instructor-dashboard/live-classes/jitsi.blade.php',
            'resources/views/frontend/pages/learning-player/partials/live/jitsi.blade.php',
            'resources/views/frontend/student-dashboard/live/jitsi.blade.php',
            'tests/Feature/Audit/JitsiTest.php',
        ];

        foreach ($forbiddenPaths as $rel) {
            $abs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $this->assertFileDoesNotExist($abs, "Jitsi artifact came back: $rel");
        }
    }

    public function test_no_forbidden_routes_are_registered(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());

        $forbidden = [
            // Skillgro install wizard
            'setup.verify', 'setup.checkParchase',
            'setup.requirements', 'setup.database', 'setup.database.submit',
            'setup.account', 'setup.account.submit',
            'setup.configuration', 'setup.configuration.submit',
            'setup.smtp', 'setup.smtp.update', 'setup.smtp.skip',
            'setup.complete', 'website.completed',
            // Skillgro addon manager
            'admin.addons.view', 'admin.addons.verify', 'admin.addons.install',
            'admin.addons.update.status', 'admin.addons.store',
            'admin.addons.install.start', 'admin.addons.delete', 'admin.addons.uninstall',
            'admin.addons.sync',
            // Skillgro auto-update
            'admin.system-update.index', 'admin.system-update.store',
            'admin.system-update.redirect', 'admin.system-update.delete',
            // Jitsi
            'instructor.jitsi-setting.index', 'instructor.jitsi-setting.update',
        ];

        $regressed = array_intersect($names, $forbidden);
        $this->assertEmpty(
            $regressed,
            'Removed route(s) re-registered: ' . implode(', ', $regressed)
        );
    }

    public function test_no_forbidden_db_tables_exist(): void
    {
        // Tables that backed Skillgro / Jitsi and were dropped on 2026-05-07.
        $forbiddenTables = [
            'configurations',   // Skillgro installer state
            'custom_addons',    // Skillgro addon registry
            'jitsi_settings',   // Jitsi credentials
        ];

        foreach ($forbiddenTables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "Removed DB table came back: $table — see project_skillgro_removed_2026-05-07.md"
            );
        }
    }

    public function test_course_live_classes_type_is_zoom_only(): void
    {
        // The enum was narrowed from ('zoom','jitsi') to ('zoom') by
        // 2026_05_07_180200_narrow_course_live_classes_type_enum. If a
        // future migration widens it again the Jitsi removal is
        // partially undone.
        $col = collect(\DB::select(
            "SHOW COLUMNS FROM course_live_classes WHERE Field = 'type'"
        ))->first();

        $this->assertNotNull($col, 'course_live_classes.type column missing');
        $this->assertSame(
            "enum('zoom')",
            (string) $col->Type,
            'course_live_classes.type widened — Jitsi or another provider re-introduced?'
        );
    }

    public function test_live_session_controllers_render_sdk_embed_not_external_redirect(): void
    {
        // Both LearningController::liveSession (student) and Coach\Live\
        // ClassController::coachLiveSession (coach) used to do
        // redirect()->away($join_url), sending users to app.zoom.us where
        // Zoom shows its own passcode + name prompt. The whole point of
        // the embedded SDK + signature endpoint is to skip that prompt.
        // If anyone re-introduces the redirect, this test fails.
        $student = (string) file_get_contents(app_path('Http/Controllers/Frontend/LearningController.php'));
        $this->assertStringNotContainsString(
            'redirect()->away($join)',
            $student,
            'LearningController::liveSession redirects students externally — passcode prompt regression'
        );
        $this->assertStringContainsString(
            "view('frontend.student-dashboard.live.zoom'",
            $student,
            'LearningController::liveSession is no longer rendering the embedded SDK launcher'
        );

        $coach = (string) file_get_contents(app_path('Http/Controllers/Frontend/Coach/LiveClassController.php'));
        $this->assertStringNotContainsString(
            'redirect()->away($join)',
            $coach,
            'Coach\\LiveClassController::coachLiveSession redirects coach externally — passcode prompt regression'
        );
        $this->assertStringContainsString(
            "view('frontend.instructor-dashboard.live-classes.zoom'",
            $coach,
            'Coach\\LiveClassController::coachLiveSession is no longer rendering the embedded SDK launcher'
        );
    }

    public function test_legacy_sdk_secret_leaking_zoom_view_is_absent(): void
    {
        // resources/views/frontend/pages/learning-player/partials/live/zoom.blade.php
        // was an older SDK launcher that rendered $instructor->zoom_credential->client_secret
        // straight into client-side <script>, leaking the SDK secret to every
        // attendee. The shared, server-signed launcher at
        // frontend.student-dashboard.live.zoom replaces it.
        $abs = base_path('resources/views/frontend/pages/learning-player/partials/live/zoom.blade.php');
        $this->assertFileDoesNotExist(
            $abs,
            'Legacy SDK-secret-leaking Zoom view came back — use frontend.student-dashboard.live.zoom instead'
        );
    }

    public function test_learning_player_js_does_not_link_to_zoom_app_externally(): void
    {
        // The "Zoom app" external button opened join_url (zoom.us/j/…) in a
        // new tab, sending students through Zoom's own web client which
        // forces a passcode + name prompt. Removed 2026-05-07; the only
        // join path is now the embedded Meeting SDK launcher.
        $js = (string) file_get_contents(public_path('frontend/js/default/learning-player.js'));
        $this->assertStringNotContainsString(
            'live.join_url',
            $js,
            'learning-player.js still references join_url — passcode-prompt regression risk'
        );
        $this->assertStringNotContainsString(
            'Zoom app</a>',
            $js,
            'learning-player.js still renders the external "Zoom app" button — bypasses our SDK embed'
        );
    }

    public function test_skillgro_branded_icon_font_files_are_renamed(): void
    {
        // The icon font was originally named flaticon-skillgro.css /
        // flaticon_skill_grow.* — renamed on 2026-05-07 to flaticon-mbs.css
        // / flaticon_mbs.*. Asserts neither the old filename nor the old
        // @font-face family name `flaticon_skill_grow` reappears.
        $base = base_path();

        $oldPaths = [
            'public/frontend/css/flaticon-skillgro.css',
            'public/frontend/fonts/flaticon_skill_grow.css',
            'public/frontend/fonts/flaticon_skill_grow.eot',
            'public/frontend/fonts/flaticon_skill_grow.html',
            'public/frontend/fonts/flaticon_skill_grow.scss',
            'public/frontend/fonts/flaticon_skill_grow.svg',
            'public/frontend/fonts/flaticon_skill_grow.ttf',
            'public/frontend/fonts/flaticon_skill_grow.woff',
            'public/frontend/fonts/flaticon_skill_grow.woff2',
        ];

        foreach ($oldPaths as $rel) {
            $abs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $this->assertFileDoesNotExist($abs, "Skillgro-branded font asset came back: $rel");
        }

        $newCss = $base . DIRECTORY_SEPARATOR . 'public/frontend/css/flaticon-mbs.css';
        $this->assertFileExists($newCss, 'Renamed icon font CSS missing — flaticon-mbs.css');
        $this->assertStringNotContainsString(
            'flaticon_skill_grow',
            (string) file_get_contents($newCss),
            '@font-face still names flaticon_skill_grow inside flaticon-mbs.css'
        );
    }

    public function test_no_modules_statuses_installer_entry(): void
    {
        $json = json_decode((string) file_get_contents(base_path('modules_statuses.json')), true);
        $this->assertIsArray($json);
        $this->assertArrayNotHasKey(
            'Installer',
            $json,
            'Installer module re-registered in modules_statuses.json'
        );
    }

    public function test_composer_autoload_does_not_load_installer_helper(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $files = $composer['autoload']['files'] ?? [];

        foreach ($files as $f) {
            $this->assertStringNotContainsStringIgnoringCase(
                'Modules/Installer',
                $f,
                'composer.json autoload.files re-references the deleted Installer helper'
            );
        }
    }
}
