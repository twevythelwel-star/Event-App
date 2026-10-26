<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCollectionCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $collectionData;
    public string $idempotencyKey;

    public function __construct(array $collectionData)
    {
        $this->collectionData = $collectionData;
        $this->idempotencyKey = 'idemp_collection_done_' . ($collectionData['orderNumber'] ?? uniqid());
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (!empty($notifiable->email ?? $this->collectionData['customerEmail'] ?? null)) {
            $channels[] = 'mail';
        }
        if (!empty($notifiable->phone ?? $this->collectionData['customerPhone'] ?? null)) {
            $channels[] = 'sms';
        }
        return $channels ?: ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->collectionData['customerName'] ?? 'Valued Patron';
        $orderNumber = $this->collectionData['orderNumber'] ?? 'N/A';
        $completedAt = $this->collectionData['completedAt'] ?? date('M d, Y h:i A');
        $stationStaff = $this->collectionData['stationStaff'] ?? 'Staff Team';
        $itemsSummary = $this->collectionData['itemsSummary'] ?? 'All ordered items';
        $trackingUrl = $this->collectionData['trackingUrl'] ?? url('/orders/' . $orderNumber);

        return (new MailMessage)
            ->subject("Order Completed: Order #{$orderNumber} Collected - Thank You!")
            ->greeting("Hello {$customerName},")
            ->line("Your food order **#{$orderNumber}** has been **successfully collected** and fulfilled.")
            ->line("**Completed At:** {$completedAt}")
            ->line("**Handed Over By:** {$stationStaff}")
            ->line("**Fulfillment Audit:** All ordered portions were scanned and marked collected with zero items remaining.")
            ->line("**Items:** {$itemsSummary}")
            ->action('View Final Order Summary', $trackingUrl)
            ->line('We hope you thoroughly enjoy every bite! Thank you for supporting our community event.')
            ->salutation('Warm regards, The Culinary Team');
    }

    public function toSms(object $notifiable): array
    {
        $orderNumber = $this->collectionData['orderNumber'] ?? 'N/A';

        return [
            'to' => $notifiable->phone ?? $this->collectionData['customerPhone'],
            'message' => "Order #{$orderNumber} collection completed! All meal items have been handed over. Enjoy your meal and thank you for supporting the event!",
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
