<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class sendLiveClassMail extends Mailable {
    use Queueable, SerializesModels;

    public $subject;

    public $messageTemplate;

    public function __construct($subject, $messageTemplate) {
        $this->subject = $subject;
        $this->messageTemplate = $messageTemplate;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope {
        // Phase 6.2: use the caller-supplied subject (was hardcoded "Live Class Mail").
        return new Envelope(
            subject: $this->subject ?: 'Live Class Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content {
        return new Content(
            view: 'emails.default-mail-template',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array {
        return [];
    }
}
