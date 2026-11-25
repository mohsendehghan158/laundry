<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Production boards flag orders the moment they pass their due time.
Schedule::command('production:overdue-alerts')->everyMinute()->withoutOverlapping();
