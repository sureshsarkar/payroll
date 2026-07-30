<?php

namespace App\Mail;

use App\Mail\Concerns\BrandedMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DefaultMail extends Mailable
{
    use Queueable, SerializesModels, BrandedMailable;

    public $mailData;
    public $subject;
    public $messageTemplate;

    /**
     * Create a new message instance.
     *
     * White-label: when $mailData carries a 'coach_id', the email is rendered
     * with that coach's brand (logo/name/colour/from-name) with platform
     * fallback. Callers opt in by adding 'coach_id' to the data array — no
     * signature change, fully backward compatible.
     */
    public function __construct($mailData, $messageTemplate)
    {
        $coachId = isset($mailData['coach_id']) ? (int) $mailData['coach_id'] : null;
        $this->mailData = $mailData;
        // Tenant-safe links: rewrite platform host -> coach's verified domain.
        $this->messageTemplate = brandedUrl($messageTemplate, $coachId);
        $this->subject = $this->mailData['subject'];
        $this->resolveBrandForCoach($coachId);
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
            subject: $this->mailData['subject'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.default-mail-template',
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
