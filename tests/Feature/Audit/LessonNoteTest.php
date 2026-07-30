<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — server-side personal notes (lesson_notes table)
 * added on 2026-05-07 to back the localStorage notes drawer with cross-
 * device sync.
 *
 * Privacy is the load-bearing property: rows must be uniquely keyed by
 * (user_id, lesson_id), the controller must filter `where user_id =
 * auth->id`, and the routes must be auth-gated. If any of those break,
 * notes leak across users.
 */
class LessonNoteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lesson_notes_table_exists_with_expected_schema(): void
    {
        $this->assertTrue(
            Schema::hasTable('lesson_notes'),
            'lesson_notes table missing — server-side notes sync is broken'
        );
        foreach (['user_id', 'lesson_id', 'body', 'created_at', 'updated_at'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('lesson_notes', $col),
                "lesson_notes.$col missing"
            );
        }
    }

    public function test_lesson_notes_has_unique_per_user_per_lesson(): void
    {
        // Privacy + correctness: one row per (user, lesson). Without this
        // unique, the upsert in store() degenerates to "always insert"
        // and notes accumulate forever / get confused on reads.
        $row = \DB::selectOne(<<<SQL
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_NAME = 'lesson_notes'
              AND TABLE_SCHEMA = DATABASE()
              AND NON_UNIQUE = 0
              AND INDEX_NAME != 'PRIMARY'
            LIMIT 1
        SQL);
        $this->assertNotNull(
            $row,
            'lesson_notes is missing a unique index across (user_id, lesson_id) — upsert degenerates'
        );
    }

    public function test_routes_are_auth_gated(): void
    {
        $names = array_keys(Route::getRoutes()->getRoutesByName());
        $this->assertContains('lesson-notes.show',  $names);
        $this->assertContains('lesson-notes.store', $names);

        foreach (['lesson-notes.show', 'lesson-notes.store'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains(
                'auth',
                $middleware,
                "Route $name is missing the auth middleware — notes endpoint exposed to unauthenticated callers"
            );
        }
    }

    public function test_controller_filters_by_authed_user_id(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Frontend/LessonNoteController.php')
        );
        $this->assertStringContainsString(
            "where('user_id', \$user->id)",
            $src,
            'Controller no longer filters by auth->id when reading — could leak other users\' notes'
        );
        $this->assertStringContainsString(
            "updateOrCreate(",
            $src,
            'Controller no longer upserts — risk of duplicate rows or stale data'
        );
        $this->assertStringContainsString(
            "Enrollment::where",
            $src,
            'Controller is missing the enrollment authorisation gate'
        );
    }

    public function test_launcher_persists_notes_to_server(): void
    {
        $launcher = (string) file_get_contents(
            base_path('resources/views/frontend/student-dashboard/live/zoom.blade.php')
        );
        $this->assertStringContainsString(
            'lesson-notes.show',
            $launcher,
            'Launcher does not GET the server copy — multi-device sync broken'
        );
        $this->assertStringContainsString(
            'lesson-notes.store',
            $launcher,
            'Launcher does not POST to the server — cross-device sync broken'
        );
        $this->assertStringContainsString(
            'navigator.sendBeacon',
            $launcher,
            'Launcher no longer sends a sendBeacon flush on pagehide — abrupt tab close loses unsynced notes'
        );
    }
}
