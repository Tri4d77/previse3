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
 * Általános biztonsági értesítés email (jelszó módosítva, 2FA on/off, új eszköz belépés, stb.).
 * A típus-specifikus szöveg a lang/mail.php security.{event} kulcsból jön.
 */
class SecurityAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, RendersInLocale, SerializesModels;

    /**
     * @param string $eventKey  lang/mail.php security.{eventKey} kulcs (pl. "password_changed")
     * @param array  $details   extra kulcs-érték lista a sablon táblázatához (pl. ['Időpont' => '...', 'IP' => '...'])
     */
    /**
     * @param array $introReplacements   a lang file intro stringjében lévő :placeholder-ek behelyettesítése
     *                                   (pl. ['organization' => 'XY Kft.', 'admin_name' => 'Kovács János'])
     * @param array $details             kulcs-érték pár lista a sablon táblázatához (kulcsok SZÖVEGESEN, nem kulcs-nevek)
     */
    public function __construct(
        public string $recipientEmail,
        public string $userName,
        public string $eventKey,
        public array $details = [],
        public array $introReplacements = [],
        ?string $locale = null,
    ) {
        $this->locale($locale ?: config('app.locale'));
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
                'intro' => __("mail.security.{$this->eventKey}.intro", $this->introReplacements),
                'details' => $this->details,
            ],
        );
    }
}
