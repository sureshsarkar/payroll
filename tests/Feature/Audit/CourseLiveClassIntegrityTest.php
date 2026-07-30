<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tripwire — `course_live_classes.course_id` was nullable
 * for years and both write paths (CourseContentController + Coach\
 * LiveClassController) forgot to set it, leaving every row in
 * production orphaned. Fixed on 2026-05-07: the columns are now
 * NOT NULL with a FK to courses(id), and both controllers populate
 * course_id from the parent lesson.
 *
 * If anyone weakens the constraint or removes the controller
 * assignment, this test fails.
 */
class CourseLiveClassIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_course_id_column_is_not_null(): void
    {
        // information_schema is the only source of truth for NULLability;
        // Eloquent doesn't expose it directly.
        $row = DB::selectOne(<<<SQL
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_NAME = 'course_live_classes'
              AND COLUMN_NAME = 'course_id'
              AND TABLE_SCHEMA = DATABASE()
        SQL);

        $this->assertNotNull($row, 'course_live_classes.course_id missing entirely');
        $this->assertSame(
            'NO',
            $row->IS_NULLABLE,
            'course_live_classes.course_id reverted to nullable — orphan rows can return'
        );
    }

    public function test_course_id_has_foreign_key_to_courses(): void
    {
        $row = DB::selectOne(<<<SQL
            SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = 'course_live_classes'
              AND COLUMN_NAME = 'course_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND TABLE_SCHEMA = DATABASE()
        SQL);

        $this->assertNotNull(
            $row,
            'course_live_classes.course_id has no FK — referential integrity not enforced'
        );
        $this->assertSame(
            'courses',
            $row->REFERENCED_TABLE_NAME,
            'course_live_classes.course_id FK points at the wrong table'
        );
    }

    public function test_no_existing_rows_have_null_course_id(): void
    {
        // The NOT NULL constraint should make this impossible, but a quick
        // sanity check guards against accidental schema drift in tests
        // that bypass migrations.
        if (!Schema::hasTable('course_live_classes')) {
            $this->markTestSkipped('course_live_classes table missing in this test DB');
        }
        $count = DB::table('course_live_classes')->whereNull('course_id')->count();
        $this->assertSame(0, $count, "{$count} course_live_classes rows still have course_id=NULL");
    }

    public function test_both_write_paths_populate_course_id(): void
    {
        // Static check on the source — much cheaper than spinning up the
        // full HTTP stack to reach each controller's create call.
        $coach = (string) file_get_contents(app_path('Http/Controllers/Frontend/Coach/LiveClassController.php'));
        $this->assertMatchesRegularExpression(
            "/CourseLiveClass::create\(\[[^\]]*'course_id'\s*=>/s",
            $coach,
            'Coach\\LiveClassController::store no longer sets course_id on CourseLiveClass::create — orphan-row regression risk'
        );

        $content = (string) file_get_contents(app_path('Http/Controllers/Frontend/CourseContentController.php'));
        $this->assertMatchesRegularExpression(
            "/CourseLiveClass::create\(\[[^\]]*'course_id'\s*=>/s",
            $content,
            'CourseContentController::lessonStore no longer sets course_id on CourseLiveClass::create — orphan-row regression risk'
        );
    }
}
