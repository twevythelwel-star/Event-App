<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketIssuedOrUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $ticketData;
    public bool $isUpdate;
    public string $idempotencyKey;

    public function __construct(array $ticketData, bool $isUpdate = false)
    {
        $this->ticketData = $ticketData;
        $this->isUpdate = $isUpdate;
        $action = $isUpdate ? 'update_' . ($ticketData['version'] ?? '2') : 'issued';
        $this->idempotencyKey = 'idemp_tck_' . $action . '_' . ($ticketData['ticketCode'] ?? uniqid());
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (!empty($notifiable->email ?? $this->ticketData['customerEmail'] ?? null)) {
            $channels[] = 'mail';
        }
        if (!empty($notifiable->phone ?? $this->ticketData['customerPhone'] ?? null)) {
            $channels[] = 'sms';
        }
        return $channels ?: ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->ticketData['customerName'] ?? 'Valued Guest';
        $ticketCode = $this->ticketData['ticketCode'] ?? 'TCK-SAMPLE';
        $eventName = $this->ticketData['eventName'] ?? 'Community Event';
        $eventDate = $this->ticketData['eventDate'] ?? 'Event Day';
        $itemsList = $this->ticketData['itemsSummary'] ?? 'Meal order items';
        $ticketUrl = $this->ticketData['ticketUrl'] ?? url('/tickets/' . $ticketCode);

        $subject = $this->isUpdate 
            ? "Ticket Updated: #{$ticketCode} - {$eventName}"
            : "Digital Ticket Issued: #{$ticketCode} - {$eventName}";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$customerName},")
            ->line($this->isUpdate 
                ? "Your digital ticket for **{$eventName}** has been **updated** (e.g. menu adjustments or portion modifications)."
                : "Your official digital ticket for **{$eventName}** has been successfully **issued**!")
            ->line("**Ticket Code:** `{$ticketCode}`")
            ->line("**Date & Location:** {$eventDate}")
            ->line("**Included Portions / Items:**")
            ->line("_{$itemsList}_")
            ->action('Access Live QR Ticket', $ticketUrl)
            ->line('Save this link on your smartphone or bookmark the page. You will scan this QR code at food distribution.')
            ->salutation('Warm regards, ' . $eventName . ' Event Crew');

        return $mail;
    }

    public function toSms(object $notifiable): array
    {
        $ticketCode = $this->ticketData['ticketCode'] ?? 'TCK-SAMPLE';
        $eventName = $this->ticketData['eventName'] ?? 'Event';
        $ticketUrl = $this->ticketData['ticketUrl'] ?? url('/tickets/' . $ticketCode);
        $actionWord = $this->isUpdate ? 'updated' : 'ready';

        return [
            'to' => $notifiable->phone ?? $this->ticketData['customerPhone'],
            'message' => "Your ticket #{$ticketCode} for {$eventName} is {$actionWord}! Show this QR code at food pickup: {$ticketUrl}",
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
