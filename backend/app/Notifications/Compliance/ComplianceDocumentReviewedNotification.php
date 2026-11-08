<?php

declare(strict_types=1);

namespace App\Notifications\Compliance;

use App\Models\EventComplianceEvidence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the person who submitted a permit or licence that EventBite verified or rejected it. Queued. */
class ComplianceDocumentReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly EventComplianceEvidence $evidence)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->evidence->event?->title ?? 'your event';
        $mail = (new MailMessage)->greeting('Hello ' . $notifiable->name . ',');
        if ($this->evidence->status === 'verified') {
            $mail->success()->subject("Verified: {$this->evidence->item_title}")
                ->line("EventBite verified the permit or licence you submitted for {$event}.");
            if ($this->evidence->expires_on) {
                $mail->line('It expires on ' . $this->evidence->expires_on->format('j F Y') . '. We will remind you before then.');
            }
        } else {
            $mail->error()->subject("Action needed: {$this->evidence->item_title}")
                ->line("EventBite could not accept the permit or licence you submitted for {$event}.")
                ->line('Reason: ' . ($this->evidence->review_comment ?: 'see the requirements checklist'))
                ->line('Submit a corrected document. The event cannot be published until this requirement is resolved.');
        }

        return $mail->action('Open the requirements checklist', route('events.requirements', $this->evidence->event_id));
    }

    public function toArray(object $notifiable): array
    {
        return ['evidence_id' => $this->evidence->id, 'status' => $this->evidence->status];
    }
}
