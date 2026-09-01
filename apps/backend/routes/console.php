<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:release-expired-reservations')->everyFiveMinutes()->withoutOverlapping();

// Runs often; each pair decides for itself whether enough time has passed,
// based on its own frequency_minutes (ExchangeRateSetting::isDueForRefresh).
Schedule::command('exchange-rates:refresh')->everyFiveMinutes()->withoutOverlapping();
