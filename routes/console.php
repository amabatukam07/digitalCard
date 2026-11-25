<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nexosupri:expire-pix-charges')->everyFiveMinutes();
Schedule::command('nexosupri:reconcile-ledgers')->dailyAt('03:00');
