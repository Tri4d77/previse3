<?php

namespace App\Mail\Concerns;

/**
 * A Mailable->render() a beállított `$this->locale` kontextusában fut.
 * A Laravel alap Mailable csak send() híváskor alkalmazza a locale-t,
 * direkt render() esetén nem — ez a trait bepótolja.
 *
 * Szükséges a ShouldQueue mailable-ök tesztelhetőségéhez ($mail->render())
 * és a Notification->toMail-ben visszaadott Mailable renderjéhez is.
 */
trait RendersInLocale
{
    public function render()
    {
        $locale = $this->locale ?: config('app.locale');
        $translator = app('translator');
        $original = $translator->getLocale();

        $translator->setLocale($locale);
        try {
            return parent::render();
        } finally {
            $translator->setLocale($original);
        }
    }
}
