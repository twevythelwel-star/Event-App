<?php

declare(strict_types=1);

namespace App\Notifications\Compliance;

use App\Enums\HostVerificationStatus;
use App\Models\HostIdentityVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a host their government ID verification changed status, and what to do next. Queued. */
class HostVerificationReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly HostIdentityVerification $verification)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->verification->statusEnum();
        $reason = trim((string) $this->verification->rejection_or_info_reason);
        $mail = (new MailMessage)
            ->subject('Your host identity verification: ' . $status->label())
            ->greeting('Hello ' . $notifiable->name . ',');

        match ($status) {
            HostVerificationStatus::APPROVED => $mail->success()->line('EventBite has approved your government ID. Your events can now be published once their other compliance checks pass.'),
            HostVerificationStatus::UNDER_REVIEW => $mail->line('EventBite has started reviewing your government ID. We will email you with the result.'),
            HostVerificationStatus::ADDITIONAL_INFORMATION_REQUIRED, HostVerificationStatus::REVERIFICATION_REQUIRED
                => $mail->line('EventBite needs a new or clearer copy of your government ID.')->line('Reason: ' . ($reason ?: 'see your compliance page')),
            HostVerificationStatus::REJECTED => $mail->error()->line('EventBite could not accept your government ID.')->line('Reason: ' . ($reason ?: 'see your compliance page')),
            HostVerificationStatus::SUSPENDED => $mail->error()->line('Your host identity verification has been suspended.')->line('Reason: ' . ($reason ?: 'see your compliance page')),
            default => $mail->line('Your verification status is now ' . $status->label() . '.'),
        };

        return $mail->action('Open your compliance page', route('host.compliance') . '#identity');
    }

    public function toArray(object $notifiable): array
    {
        return ['verification_id' => $this->verification->id, 'status' => $this->verification->status];
    }
}
