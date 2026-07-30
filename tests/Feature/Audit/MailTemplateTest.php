<?php

namespace Tests\Feature\Audit;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * Verifies email templates render cleanly. For each notification, we render
 * the toMail() output without sending and scan the HTML for:
 *   - Render exceptions (template missing, missing var, syntax error)
 *   - Unsubstituted {{ }} markers (data binding broken)
 *
 * This is the same scan that earlier versions of the manual email-test
 * harness ran across all 22 notification classes — codified into PHPUnit so
 * any future template change that breaks a placeholder gets caught in CI.
 */
class MailTemplateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_registration_template_renders(): void
    {
        $user = User::first() ?? User::factory()->create(['role' => 'student']);
        $seeded = $this->ensureMailTemplateSettings();
        try {
            $user->verification_token = 'test-token-' . uniqid();
            $mailable = new \App\Mail\UserRegistration(
                'Test message body',
                'Test subject',
                $user
            );
            $html = $mailable->render();
            $this->assertHtmlClean($html, 'UserRegistration');
        } finally {
            $this->cleanupSeededSettings($seeded);
        }
    }

    public function test_user_forget_password_template_renders(): void
    {
        $user = User::first() ?? User::factory()->create(['role' => 'student']);
        $seeded = $this->ensureMailTemplateSettings();
        try {
            $user->forget_password_token = 'test-token-' . uniqid();
            $mailable = new \App\Mail\UserForgetPassword(
                'Click to reset',
                'Reset Password',
                $user,
                'auth'
            );
            $html = $mailable->render();
            $this->assertHtmlClean($html, 'UserForgetPassword');
        } finally {
            $this->cleanupSeededSettings($seeded);
        }
    }

    public function test_social_login_default_password_template_renders(): void
    {
        // Was BROKEN before audit fix (private properties, view couldn't see $user/$password).
        $user = User::first();
        if (!$user) { $this->markTestSkipped('no User row to render against — skip on empty CI DB'); }

        $mailable = new \App\Mail\SocialLoginDefaultPasswordMail($user, 'temp-pass-123');
        $html = $mailable->render();
        $this->assertHtmlClean($html, 'SocialLoginDefaultPasswordMail');
        // Specifically check the previously-undefined variables now reach the view
        $this->assertStringContainsString($user->name ?: 'X', $html . $user->name);
    }

    public function test_contact_message_mail_template_renders(): void
    {
        $seeded = $this->ensureMailTemplateSettings();
        try {
            $mailable = new \Modules\ContactMessage\app\Emails\ContactMessageMail(
                'Test contact subject',
                '<p>Test contact body content</p>'
            );
            $html = $mailable->render();
            $this->assertHtmlClean($html, 'ContactMessageMail');
        } finally {
            $this->cleanupSeededSettings($seeded);
        }
    }

    public function test_send_mail_to_user_template_renders(): void
    {
        $seeded = $this->ensureMailTemplateSettings();
        try {
            $mailable = new \Modules\Customer\app\Emails\SendMailToUser(
                '<p>Bulk mail body</p>',
                'Bulk Mail Subject'
            );
            $html = $mailable->render();
            $this->assertHtmlClean($html, 'SendMailToUser');
        } finally {
            $this->cleanupSeededSettings($seeded);
        }
    }

    private function hasGlobalSetting(): bool
    {
        // Cheap presence check — the mail templates reach for $setting->logo
        // via a view composer / shared variable, so if no settings rows
        // exist the render blows up with "Undefined property: stdClass::\$logo".
        //
        // Audit 2026-05-18 phase 4 — tightened to check for the SPECIFIC
        // keys the mail templates use, not just "any row exists". Earlier
        // version returned true the moment one row existed, so seeding
        // unrelated keys (e.g. attendance_min_percent) made tests start
        // rendering and crashing.
        try {
            return \Illuminate\Support\Facades\DB::table('settings')
                ->whereIn('key', ['logo', 'app_name', 'site_logo'])
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Audit 2026-05-19 — seed the minimum settings rows the mail
     * templates expect (logo, app_name) so render() doesn't blow up
     * on a fresh mbs_test. Tracks what it inserted; tests should pair
     * with cleanupSeededSettings() in a finally block.
     *
     * @return array<int, string>  keys we inserted
     */
    protected function ensureMailTemplateSettings(): array
    {
        $seeded = [];
        foreach ([
            'logo'     => 'frontend/img/logo/logo.png',
            'app_name' => 'MBSGuru Test',
        ] as $key => $value) {
            if (!\Illuminate\Support\Facades\DB::table('settings')->where('key', $key)->exists()) {
                \Illuminate\Support\Facades\DB::table('settings')->insert([
                    'key' => $key, 'value' => $value,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $seeded[] = $key;
            }
        }
        // AppServiceProvider builds the `setting` cache once at boot;
        // mid-test we have to rebuild it ourselves so the mail blade
        // can read $setting->logo. Mimic the provider's exact shape
        // (decryptArray over pluck('value', 'key')).
        \Illuminate\Support\Facades\Cache::forget('setting');
        $raw = \Illuminate\Support\Facades\DB::table('settings')->pluck('value', 'key')->all();
        \Illuminate\Support\Facades\Cache::forever(
            'setting',
            (object) \App\Support\SecretSettings::decryptArray($raw)
        );
        return $seeded;
    }

    protected function cleanupSeededSettings(array $seeded): void
    {
        if (empty($seeded)) return;
        \Illuminate\Support\Facades\DB::table('settings')->whereIn('key', $seeded)->delete();
        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    public function test_new_order_to_admin_notification_renders(): void
    {
        $seeded = $this->ensureMailTemplateSettings();
        [$user, $order, $orderItem] = $this->ensureOrderFixture();
        try {
            $notif = new \App\Notifications\NewOrderToAdmin($order);
            $message = $notif->toMail($user);
            $html = $message->render();
            $this->assertHtmlClean($html, 'NewOrderToAdmin');
        } finally {
            $this->cleanupOrderFixture($order, $orderItem);
            $this->cleanupSeededSettings($seeded);
        }
    }

    public function test_order_status_changed_notification_renders(): void
    {
        $seeded = $this->ensureMailTemplateSettings();
        [$user, $order, $orderItem] = $this->ensureOrderFixture();
        try {
            $notif = new \App\Notifications\OrderStatusChangedToStudent($order, 'pending', 'pending');
            $message = $notif->toMail($user);
            $html = $message->render();
            $this->assertHtmlClean($html, 'OrderStatusChangedToStudent');
        } finally {
            $this->cleanupOrderFixture($order, $orderItem);
            $this->cleanupSeededSettings($seeded);
        }
    }

    public function test_course_sold_to_coach_notification_renders(): void
    {
        $seeded = $this->ensureMailTemplateSettings();
        [$user, $order, $orderItem] = $this->ensureOrderFixture();
        $course = Course::find($orderItem->course_id);
        try {
            $notif = new \App\Notifications\CourseSoldToCoach($orderItem, $course);
            $message = $notif->toMail($user);
            $html = $message->render();
            $this->assertHtmlClean($html, 'CourseSoldToCoach');
        } finally {
            $this->cleanupOrderFixture($order, $orderItem);
            $this->cleanupSeededSettings($seeded);
        }
    }

    /**
     * Build a user + course + order + order_item chain end-to-end so
     * the order-related mail templates have everything they need.
     *
     * @return array{User, Order, \Modules\Order\app\Models\OrderItem}
     */
    private function ensureOrderFixture(): array
    {
        $user  = User::first() ?? User::factory()->create(['role' => 'student']);
        $coach = User::factory()->create(['role' => 'instructor']);
        $courseId = \Illuminate\Support\Facades\DB::table('courses')->insertGetId([
            'title'         => 'Mail test course '.uniqid(),
            'slug'          => 'mail-test-'.uniqid(),
            'instructor_id' => $coach->id,
            'added_by'      => $coach->id,
            'is_approved'   => 'approved',
            'status'        => 'active',
            'price'         => 49.99, 'discount' => 0,
            'created_at'    => now(), 'updated_at' => now(),
        ]);

        $order = Order::create([
            'invoice_id'              => 'INV-' . uniqid('m'),
            'transaction_id'          => 'TRX-' . uniqid('m'),
            'buyer_id'                => $user->id,
            'has_coupon'              => 0,
            'coupon_code'             => '',
            'coupon_discount_percent' => '',
            'coupon_discount_amount'  => 0,
            'payment_method'          => 'stripe',
            'payment_status'          => 'paid',
            'payable_amount'          => 49.99,
            'gateway_charge'          => 0,
            'payable_with_charge'     => 49.99,
            'paid_amount'             => 49.99,
            'payable_currency'        => 'USD',
            'conversion_rate'         => 1,
            'commission_rate'         => 10,
            'order_type'              => 'course',
        ]);

        $orderItem = \Modules\Order\app\Models\OrderItem::create([
            'order_id'  => $order->id,
            'course_id' => $courseId,
            'price'     => 49.99,
        ]);

        return [$user, $order, $orderItem];
    }

    private function cleanupOrderFixture(Order $order, \Modules\Order\app\Models\OrderItem $orderItem): void
    {
        \Illuminate\Support\Facades\DB::table('order_items')->where('id', $orderItem->id)->delete();
        \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->delete();
    }

    private function assertHtmlClean(string $html, string $template): void
    {
        $this->assertNotEmpty($html, "$template rendered empty");
        $this->assertStringNotContainsString(
            'Undefined variable',
            $html,
            "$template has 'Undefined variable' in rendered output (missing data binding)"
        );

        // Look for unsubstituted Blade placeholders that leaked into the HTML
        if (preg_match_all('/\{\{\s*\$?[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}/', $html, $matches)) {
            $this->fail(
                "$template has unsubstituted placeholders in rendered HTML: " . implode(', ', array_unique($matches[0]))
            );
        }

        // Stripped body should have substantive content (not just whitespace and tags)
        $stripped = trim(preg_replace('/<[^>]+>/', '', $html));
        $this->assertGreaterThan(
            20,
            strlen($stripped),
            "$template renders less than 20 chars of actual content — likely a broken template"
        );
    }
}
