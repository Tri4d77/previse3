<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A request locale meghatározása, prioritási sorrend:
 *
 *  1. Bejelentkezett user settings.locale (ha van)
 *  2. Accept-Language header első támogatott nyelve (pl. "hu, en;q=0.9")
 *  3. config('app.locale') — fallback (HU)
 *
 * Így a válaszok (validáció-szövegek, auth üzenetek) a user preferált
 * nyelvén jönnek, és a Mail küldéskor is a helyes alapértelmezett locale van
 * érvényben (bár a Mailable-k explicit ->locale()-t használnak a címzett
 * user beállítása alapján).
 */
class SetLocale
{
    private const SUPPORTED = ['hu', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        // Csak a translator locale-t állítjuk, NEM az `app()->setLocale()`-t.
        // Az utóbbi a `config('app.locale')`-t is mutálná, ami szennyezné
        // a Mailable-k fallback logikáját (címzett-specifikus locale kell,
        // nem a request-et küldő user locale-ja).
        app('translator')->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Authenticated user beállítása
        $user = $request->user();
        if ($user && $user->settings?->locale && in_array($user->settings->locale, self::SUPPORTED, true)) {
            return $user->settings->locale;
        }

        // 2. Accept-Language header
        $header = $request->header('Accept-Language');
        if ($header) {
            foreach (explode(',', $header) as $part) {
                $lang = strtolower(trim(explode(';', $part)[0]));
                $primary = substr($lang, 0, 2);
                if (in_array($primary, self::SUPPORTED, true)) {
                    return $primary;
                }
            }
        }

        // 3. App default
        return config('app.locale', 'hu');
    }
}
