<?php

namespace App\Notifications;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $orderData;
    public string $idempotencyKey;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $orderData)
    {
        $this->orderData = $orderData;
        // Deterministic idempotency key: prevents duplicate receipts on job retries
        $this->idempotencyKey = 'idemp_order_received_' . ($orderData['orderNumber'] ?? uniqid());
        $this->onQueue('notifications');
    }

    /**
     * Determine notification delivery channels.
     * Starts with email where configured, and includes SMS for mobile orders.
     */
    public function via(object $notifiable): array
    {
        $channels = [];
        if (!empty($notifiable->email ?? $this->orderData['customerEmail'] ?? null)) {
            $channels[] = 'mail';
        }
        if (!empty($notifiable->phone ?? $this->orderData['customerPhone'] ?? null)) {
            $channels[] = 'sms';
        }
        return $channels ?: ['mail'];
    }

    /**
     * Build the rich email notification message.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->orderData['customerName'] ?? 'Valued Customer';
        $orderNumber = $this->orderData['orderNumber'] ?? 'N/A';
        $eventName = $this->orderData['eventName'] ?? 'Island Event';
        $totalFormatted = $this->orderData['totalFormatted'] ?? '$0.00';
        $trackingUrl = $this->orderData['trackingUrl'] ?? url('/orders/' . $orderNumber);

        return (new MailMessage)
            ->subject("Order Received: #{$orderNumber} - {$eventName}")
            ->greeting("Hello {$customerName},")
            ->line("We have received your order for **{$eventName}**.")
            ->line("Your order reference is **#{$orderNumber}** with a subtotal of **{$totalFormatted}**.")
            ->line("Please note: Your order is currently placed. If you chose a manual transfer (Zelle, Cash App, or Bank Transfer), please follow the payment instructions provided.")
            ->action('View Order & Payment Details', $trackingUrl)
            ->line('Thank you for supporting community culinary gatherings!')
            ->salutation('Warm regards, The ' . $eventName . ' Organizing Team');
    }

    /**
     * Build the SMS payload.
     */
    public function toSms(object $notifiable): array
    {
        $orderNumber = $this->orderData['orderNumber'] ?? 'N/A';
        $eventName = $this->orderData['eventName'] ?? 'Island Event';
        $totalFormatted = $this->orderData['totalFormatted'] ?? '$0.00';
        $trackingUrl = $this->orderData['trackingUrl'] ?? url('/orders/' . $orderNumber);

        return [
            'to' => $notifiable->phone ?? $this->orderData['customerPhone'],
            'message' => "Order #{$orderNumber} received for {$eventName}. Total: {$totalFormatted}. View details & payment steps: {$trackingUrl}",
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
