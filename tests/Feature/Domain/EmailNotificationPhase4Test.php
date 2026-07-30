<?php

namespace Tests\Feature\Domain;

use App\Models\CoachTrialEnquiry;
use App\Models\CourseLiveClass;
use App\Models\User;
use App\Notifications\InAppNotification;
use App\Notifications\LiveClassScheduledToStudent;
use App\Notifications\TrialBookingToStudent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 2026-07-09 — Email/Notification audit, Phase 4 (Currency & Formatting).
 * Consistent amount format (4.3), currency-neutral money glyph (4.1), and no
 * dangling empty labels (4.5).
 */
class EmailNotificationPhase4Test extends TestCase
{
    use DatabaseTransactions;

    private function seedInr(): void
    {
        DB::table('multi_currencies')->updateOrInsert(['currency_code' => 'INR'],
            ['currency_icon' => '₹', 'currency_position' => 'before_price', 'currency_rate' => 1, 'is_default' => 'yes']);
        Cache::forget('allCurrencies');
    }

    /* ── 4.3 consistent amount format ──────────────────────────────── */

    public function test_trial_amount_uses_consistent_symbol_format(): void
    {
        $this->seedInr();
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $enq = (new CoachTrialEnquiry())->forceFill([
            'coach_id' => $coach->id, 'name' => 'Asha', 'email' => 'a@b.test',
            'plan_type' => 'individual', 'course_type' => 'yoga', 'time_slot' => '10:00',
            'price' => 500, 'currency' => 'INR', 'payment_status' => 'paid',
        ]);

        $n = TrialBookingToStudent::fromEnquiry($enq, 'CoachX');
        $ref = new \ReflectionMethod($n, 'placeholders');
        $ref->setAccessible(true);
        $ph = $ref->invoke($n, $coach);

        // ₹500.00 — not "INR 500.00" and not "500.00 INR".
        $this->assertSame('₹500.00', $ph['amount']);
    }

    public function test_free_trial_amount_stays_free(): void
    {
        $this->seedInr();
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $enq = (new CoachTrialEnquiry())->forceFill([
            'coach_id' => $coach->id, 'name' => 'Asha', 'email' => 'a@b.test',
            'plan_type' => 'individual', 'course_type' => 'yoga', 'time_slot' => '10:00',
            'price' => 0, 'currency' => 'INR', 'payment_status' => 'free',
        ]);
        $n = TrialBookingToStudent::fromEnquiry($enq, 'CoachX');
        $ref = new \ReflectionMethod($n, 'placeholders');
        $ref->setAccessible(true);
        $this->assertSame('Free', $ref->invoke($n, $coach)['amount']);
    }

    /* ── 4.5 no dangling empty labels ──────────────────────────────── */

    public function test_live_class_empty_batch_and_coach_have_fallbacks(): void
    {
        $coach = User::factory()->create(['role' => 'instructor', 'coach_id' => null, 'name' => 'Guru Anand']);
        $course = (new \App\Models\Course())->forceFill([
            'instructor_id' => $coach->id, 'added_by' => $coach->id, 'title' => 'Yoga',
            'slug' => 'yoga-' . uniqid(), 'price' => 0, 'status' => 'active', 'is_approved' => 'approved', 'type' => 'live',
        ]);
        $course->save();
        $chapter = (new \App\Models\CourseChapter())->forceFill(['instructor_id' => $coach->id, 'course_id' => $course->id, 'title' => 'Ch', 'order' => 1, 'status' => 'active']);
        $chapter->save();
        $lesson = (new \App\Models\CourseChapterLesson())->forceFill(['title' => 'Lesson 1', 'instructor_id' => $coach->id, 'course_id' => $course->id, 'chapter_id' => $chapter->id, 'duration' => 60, 'storage' => 'live', 'file_type' => 'live']);
        $lesson->save();
        $lc = (new CourseLiveClass())->forceFill(['course_id' => $course->id, 'lesson_id' => $lesson->id, 'batch_id' => null, 'start_time' => now()->addDay(), 'expected_duration_minutes' => 60]);
        $lc->save();
        $student = User::factory()->create(['role' => 'student', 'coach_id' => $coach->id]);

        // Empty batch + empty coach passed in.
        $n = new LiveClassScheduledToStudent($lc, 'Yoga', 'Lesson 1', false, '', '');
        $ref = new \ReflectionMethod($n, 'placeholders');
        $ref->setAccessible(true);
        $ph = $ref->invoke($n, $student);

        $this->assertSame('—', $ph['batch_name'], 'no dangling "Batch:" label');
        $this->assertSame('Guru Anand', $ph['coach_name'], 'coach name resolved from the course owner');
    }

    /* ── 4.1 currency-neutral money glyph ──────────────────────────── */

    public function test_money_icon_glyph_is_currency_neutral(): void
    {
        $user = User::factory()->create(['role' => 'instructor', 'coach_id' => null]);
        $n = new class extends InAppNotification {
            public function __construct()
            {
                $this->title = 'Payout approved';
                $this->body = 'Your withdrawal has been approved.';
                $this->icon = 'fa-wallet';
                $this->url = '/';
            }
        };

        $html = $n->toMail($user)->render();
        $this->assertStringContainsString('💰', $html, 'money icon uses a currency-neutral glyph');
        // The old hardcoded "$" glyph must not be the icon-circle character.
        $this->assertStringNotContainsString('>$<', $html);
    }
}
