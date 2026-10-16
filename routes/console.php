<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clock out staff who closed the browser without logging out.
Schedule::command('app:close-stale-presence')->everyFiveMinutes()->withoutOverlapping();
