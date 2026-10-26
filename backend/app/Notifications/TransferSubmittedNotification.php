<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransferSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $orderData;
    public string $idempotencyKey;

    public function __construct(array $orderData)
    {
        $this->orderData = $orderData;
        $this->idempotencyKey = 'idemp_transfer_sub_' . ($orderData['orderNumber'] ?? uniqid());
        $this->onQueue('notifications');
    }

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

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->orderData['customerName'] ?? 'Valued Customer';
        $orderNumber = $this->orderData['orderNumber'] ?? 'N/A';
        $reference = $this->orderData['paymentReference'] ?? 'REF-MANUAL';
        $paymentMethod = $this->orderData['paymentMethodName'] ?? 'Transfer';
        $trackingUrl = $this->orderData['trackingUrl'] ?? url('/orders/' . $orderNumber);

        return (new MailMessage)
            ->subject("Payment Transfer Submitted: Order #{$orderNumber} (Pending Verification)")
            ->greeting("Hello {$customerName},")
            ->line("We have received your transfer details for **Order #{$orderNumber}**.")
            ->line("**Payment Method:** {$paymentMethod}")
            ->line("**Submitted Reference:** `{$reference}`")
            ->line("**Status:** Under Organizer Review")
            ->line("> **Please note:** This confirmation verifies that your payment details were received by our system, but **does not mean payment has succeeded**. The organizer will cross-reference their account ledger before approving your ticket.")
            ->action('Check Verification Status', $trackingUrl)
            ->line('You will receive an immediate notification once the organizer approves your payment.')
            ->salutation('Regards, Event Verification Desk');
    }

    public function toSms(object $notifiable): array
    {
        $orderNumber = $this->orderData['orderNumber'] ?? 'N/A';
        $reference = $this->orderData['paymentReference'] ?? 'REF-MANUAL';
        $trackingUrl = $this->orderData['trackingUrl'] ?? url('/orders/' . $orderNumber);

        return [
            'to' => $notifiable->phone ?? $this->orderData['customerPhone'],
            'message' => "Order #{$orderNumber}: Payment ref {$reference} submitted. Note: Funds are NOT yet verified. Ticket activates once organizer confirms receipt. Status: {$trackingUrl}",
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
