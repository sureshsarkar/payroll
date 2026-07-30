<?php

namespace App\Mail;

use App\Mail\Concerns\BrandedAuthMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SocialLoginDefaultPasswordMail extends Mailable
{
    use Queueable, SerializesModels, BrandedAuthMail;

    // Public so Laravel auto-exposes them to the view (emails.password-mail
    // references $user and $password). Private would break the Blade render.
    public function __construct(public User $user, public string $password)
    {
        // White-label: resolve the new user's coach brand (platform fallback).
        $this->resolveBrandFor($user);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $from = ($this->brandIsCoach && $this->brandName)
            ? new Address(config('mail.from.address'), $this->brandName)
            : null;

        return new Envelope(
            from: $from,
            subject: ($this->brandName ? $this->brandName . ' — ' : '') . 'Your account password',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-mail',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
