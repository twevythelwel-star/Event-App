<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentInstructionsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $orderData;
    public string $idempotencyKey;

    public function __construct(array $orderData)
    {
        $this->orderData = $orderData;
        $this->idempotencyKey = 'idemp_pay_instructions_' . ($orderData['orderNumber'] ?? uniqid());
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
        $paymentMethod = $this->orderData['paymentMethodName'] ?? 'Transfer';
        $paymentInstructions = $this->orderData['instructionsText'] ?? 'Please transfer to the organizer.';
        $accountDetails = $this->orderData['accountDetails'] ?? '';
        $amountDue = $this->orderData['totalFormatted'] ?? '$0.00';
        $submissionUrl = $this->orderData['submissionUrl'] ?? url('/orders/' . $orderNumber . '/submit-transfer');

        $mail = (new MailMessage)
            ->subject("Payment Instructions: Order #{$orderNumber} ({$paymentMethod})")
            ->greeting("Hello {$customerName},")
            ->line("Here are your payment instructions for **Order #{$orderNumber}**.")
            ->line("**Amount Due:** {$amountDue}")
            ->line("**Selected Payment Method:** {$paymentMethod}")
            ->line("**Payment Details:** {$accountDetails}")
            ->line("**Organizer Note:** {$paymentInstructions}")
            ->line("**Important Rule:** Your ticket will only be activated after the organizer verifies receipt of your funds. Never assume transfer submission equals verified payment.")
            ->action('Submit Payment Reference / Screenshot', $submissionUrl)
            ->line('Once you have completed the transfer, click the button above to upload your reference or receipt.')
            ->salutation('Best regards, Event Finance Desk');

        return $mail;
    }

    public function toSms(object $notifiable): array
    {
        $orderNumber = $this->orderData['orderNumber'] ?? 'N/A';
        $paymentMethod = $this->orderData['paymentMethodName'] ?? 'Transfer';
        $amountDue = $this->orderData['totalFormatted'] ?? '$0.00';
        $payHandle = $this->orderData['accountHandle'] ?? 'see instructions';
        $submissionUrl = $this->orderData['submissionUrl'] ?? url('/orders/' . $orderNumber . '/submit-transfer');

        return [
            'to' => $notifiable->phone ?? $this->orderData['customerPhone'],
            'message' => "Order #{$orderNumber} payment instructions: Send {$amountDue} via {$paymentMethod} to {$payHandle}. Submit reference to activate ticket: {$submissionUrl}",
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
