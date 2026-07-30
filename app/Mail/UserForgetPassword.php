<?php

namespace App\Mail;

use App\Mail\Concerns\BrandedAuthMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserForgetPassword extends Mailable
{
    use Queueable, SerializesModels, BrandedAuthMail;

    public $mail_subject;

    public $mail_message;

    public $from_user;

    public $mail_template_path;

    public function __construct($mail_message, $mail_subject, $from_user, $mail_template_path)
    {
        $this->mail_subject = $mail_subject;
        $this->mail_message = $mail_message;
        $this->from_user = $from_user;
        $this->mail_template_path = $mail_template_path;
        $this->resolveBrandFor($from_user);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $from = ($this->brandIsCoach && $this->brandName && config('mail.from.address'))
            ? new Address(config('mail.from.address'), $this->brandName)
            : null;

        return new Envelope(
            subject: $this->mail_subject,
            from: $from,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->mail_template_path.'.forget_password_mail',
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
