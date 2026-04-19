<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tájékoztató email a RÉGI címre: valaki email-változtatást kezdeményezett.
 */
class EmailChangeNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public string $userName,
        public string $newEmail,
        public string $requestedAt,
        public ?string $ipAddress = null,
        ?string $locale = null,
    ) {
        if ($locale) {
            $this->locale($locale);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->recipientEmail, $this->userName)],
            subject: __('mail.email_change_notice.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-change-notice',
            with: [
                'userName' => $this->userName,
                'newEmail' => $this->newEmail,
                'requestedAt' => $this->requestedAt,
                'ipAddress' => $this->ipAddress,
            ],
        );
    }
}
