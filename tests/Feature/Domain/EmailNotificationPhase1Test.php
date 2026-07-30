<?php

namespace Tests\Feature\Domain;

use App\Models\User;
use App\Notifications\OrderStatusChangedToStudent;
use App\Services\MailSenderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Order\app\Models\Order;
use Tests\TestCase;

/**
 * 2026-07-09 — Email/Notification audit, Phase 1 (Correctness & Data-Safety).
 * Regression coverage for the batch name-bleed bug, session-independent money
 * formatting, unresolved-token stripping, and the order-status enum leak.
 */
class EmailNotificationPhase1Test extends TestCase
{
    use DatabaseTransactions;

    /* ── 1.3 formatMoney (session-independent) ─────────────────────── */

    public function test_format_money_is_session_independent_and_currency_correct(): void
    {
        // Seed currencies (mbs_test isn't seeded) so the format has data to use.
        DB::table('multi_currencies')->updateOrInsert(['currency_code' => 'INR'],
            ['currency_icon' => '₹', 'currency_position' => 'before_price', 'currency_rate' => 1, 'is_default' => 'yes']);
        DB::table('multi_currencies')->updateOrInsert(['currency_code' => 'USD'],
            ['currency_icon' => '$', 'currency_position' => 'before_price', 'currency_rate' => 1, 'is_default' => 'no']);
        Cache::forget('allCurrencies');

        // Default currency (INR) — no session required.
        $this->assertSame('₹500.00', formatMoney(500));
        $this->assertSame('₹1,250.50', formatMoney(1250.5));
        // Explicit currency code resolves that currency's symbol.
        $this->assertSame('$500.00', formatMoney(500, 'USD'));
        // Unknown code falls back to the default, never crashes.
        $this->assertSame('₹10.00', formatMoney(10, 'ZZZ'));

        Cache::forget('allCurrencies');
    }

    /* ── 1.6 / 1.7 strip unresolved tokens ─────────────────────────── */

    public function test_strip_unresolved_tokens_removes_leftover_placeholders(): void
    {
        $this->assertSame('Hi , amount  ok', strip_unresolved_tokens('Hi {{user_name}}, amount {{amount}} ok'));
        $this->assertSame('plain text', strip_unresolved_tokens('plain text'));
        // Whitespace-tolerant.
        $this->assertSame('', strip_unresolved_tokens('{{ spaced_token }}'));
    }

    public function test_notification_substitute_strips_unsupplied_tokens(): void
    {
        $user = User::factory()->create(['name' => 'Asha']);
        $notif = new \App\Notifications\PasswordChangedToUser();

        $ref = new \ReflectionMethod(\App\Notifications\InAppNotification::class, 'substitute');
        $ref->setAccessible(true);

        // Supplied token replaced; unsupplied token stripped (not leaked raw).
        $out = $ref->invoke($notif, 'Hi {{user_name}}, code {{unknown_token}} end', ['user_name' => 'Asha']);
        $this->assertStringContainsString('Hi Asha', $out);
        $this->assertStringNotContainsString('{{unknown_token}}', $out);
        $this->assertStringNotContainsString('{{', $out);
    }

    /* ── 1.5 order-status enum leak ────────────────────────────────── */

    public function test_order_status_fallback_does_not_leak_raw_enums(): void
    {
        $order = (new Order())->forceFill([
            'id' => 999, 'invoice_id' => 'INV-1', 'status' => 'processing', 'payment_status' => 'pending',
            'primary_coach_id' => null,
        ]);

        $n = new OrderStatusChangedToStudent($order);
        $ref = new \ReflectionProperty($n, 'body');
        $ref->setAccessible(true);
        $body = (string) $ref->getValue($n);

        $this->assertStringNotContainsString('processing', $body, 'raw status enum must not leak');
        $this->assertStringNotContainsString('Status:', $body);
        $this->assertNotEmpty($body);
    }

    /* ── 1.1 batch live-class email name-bleed (the headline bug) ──── */

    public function test_live_class_batch_email_personalises_each_recipient(): void
    {
        Cache::put('setting', (object) [
            'is_queable' => 'inactive', 'mail_host' => 'smtp', 'mail_port' => 587,
            'mail_encryption' => 'tls', 'mail_username' => 'u', 'mail_password' => 'p',
            'mail_sender_email' => 'a@b.com', 'mail_sender_name' => 'X',
        ]);
        DB::table('email_templates')->updateOrInsert(
            ['name' => 'live_class_mail'],
            ['subject' => 'Live class', 'message' => 'Hi {{user_name}}, join {{course}}.', 'updated_at' => now(), 'created_at' => now()]
        );

        Mail::fake();

        $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $bob   = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.test']);

        (new MailSenderService())->sendLiveClassNotificationMailTrait(
            collect([$alice, $bob]),
            (object) ['course' => 'Yoga', 'lesson' => 'L1', 'start_time' => '10:00', 'join_url' => 'https://x']
        );

        Mail::assertSent(\App\Mail\sendLiveClassMail::class, 2);
        // Alice's email greets Alice; Bob's greets Bob (was: both greeted "Alice").
        Mail::assertSent(\App\Mail\sendLiveClassMail::class, fn ($m) => $m->hasTo('alice@example.test') && str_contains($m->messageTemplate, 'Hi Alice'));
        Mail::assertSent(\App\Mail\sendLiveClassMail::class, fn ($m) => $m->hasTo('bob@example.test') && str_contains($m->messageTemplate, 'Hi Bob') && ! str_contains($m->messageTemplate, 'Alice'));

        Cache::forget('setting');
    }
}
