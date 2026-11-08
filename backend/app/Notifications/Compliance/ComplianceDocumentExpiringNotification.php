<?php

declare(strict_types=1);

namespace App\Notifications\Compliance;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Reminds a host that a verified document is about to expire, or has expired. Queued. */
class ComplianceDocumentExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $document,
        public readonly string $subjectName,
        public readonly CarbonInterface $expiresOn,
        public readonly int $daysLeft,
        public readonly string $url,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->expiresOn->format('j F Y');
        $mail = (new MailMessage)->greeting('Hello ' . $notifiable->name . ',');
        if ($this->daysLeft <= 0) {
            $mail->error()->subject("Expired: {$this->document}")
                ->line("{$this->document} for {$this->subjectName} expired on {$date}.")
                ->line('Until you upload the renewed document, this can stop events from being published.');
        } else {
            $mail->subject("Expires in {$this->daysLeft} days: {$this->document}")
                ->line("{$this->document} for {$this->subjectName} expires on {$date}.")
                ->line('Renew it and upload the new document before then.');
        }

        return $mail->action('Upload the renewed document', $this->url);
    }

    public function toArray(object $notifiable): array
    {
        return ['document' => $this->document, 'expires_on' => $this->expiresOn->toDateString(), 'days_left' => $this->daysLeft];
    }
}
