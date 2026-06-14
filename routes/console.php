<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:recover-stuck')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('audit:prune')->daily()->withoutOverlapping();
Schedule::command('ops:queue-health')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('invoices:mark-overdue')->hourly()->withoutOverlapping();
Schedule::command('telescope:prune --hours=24')->daily();
