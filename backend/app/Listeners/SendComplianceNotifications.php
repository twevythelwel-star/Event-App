<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Compliance\ComplianceEvidenceReviewed;
use App\Events\Compliance\IdentityVerificationReviewed;
use App\Events\Compliance\PublishingSuspensionChanged;
use App\Models\Event;
use App\Models\User;
use App\Notifications\Compliance\ComplianceDocumentReviewedNotification;
use App\Notifications\Compliance\HostVerificationReviewedNotification;
use App\Notifications\Compliance\PublishingSuspensionNotification;
use App\Services\Compliance\EventPublicationGates;
use Illuminate\Events\Dispatcher;

/**
 * Tells people about review decisions that affect them. The notifications themselves are queued
 * (the "notifications" queue), so a slow mail provider never delays the administrator's action.
 */
class SendComplianceNotifications
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            IdentityVerificationReviewed::class => 'identityReviewed',
            ComplianceEvidenceReviewed::class => 'evidenceReviewed',
            PublishingSuspensionChanged::class => 'suspensionChanged',
        ];
    }

    public function identityReviewed(IdentityVerificationReviewed $event): void
    {
        // Starting a review is not news to tell the host by email; the decision is.
        if ($event->verification->status !== 'under_review' && $event->verification->user) {
            $event->verification->user->notify(new HostVerificationReviewedNotification($event->verification));
        }
    }

    public function evidenceReviewed(ComplianceEvidenceReviewed $event): void
    {
        $evidence = $event->evidence;
        if (in_array($evidence->status, ['verified', 'rejected'], true) && $evidence->submitter) {
            $evidence->submitter->notify(new ComplianceDocumentReviewedNotification($evidence));
        }
    }

    public function suspensionChanged(PublishingSuspensionChanged $event): void
    {
        $notification = new PublishingSuspensionNotification($event->suspended, $event->reason, $event->event?->title);
        foreach ($this->recipients($event->event, $event->host) as $user) {
            $user->notify($notification);
        }
    }

    /** @return list<User> the host, or the event's hosts */
    private function recipients(?Event $event, ?User $host): array
    {
        if ($host) {
            return [$host];
        }

        return $event ? app(EventPublicationGates::class)->hosts($event) : [];
    }
}
