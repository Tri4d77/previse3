<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Sanctum használja a saját bővített PersonalAccessToken modelünket
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Globális jelszó-szabályzat: min. 10 karakter, kis+nagybetű, szám.
        // Ahol a `Password::defaults()` szerepel validatoroknál, automatikusan
        // ezeket a szabályokat alkalmazza.
        Password::defaults(function () {
            $rule = Password::min(10)->mixedCase()->numbers();

            // Éles környezetben HIBP ellenőrzés is (közismert/kiszivárgott jelszavak tiltása)
            return $this->app->environment('production')
                ? $rule->uncompromised()
                : $rule;
        });
    }
}
