<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
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
    }
}
