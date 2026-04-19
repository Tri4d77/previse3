<?php

namespace App\Mail;

use App\Mail\Concerns\RendersInLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email-cím változtatás megerősítő levél az ÚJ címre.
 */
class EmailChangeConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, RendersInLocale, SerializesModels;

    public function __construct(
        public string $userName,
        public string $oldEmail,
        public string $newEmail,
        public string $confirmUrl,
        public int $expiresInMinutes,
        ?string $locale = null,
    ) {
        $this->locale($locale ?: config('app.locale'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->newEmail, $this->userName)],
            subject: __('mail.email_change_confirm.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-change-confirm',
            with: [
                'userName' => $this->userName,
                'oldEmail' => $this->oldEmail,
                'newEmail' => $this->newEmail,
                'confirmUrl' => $this->confirmUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}
