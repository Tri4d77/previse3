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
 * Általános biztonsági értesítés email (jelszó módosítva, 2FA on/off, új eszköz belépés, stb.).
 * A típus-specifikus szöveg a lang/mail.php security.{event} kulcsból jön.
 */
class SecurityAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param string $eventKey  lang/mail.php security.{eventKey} kulcs (pl. "password_changed")
     * @param array  $details   extra kulcs-érték lista a sablon táblázatához (pl. ['Időpont' => '...', 'IP' => '...'])
     */
    public function __construct(
        public string $recipientEmail,
        public string $userName,
        public string $eventKey,
        public array $details = [],
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
            subject: __("mail.security.{$this->eventKey}.subject", ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.security-alert',
            with: [
                'subject' => __("mail.security.{$this->eventKey}.subject", ['app' => config('app.name')]),
                'heading' => __("mail.security.{$this->eventKey}.heading"),
                'intro' => __("mail.security.{$this->eventKey}.intro"),
                'details' => $this->details,
            ],
        );
    }
}
