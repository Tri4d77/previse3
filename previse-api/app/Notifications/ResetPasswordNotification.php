<?php

namespace App\Notifications;

use App\Mail\ResetPasswordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Jelszó-visszaállítási email notification.
 *
 * A Laravel alapértelmezett `Illuminate\Auth\Notifications\ResetPassword`-ot
 * váltja le; a tényleges email építést a ResetPasswordMail Mailable végzi
 * (Mailable->locale() kezeli a HU/EN fordítást korrekt módon).
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        $resetUrl = rtrim(config('app.frontend_url'), '/')
            . '/reset-password?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);
        $locale = $this->resolveLocale($notifiable);

        return new ResetPasswordMail(
            recipientEmail: $notifiable->getEmailForPasswordReset(),
            recipientName: $notifiable->name ?? '',
            resetUrl: $resetUrl,
            expiresInMinutes: $expiresInMinutes,
            locale: $locale,
        );
    }

    /**
     * A notifiable beállított locale-ja, ha van, egyébként az app locale.
     */
    private function resolveLocale(object $notifiable): string
    {
        if ($notifiable instanceof HasLocalePreference) {
            $pref = $notifiable->preferredLocale();
            if ($pref) return $pref;
        }

        return $notifiable->settings?->locale ?? app()->getLocale();
    }
}
