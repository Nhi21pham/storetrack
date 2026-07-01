<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invitations:expire')->hourly();
Schedule::command('exports:cleanup')->everyFiveMinutes();
Schedule::command('imports:cleanup')->hourly();

Schedule::command('demo:restore')->everyFiveMinutes();

Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:run --only-db')->hourlyAt(30)->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('03:00');
Schedule::command('backup:monitor')->dailyAt('03:30');

Schedule::command('backup:verify')
    ->hourlyAt(40)
    ->onFailure(function () {
        Log::error('[backup:verify] Latest backup failed content verification — a backup may be silently empty.');
    });
