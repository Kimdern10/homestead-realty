<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Daily emails. On shared hosting add ONE cron job that runs every minute:
 *   * * * * * cd /home/USER/homestead-realty && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('alerts:send')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('leads:remind')->weekdays()->at('07:30')->withoutOverlapping();
