<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\HostVerificationStatus;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Notifications\Compliance\ComplianceDocumentExpiringNotification;
use App\Services\Compliance\EventPublicationGates;
use Illuminate\Console\Command;

/**
 * Daily: reminds hosts about verified permits, licences and approved government IDs that expire in 30 or 7 days,
 * or expired yesterday. Each document is reminded once on each of those days; the emails are queued.
 */
class NotifyExpiringComplianceDocuments extends Command
{
    protected $signature = 'compliance:notify-expiring';

    protected $description = 'Queue reminders for compliance documents that are about to expire or have just expired';

    /** Days before expiry to remind on; -1 is the day after it expired. */
    public const REMIND_ON_DAYS = [30, 7, -1];

    public function handle(EventPublicationGates $gates): int
    {
        $sent = 0;
        foreach (self::REMIND_ON_DAYS as $days) {
            $date = now()->startOfDay()->addDays($days)->toDateString();

            EventComplianceEvidence::with('event')->where('status', 'verified')->whereDate('expires_on', $date)->get()
                ->each(function (EventComplianceEvidence $evidence) use ($gates, $days, &$sent) {
                    if (!$evidence->event) {
                        return;
                    }
                    $notification = new ComplianceDocumentExpiringNotification($evidence->item_title, $evidence->event->title,
                        $evidence->expires_on, max(0, $days), route('events.requirements', $evidence->event_id));
                    foreach ($gates->hosts($evidence->event) as $host) {
                        $host->notify($notification);
                        $sent++;
                    }
                });

            HostIdentityVerification::with('user')->where('status', HostVerificationStatus::APPROVED->value)->whereDate('document_expires_at', $date)->get()
                ->each(function (HostIdentityVerification $verification) use ($days, &$sent) {
                    if ($verification->user) {
                        $verification->user->notify(new ComplianceDocumentExpiringNotification('Your government ID', 'your host account',
                            $verification->document_expires_at, max(0, $days), route('host.compliance') . '#identity'));
                        $sent++;
                    }
                });
        }

        $this->info("Queued {$sent} expiry " . str('reminder')->plural($sent) . '.');

        return self::SUCCESS;
    }
}
