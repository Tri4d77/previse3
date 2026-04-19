<?php

namespace App\Mail;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meghívó email egy tagság (membership) számára.
 *
 * Két ág:
 *  - Új user (user.password === null): „Állítsd be a jelszót"
 *  - Létező user: „Erősítsd meg a tagságot"
 *
 * A locale-t a címzett user-specifikus beállításából veszi (ha van),
 * egyébként az app.locale-ból.
 */
class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Membership $membership,
        public string $invitationUrl,
        public ?string $inviterName = null,
        public int $expiresInDays = 7,
    ) {
        // Locale a címzett beállításából
        $userLocale = $membership->user->settings?->locale;
        if ($userLocale) {
            $this->locale($userLocale);
        }
    }

    public function envelope(): Envelope
    {
        $this->membership->loadMissing(['user', 'organization', 'role']);

        return new Envelope(
            to: [new Address($this->membership->user->email, $this->membership->user->name ?? '')],
            subject: __('mail.invitation.subject', [
                'app' => config('app.name'),
                'organization' => $this->membership->organization->name,
            ]),
        );
    }

    public function content(): Content
    {
        $this->membership->loadMissing(['user', 'organization', 'role']);

        return new Content(
            view: 'emails.invitation',
            with: [
                'userName' => $this->membership->user->name ?: $this->membership->user->email,
                'inviterName' => $this->inviterName ?? __('mail.invitation.default_inviter', [], null, 'Previse'),
                'organizationName' => $this->membership->organization->name,
                'roleName' => $this->membership->role->name,
                'invitationUrl' => $this->invitationUrl,
                'isNewUser' => is_null($this->membership->user->password),
                'expiresInDays' => $this->expiresInDays,
            ],
        );
    }
}
