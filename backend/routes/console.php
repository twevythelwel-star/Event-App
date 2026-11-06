<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('queue:prune-failed --hours=336')->dailyAt('03:20')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('commerce:expire-reservations --batch=200')->everyMinute()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('payments:reconcile-stripe --limit=100 --stale-minutes=5')->everyFiveMinutes()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('tickets:offer-waitlist')->everyFiveMinutes()->withoutOverlapping();
