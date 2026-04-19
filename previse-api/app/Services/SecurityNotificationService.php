<?php

namespace App\Services;

use App\Mail\SecurityAlertMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Egységes wrapper a biztonsági email-ekhez.
 *
 * A tényleges küldést try/catch-ben végezzük, hogy egy SMTP hiba ne akadályozza
 * meg a fő műveletet (jelszóváltást, 2FA állapot változást, stb).
 */
class SecurityNotificationService
{
    /**
     * Jelszó módosítva.
     */
    public function passwordChanged(User $user, Request $request): void
    {
        $this->send($user, 'password_changed', $this->baseDetails($request));
    }

    /**
     * 2FA bekapcsolva.
     */
    public function twoFactorEnabled(User $user, Request $request): void
    {
        $this->send($user, 'two_factor_enabled', $this->baseDetails($request));
    }

    /**
     * 2FA kikapcsolva.
     */
    public function twoFactorDisabled(User $user, Request $request): void
    {
        $this->send($user, 'two_factor_disabled', $this->baseDetails($request));
    }

    /**
     * Email-cím sikeresen módosítva (az ÚJ címre megy).
     */
    public function emailChanged(User $user, string $oldEmail, Request $request): void
    {
        $details = array_merge($this->baseDetails($request), [
            __('mail.security.labels.time') => now()->toDateTimeString(),
        ]);

        // Küldés a régi címre mégis (tájékoztató). Használjuk a SecurityAlertMail-t de külön email-lel.
        $this->sendToEmail($oldEmail, $user, 'email_changed', $details);
    }

    /**
     * Új eszközről bejelentkezés (IP+UA korábban nem látott).
     */
    public function newDeviceLogin(User $user, Request $request): void
    {
        $this->send($user, 'new_device_login', $this->baseDetails($request));
    }

    /**
     * Fiók-törlés megkezdve (30 napos grace period).
     */
    public function accountDeletionScheduled(User $user, ?Request $request = null): void
    {
        $details = $request ? $this->baseDetails($request) : [
            __('mail.security.labels.time') => now()->toDateTimeString(),
        ];
        $this->send($user, 'account_deletion_scheduled', $details);
    }

    /**
     * Fiók-törlés visszavonva.
     */
    public function accountDeletionCancelled(User $user, ?Request $request = null): void
    {
        $details = $request ? $this->baseDetails($request) : [
            __('mail.security.labels.time') => now()->toDateTimeString(),
        ];
        $this->send($user, 'account_deletion_cancelled', $details);
    }

    /**
     * Értesíti egy szervezet többi tagját, hogy az utolsó admin távozott.
     * A $otherMembers collection $member->user objektumokat tartalmaz.
     */
    public function adminLeftOrganization(\Illuminate\Support\Collection $otherMembers, string $orgName, string $departedAdminName): void
    {
        foreach ($otherMembers as $member) {
            $user = $member->user ?? null;
            if (! $user) continue;

            try {
                $locale = $user->settings?->locale ?? app()->getLocale();
                Mail::send(new \App\Mail\SecurityAlertMail(
                    recipientEmail: $user->email,
                    userName: $user->name ?? '',
                    eventKey: 'admin_left_organization',
                    details: [],
                    introReplacements: [
                        'organization' => $orgName,
                        'admin_name' => $departedAdminName,
                    ],
                    locale: $locale,
                ));
            } catch (\Throwable $e) {
                Log::error('admin_left_organization email failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Megvolt-e már ez az (IP, user_agent) páros? A user korábbi tokenjei alapján.
     * A jelenleg kiállított új tokent NEM vesszük számba (azt a login éppen most hozta létre).
     */
    public function isKnownDevice(User $user, Request $request, int $exceptTokenId): bool
    {
        $ua = (string) $request->userAgent();
        $ip = (string) $request->ip();

        return $user->tokens()
            ->where('id', '!=', $exceptTokenId)
            ->where('ip_address', $ip)
            ->where('user_agent', $ua)
            ->exists();
    }

    /**
     * Küld egy eseményt a user aktuális email-címére.
     */
    private function send(User $user, string $eventKey, array $details): void
    {
        $this->sendToEmail($user->email, $user, $eventKey, $details);
    }

    private function sendToEmail(string $toEmail, User $user, string $eventKey, array $details, array $introReplacements = []): void
    {
        try {
            $locale = $user->settings?->locale ?? app()->getLocale();

            Mail::send(new SecurityAlertMail(
                recipientEmail: $toEmail,
                userName: $user->name ?? '',
                eventKey: $eventKey,
                details: $details,
                introReplacements: $introReplacements,
                locale: $locale,
            ));
        } catch (\Throwable $e) {
            Log::error('Security notification email failed', [
                'user_id' => $user->id,
                'to' => $toEmail,
                'event' => $eventKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Közös részletek (idő, IP, eszköz string).
     */
    private function baseDetails(Request $request): array
    {
        return [
            __('mail.security.labels.time') => now()->toDateTimeString(),
            __('mail.security.labels.ip') => (string) $request->ip(),
            __('mail.security.labels.device') => $this->shortUa((string) $request->userAgent()),
        ];
    }

    private function shortUa(string $ua): string
    {
        if ($ua === '') return '—';
        // Egyszerű humán-olvasható forma
        $browser = 'Böngésző';
        if (preg_match('/Edg\//', $ua)) $browser = 'Edge';
        elseif (preg_match('/Chrome\//', $ua) && ! preg_match('/Edg\//', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Firefox\//', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Safari\//', $ua) && ! preg_match('/Chrome\//', $ua)) $browser = 'Safari';

        $os = '';
        if (preg_match('/Windows/', $ua)) $os = 'Windows';
        elseif (preg_match('/Macintosh/', $ua)) $os = 'macOS';
        elseif (preg_match('/iPhone|iPad/', $ua)) $os = 'iOS';
        elseif (preg_match('/Android/', $ua)) $os = 'Android';
        elseif (preg_match('/Linux/', $ua)) $os = 'Linux';

        return trim($browser . ($os ? " • $os" : ''));
    }
}
