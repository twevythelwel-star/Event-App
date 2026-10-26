<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CashReceiptRecordedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $receiptData;
    public string $idempotencyKey;

    public function __construct(array $receiptData)
    {
        $this->receiptData = $receiptData;
        $this->idempotencyKey = 'idemp_cash_rcpt_' . ($receiptData['receiptNumber'] ?? uniqid());
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (!empty($notifiable->email ?? $this->receiptData['customerEmail'] ?? null)) {
            $channels[] = 'mail';
        }
        if (!empty($notifiable->phone ?? $this->receiptData['customerPhone'] ?? null)) {
            $channels[] = 'sms';
        }
        return $channels ?: ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->receiptData['customerName'] ?? 'Valued Patron';
        $orderNumber = $this->receiptData['orderNumber'] ?? 'N/A';
        $receiptNumber = $this->receiptData['receiptNumber'] ?? 'RCP-CASH-1001';
        $amountReceived = $this->receiptData['amountReceived'] ?? '$0.00';
        $receivedBy = $this->receiptData['receivedBy'] ?? 'Authorized Event Agent';
        $receivedAt = $this->receiptData['receivedAt'] ?? date('M d, Y h:i A');
        $ticketCode = $this->receiptData['ticketCode'] ?? 'TCK-CASH';
        $ticketUrl = $this->receiptData['ticketUrl'] ?? url('/tickets/' . $ticketCode);

        return (new MailMessage)
            ->subject("Official Cash Receipt #{$receiptNumber} - Order #{$orderNumber}")
            ->greeting("Hello {$customerName},")
            ->line("An authorized event agent has recorded your in-person cash payment.")
            ->line("**Receipt Number:** `{$receiptNumber}`")
            ->line("**Order Number:** #{$orderNumber}")
            ->line("**Amount Collected:** {$amountReceived}")
            ->line("**Received By:** {$receivedBy}")
            ->line("**Date & Time:** {$receivedAt}")
            ->line("Your order is now fully settled and your digital redemption ticket is active.")
            ->action('View Digital Ticket & Receipt', $ticketUrl)
            ->line('Please present this ticket on event day for food collection.')
            ->salutation('Warm regards, Event Box Office');
    }

    public function toSms(object $notifiable): array
    {
        $orderNumber = $this->receiptData['orderNumber'] ?? 'N/A';
        $receiptNumber = $this->receiptData['receiptNumber'] ?? 'RCP-CASH';
        $amountReceived = $this->receiptData['amountReceived'] ?? '$0.00';
        $ticketUrl = $this->receiptData['ticketUrl'] ?? url('/orders/' . $orderNumber);

        return [
            'to' => $notifiable->phone ?? $this->receiptData['customerPhone'],
            'message' => "Cash receipt recorded: {$amountReceived} received for Order #{$orderNumber} (Rcpt: {$receiptNumber}). Your digital ticket is activated: {$ticketUrl}",
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
