<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $reminderData;
    public string $idempotencyKey;

    public function __construct(array $reminderData)
    {
        $this->reminderData = $reminderData;
        $window = $reminderData['reminderWindow'] ?? '24h';
        $this->idempotencyKey = 'idemp_ev_remind_' . $window . '_' . ($reminderData['orderNumber'] ?? uniqid());
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (!empty($notifiable->email ?? $this->reminderData['customerEmail'] ?? null)) {
            $channels[] = 'mail';
        }
        if (!empty($notifiable->phone ?? $this->reminderData['customerPhone'] ?? null)) {
            $channels[] = 'sms';
        }
        return $channels ?: ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->reminderData['customerName'] ?? 'Valued Guest';
        $eventName = $this->reminderData['eventName'] ?? 'Island Feast';
        $eventDate = $this->reminderData['eventDate'] ?? 'Tomorrow';
        $venueLocation = $this->reminderData['venueLocation'] ?? 'Community Center Ground';
        $pickupHours = $this->reminderData['pickupHours'] ?? '12:00 PM - 6:00 PM';
        $ticketCode = $this->reminderData['ticketCode'] ?? 'TCK-SAMPLE';
        $ticketUrl = $this->reminderData['ticketUrl'] ?? url('/tickets/' . $ticketCode);

        return (new MailMessage)
            ->subject("Event Reminder: {$eventName} is taking place soon!")
            ->greeting("Hello {$customerName},")
            ->line("This is a friendly reminder that **{$eventName}** is happening on **{$eventDate}**.")
            ->line("**Location & Venue:** {$venueLocation}")
            ->line("**Serving Hours:** {$pickupHours}")
            ->line("**Your Ticket Reference:** `{$ticketCode}`")
            ->line("Please make sure to have your smartphone ready with your digital QR ticket, or have a printed copy on hand.")
            ->action('View My Ticket & Directions', $ticketUrl)
            ->line('Our culinary team is fired up and looking forward to welcoming you!')
            ->salutation('Warm regards, ' . $eventName . ' Organizing Committee');
    }

    public function toSms(object $notifiable): array
    {
        $eventName = $this->reminderData['eventName'] ?? 'Event';
        $eventDate = $this->reminderData['eventDate'] ?? 'Tomorrow';
        $pickupHours = $this->reminderData['pickupHours'] ?? '12PM-6PM';
        $ticketCode = $this->reminderData['ticketCode'] ?? 'TCK';
        $ticketUrl = $this->reminderData['ticketUrl'] ?? url('/tickets/' . $ticketCode);

        return [
            'to' => $notifiable->phone ?? $this->reminderData['customerPhone'],
            'message' => "Reminder: {$eventName} is {$eventDate}! Pickup hours: {$pickupHours}. Have ticket #{$ticketCode} ready on your phone: {$ticketUrl}",
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
