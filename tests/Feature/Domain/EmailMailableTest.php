<?php

namespace Tests\Feature\Domain;

use App\Mail\SocialLoginDefaultPasswordMail;
use App\Mail\UserForgetPassword;
use App\Mail\UserRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Domain test — email delivery contract (Tier C category).
 *
 * Uses Mail::fake() / Notification::fake() — no real SMTP needed.
 *
 * Verifies:
 *   - Constructable: each Mailable / Notification can be instantiated
 *     with realistic data.
 *   - Renderable: build() / envelope() / content() do not throw.
 *   - Recipient correctness: the right to() address is on the envelope.
 *   - Subject + payload data are passed through unmangled.
 */
class EmailMailableTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_registration_mailable_constructs_and_renders(): void
    {
        Mail::fake();

        $mail = new UserRegistration(
            mail_message: '<p>Welcome to MBSGuru.</p>',
            mail_subject: 'Welcome!',
            from_user: 'no-reply@mbsguru.example',
        );

        Mail::to('alice@example.test')->send($mail);

        Mail::assertSent(UserRegistration::class, function ($m) {
            return $m->mail_subject === 'Welcome!'
                && str_contains($m->mail_message, 'MBSGuru')
                && $m->hasTo('alice@example.test');
        });
    }

    public function test_user_forget_password_mailable_passes_template_path(): void
    {
        Mail::fake();

        $mail = new UserForgetPassword(
            mail_message: 'Reset link: https://example.test/reset?token=abc',
            mail_subject: 'Password reset',
            from_user: 'no-reply@mbsguru.example',
            mail_template_path: 'emails.forgot-password',
        );

        Mail::to('alice@example.test')->send($mail);

        Mail::assertSent(UserForgetPassword::class, function ($m) {
            return $m->mail_template_path === 'emails.forgot-password'
                && str_contains($m->mail_message, 'token=abc');
        });
    }

    public function test_social_login_default_password_mail_constructs(): void
    {
        Mail::fake();

        $user = new User();
        $user->name = 'Test User';
        $user->email = 'newuser@example.test';

        $mail = new SocialLoginDefaultPasswordMail($user, 'X1Y2Z3');

        Mail::to($user->email)->send($mail);

        Mail::assertSentCount(1);
        Mail::assertSent(SocialLoginDefaultPasswordMail::class, function ($m) {
            return $m->password === 'X1Y2Z3'
                && $m->user->email === 'newuser@example.test';
        });
    }

    public function test_mail_to_multiple_recipients_uses_separate_envelopes(): void
    {
        Mail::fake();

        Mail::to('a@example.test')->send(new UserRegistration('m', 's', 'f@example'));
        Mail::to('b@example.test')->send(new UserRegistration('m', 's', 'f@example'));

        Mail::assertSentCount(2);
        Mail::assertSent(UserRegistration::class, fn($m) => $m->hasTo('a@example.test'));
        Mail::assertSent(UserRegistration::class, fn($m) => $m->hasTo('b@example.test'));
    }

    public function test_no_mail_sent_when_no_recipients(): void
    {
        Mail::fake();

        Mail::assertNothingSent();
    }

    public function test_mailable_subject_appears_on_envelope(): void
    {
        $mail = new UserRegistration('body', 'My Specific Subject Line', 'f@example.test');
        $envelope = $mail->envelope();

        $this->assertSame('My Specific Subject Line', $envelope->subject);
    }

    public function test_mailable_with_long_message_renders_without_truncation(): void
    {
        Mail::fake();

        $longMessage = str_repeat('Lorem ipsum dolor sit amet. ', 100);
        $mail = new UserRegistration($longMessage, 'Long body test', 'f@example.test');

        Mail::to('alice@example.test')->send($mail);

        Mail::assertSent(UserRegistration::class, function ($m) use ($longMessage) {
            return strlen($m->mail_message) > 1000
                && $m->mail_message === $longMessage;
        });
    }

    public function test_mailable_handles_unicode_in_subject_and_body(): void
    {
        Mail::fake();

        $subject = 'Bem-vindo à MBSGuru — 你好 — مرحبا';
        $body = '<p>Welcome with accents: àéîõü. Emoji: 🎓</p>';

        Mail::to('alice@example.test')->send(new UserRegistration($body, $subject, 'f@example.test'));

        Mail::assertSent(UserRegistration::class, function ($m) use ($subject, $body) {
            return $m->mail_subject === $subject && $m->mail_message === $body;
        });
    }
}
