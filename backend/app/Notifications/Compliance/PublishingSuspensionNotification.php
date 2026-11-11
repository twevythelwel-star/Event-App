<?php

declare(strict_types=1);

namespace App\Notifications\Compliance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a host that an event, or their publishing privileges, was suspended or reinstated. Queued. */
class PublishingSuspensionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly bool $suspended,
        public readonly string $reason,
        public readonly ?string $eventTitle = null,
    ) {
        $this->onQueue(config('queue.names.notifications'));
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $what = $this->eventTitle ? "your event \"{$this->eventTitle}\"" : 'your publishing privileges';
        $mail = (new MailMessage)->greeting('Hello ' . $notifiable->name . ',');
        if ($this->suspended) {
            $mail->error()->subject($this->eventTitle ? "Event suspended: {$this->eventTitle}" : 'Your publishing privileges are suspended')
                ->line("EventBite has suspended {$what} on compliance grounds.")
                ->line('Reason: ' . $this->reason);
        } else {
            $mail->success()->subject($this->eventTitle ? "Event reinstated: {$this->eventTitle}" : 'Your publishing privileges are restored')
                ->line("EventBite has lifted the suspension of {$what}.")
                ->line('Every compliance check still applies when you publish.');
        }

        return $mail->action('Open your compliance page', route('host.compliance'));
    }

    public function toArray(object $notifiable): array
    {
        return ['suspended' => $this->suspended, 'event' => $this->eventTitle];
    }
}
