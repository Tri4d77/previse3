<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks (Laravel scheduler)
|--------------------------------------------------------------------------
|
| Élesben napi 1x cron-ból: `* * * * * php artisan schedule:run >/dev/null 2>&1`
| (CPanel-en a Cron Jobs felületen percenkénti futtatás).
|
*/

// Lejárt grace-ű fiókok anonimizálása (M7) — minden nap 03:00-kor
Schedule::command('users:finalize-deletions')
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping();

// Régi auth események törlése (M8) — minden nap 03:30-kor
Schedule::command('auth:prune-events')
    ->dailyAt('03:30')
    ->onOneServer()
    ->withoutOverlapping();

// Mail queue worker fut-szabálytól megóvás: ha a queue:work beáll, restart percenként
// (Csak akkor releváns, ha a queue worker nem supervisord-ben fut.)
Schedule::command('queue:work --stop-when-empty --tries=3 --sleep=1 --max-time=50')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();
