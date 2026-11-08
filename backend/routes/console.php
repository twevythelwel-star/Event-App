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

Artisan::command('compliance:purge-expired-id-documents', function () {
    $result = \App\Services\HostIdentityVerificationService::enforceRetentionAndExpirationPolicy();
    $this->info("Purged {$result['purged_files']} expired identity document blob(s); marked {$result['marked_expired']} verification(s) expired.");
})->purpose('Enforce legally reviewed retention and deletion schedule for host government ID documents');

\Illuminate\Support\Facades\Schedule::command('compliance:purge-expired-id-documents')->dailyAt('02:45')->withoutOverlapping();
// Verified compliance-database records past their next review date need checking again (ComplianceReviewWorkflow).
\Illuminate\Support\Facades\Schedule::command('compliance:flag-due-reviews')->dailyAt('03:15')->withoutOverlapping();
