<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Free stock held by unpaid online orders whose reservation window has elapsed.
Schedule::command('sales:expire-reservations')->everyFiveMinutes()->withoutOverlapping();
