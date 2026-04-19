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
 * Jelszó-visszaállítási email Mailable.
 *
 * A Notification wrapper helyett direktben Mailable, így a Mailable->locale()
 * korrekt módon kezeli a HU/EN forditást (szemben a MailMessage-del, ahol
 * a render deferred és a locale nem ragad meg).
 */
class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public string $recipientName,
        public string $resetUrl,
        public int $expiresInMinutes,
        ?string $locale = null,
    ) {
        if ($locale) {
            $this->locale($locale);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->recipientEmail, $this->recipientName)],
            subject: __('mail.password_reset.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'userName' => $this->recipientName ?: $this->recipientEmail,
                'resetUrl' => $this->resetUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}
