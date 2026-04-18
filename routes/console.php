<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nrs:poll-confirmations')->everyTenMinutes()->withoutOverlapping();
Schedule::command('invoices:recover-stuck')->everyFifteenMinutes()->withoutOverlapping();
