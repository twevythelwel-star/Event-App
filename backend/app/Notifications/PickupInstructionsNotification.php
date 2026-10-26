<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PickupInstructionsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $pickupData;
    public string $idempotencyKey;

    public function __construct(array $pickupData)
    {
        $this->pickupData = $pickupData;
        $this->idempotencyKey = 'idemp_pickup_instr_' . ($pickupData['orderNumber'] ?? uniqid());
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (!empty($notifiable->email ?? $this->pickupData['customerEmail'] ?? null)) {
            $channels[] = 'mail';
        }
        if (!empty($notifiable->phone ?? $this->pickupData['customerPhone'] ?? null)) {
            $channels[] = 'sms';
        }
        return $channels ?: ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->pickupData['customerName'] ?? 'Valued Patron';
        $orderNumber = $this->pickupData['orderNumber'] ?? 'N/A';
        $stationName = $this->pickupData['stationName'] ?? 'Station A - Hot Fish & Sides';
        $pickupCode = $this->pickupData['ticketCode'] ?? 'TCK-SAMPLE';
        $instructions = $this->pickupData['instructions'] ?? 'Proceed to the designated station and show your QR code.';
        $ticketUrl = $this->pickupData['ticketUrl'] ?? url('/tickets/' . $pickupCode);

        return (new MailMessage)
            ->subject("Pickup Ready: Order #{$orderNumber} is Ready for Collection!")
            ->greeting("Hello {$customerName},")
            ->line("Your food order **#{$orderNumber}** is freshly prepared and ready for pickup!")
            ->line("**Assigned Collection Counter:** `{$stationName}`")
            ->line("**Ticket Reference:** `{$pickupCode}`")
            ->line("**Special Instructions:** {$instructions}")
            ->line("To prevent food delays, please proceed directly to your assigned counter with your ticket displayed on your smartphone.")
            ->action('Open Food Pickup QR Code', $ticketUrl)
            ->line('Our staff will scan your QR code and hand over your items portion-by-portion.')
            ->salutation('Warm regards, Kitchen Operations');
    }

    public function toSms(object $notifiable): array
    {
        $orderNumber = $this->pickupData['orderNumber'] ?? 'N/A';
        $stationName = $this->pickupData['stationName'] ?? 'Station A';
        $ticketUrl = $this->pickupData['ticketUrl'] ?? url('/orders/' . $orderNumber);

        return [
            'to' => $notifiable->phone ?? $this->pickupData['customerPhone'],
            'message' => "Order #{$orderNumber} is READY! Proceed to {$stationName}. Present your QR ticket to collect: {$ticketUrl}",
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
