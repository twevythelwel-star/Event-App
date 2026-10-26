<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentVerifiedOrRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $orderData;
    public bool $isVerified;
    public string $idempotencyKey;

    public function __construct(array $orderData, bool $isVerified = true)
    {
        $this->orderData = $orderData;
        $this->isVerified = $isVerified;
        $statusKey = $isVerified ? 'verified' : 'rejected';
        $this->idempotencyKey = 'idemp_pay_' . $statusKey . '_' . ($orderData['orderNumber'] ?? uniqid());
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
        $ticketCode = $this->orderData['ticketCode'] ?? 'TCK-SAMPLE';
        $ticketUrl = $this->orderData['ticketUrl'] ?? url('/tickets/' . $ticketCode);
        $rejectReason = $this->orderData['rejectionReason'] ?? 'Transfer reference could not be matched with bank ledger statement.';

        if ($this->isVerified) {
            return (new MailMessage)
                ->success()
                ->subject("Payment Verified: Order #{$orderNumber} (Ticket Active)")
                ->greeting("Great news, {$customerName}!")
                ->line("Your payment for **Order #{$orderNumber}** has been **verified and approved** by the organizer.")
                ->line("Your official digital ticket **#{$ticketCode}** is now **ACTIVE**.")
                ->line("Please present this digital ticket QR code at the event gate or kitchen counter to redeem your meals.")
                ->action('Open Digital Ticket & QR Code', $ticketUrl)
                ->line('Keep this ticket safe. We look forward to serving you!')
                ->salutation('Warm regards, Event Management');
        } else {
            return (new MailMessage)
                ->error()
                ->subject("Action Required: Payment Verification Unsuccessful for Order #{$orderNumber}")
                ->greeting("Hello {$customerName},")
                ->line("The organizer was unable to verify your payment for **Order #{$orderNumber}**.")
                ->line("**Reason provided:** {$rejectReason}")
                ->line("Your digital ticket remains unissued or on hold until valid payment is confirmed.")
                ->action('Resubmit Payment Proof', url('/orders/' . $orderNumber . '/submit-transfer'))
                ->line('If you believe this was an error, please click the button above to upload updated reference proof or contact the organizer.')
                ->salutation('Event Verification Team');
        }
    }

    public function toSms(object $notifiable): array
    {
        $orderNumber = $this->orderData['orderNumber'] ?? 'N/A';
        $ticketCode = $this->orderData['ticketCode'] ?? 'TCK-SAMPLE';
        $ticketUrl = $this->orderData['ticketUrl'] ?? url('/tickets/' . $ticketCode);
        $rejectionReason = $this->orderData['rejectionReason'] ?? 'Payment could not be confirmed';

        if ($this->isVerified) {
            return [
                'to' => $notifiable->phone ?? $this->orderData['customerPhone'],
                'message' => "Payment VERIFIED for Order #{$orderNumber}! Ticket #{$ticketCode} is now active. Present your QR at the gate: {$ticketUrl}",
                'idempotency_key' => $this->idempotencyKey,
            ];
        } else {
            return [
                'to' => $notifiable->phone ?? $this->orderData['customerPhone'],
                'message' => "Order #{$orderNumber} payment could not be verified ({$rejectionReason}). Please resubmit reference or contact organizer: " . url('/orders/' . $orderNumber),
                'idempotency_key' => $this->idempotencyKey,
            ];
        }
    }
}
