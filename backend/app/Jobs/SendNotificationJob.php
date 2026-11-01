<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Notifications\NotificationNotSent;
use App\Services\Notifications\TwilioSmsSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers one notification_logs entry through its real provider.
 *
 * Status rules: 'sent' means the provider accepted the message; only the provider's delivery callback
 * may set 'delivered'. Temporary errors are retried with backoff; permanent ones (no provider configured,
 * invalid number, opted-out recipient) fail immediately with the reason recorded.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** Seconds between attempts. */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public string $notificationLogId
    ) {}

    public function handle(TwilioSmsSender $sms): void
    {
        $log = NotificationLog::withoutGlobalScopes()->find($this->notificationLogId);
        if (!$log) {
            return;
        }

        // Retries must never send the same message twice
        if (in_array($log->status, ['sent', 'delivered'], true)) {
            $log->retry_suppressed = true;
            $log->logAttempt($log->status, 'Duplicate send suppressed: message already accepted by the provider.');

            return;
        }

        $log->retry_count = $this->attempts();
        $log->save();

        try {
            $providerId = match ($log->channel) {
                'sms' => $sms->send($log->recipient, $log->message),
                'email' => $this->sendEmail($log),
                default => throw new NotificationNotSent("Channel '{$log->channel}' is not supported."),
            };
        } catch (NotificationNotSent $e) {
            $this->markFailed($log, $e->getMessage());

            return;
        } catch (\Throwable $e) {
            $finalAttempt = $this->attempts() >= $this->tries;
            $log->logAttempt('failed', "Attempt {$this->attempts()} failed: {$e->getMessage()}" . ($finalAttempt ? '' : ' Will retry.'));
            if ($finalAttempt) {
                $this->markFailed($log, $e->getMessage());

                return;
            }

            throw $e;
        }

        $log->status = 'sent';
        $log->provider_message_id = $providerId;
        $log->sent_at = now();
        $log->failure_reason = null;
        $log->save();
        $log->logAttempt('sent', "Accepted by {$log->provider}" . ($providerId ? " (ID {$providerId})" : '') . '. Awaiting delivery confirmation.');
    }

    private function sendEmail(NotificationLog $log): ?string
    {
        // Receipt emails carry the order's itemized receipt as it stands when the email goes out.
        $receipt = null;
        if (in_array($log->trigger, \App\Services\Notifications\NotificationService::RECEIPT_TRIGGERS, true) && $log->order_id) {
            $order = \App\Models\Order::withoutGlobalScopes()->find($log->order_id);
            $receipt = $order ? app(\App\Services\ReceiptService::class)->forOrder($order) : null;
        }

        $data = ['log' => $log, 'receipt' => $receipt, 'receiptText' => $receipt ? app(\App\Services\ReceiptService::class)->toText($receipt) : null];
        $sent = Mail::send(['html' => 'emails.notification', 'text' => 'emails.notification-text'], $data, function (Message $message) use ($log) {
            $message->to($log->recipient, $log->customer_name)
                ->subject($log->email_subject ?: "EventBite: {$log->title}");
            $message->getHeaders()->addTextHeader('X-Idempotency-Key', $log->idempotency_key);
        });

        return $sent?->getMessageId();
    }

    private function markFailed(NotificationLog $log, string $reason): void
    {
        $log->status = 'failed';
        $log->failure_reason = $reason;
        $log->save();
        $log->logAttempt('failed', $reason);
    }
}
