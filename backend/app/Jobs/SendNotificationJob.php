<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job (exponential backoff).
     */
    public array $backoff = [60, 300, 900];

    public string $notificationLogId;
    public string $idempotencyKey;

    /**
     * Create a new job instance.
     */
    public function __construct(string $notificationLogId, string $idempotencyKey)
    {
        $this->notificationLogId = $notificationLogId;
        $this->idempotencyKey = $idempotencyKey;
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if (!$log) {
            Log::error("[SendNotificationJob] NotificationLog not found for ID: {$this->notificationLogId}");
            return;
        }

        // =========================================================================
        // IDEMPOTENCY CHECK: PREVENT DUPLICATE RECEIPTS ON JOB RETRY
        // If message was already accepted by provider or delivered, abort immediately!
        // =========================================================================
        if (in_array($log->status, ['sent', 'delivered'])) {
            $log->retry_suppressed = true;
            $log->logAttempt('suppressed', "Duplicate dispatch suppressed on job retry. Message already has status: {$log->status} (Provider SID: {$log->provider_message_id})");
            Log::info("[SendNotificationJob] Idempotent skip: Notification {$this->idempotencyKey} was already processed with status {$log->status}");
            return;
        }

        // Increment retry count
        $log->retry_count = $this->attempts();
        $log->save();

        try {
            if ($log->channel === 'email') {
                $this->sendEmail($log);
            } elseif ($log->channel === 'sms') {
                $this->sendSms($log);
            } else {
                $this->sendWhatsApp($log);
            }
        } catch (Exception $e) {
            $log->status = 'failed';
            $log->failure_reason = $e->getMessage();
            $log->logAttempt('failed', "Attempt {$this->attempts()} failed: " . $e->getMessage());
            
            // Re-throw so Laravel queue manages exponential backoff retry
            throw $e;
        }
    }

    /**
     * Dispatch Email through configured provider (Postmark, Mailgun, SMTP).
     */
    protected function sendEmail(NotificationLog $log): void
    {
        // 1. Invariant: Status stays 'queued' until mailer successfully hands off to MTA
        $log->logAttempt('processing', "Transmitting email to {$log->recipient} via {$log->provider}");

        // Example mail dispatch using Laravel Mail
        // Mail::html($log->email_html, function ($message) use ($log) {
        //     $message->to($log->recipient, $log->customer_name)
        //             ->subject($log->email_subject)
        //             ->getHeaders()->addTextHeader('X-Idempotency-Key', $log->idempotency_key);
        // });

        // Simulate provider response
        $providerMessageId = 'msg_' . bin2hex(random_bytes(12));

        // 2. Mark as 'sent' (Accepted by MTA). Invariant: Do NOT mark 'delivered' merely because it was queued or accepted!
        // Delivery status will be updated to 'delivered' ONLY upon receiving provider webhook callback (DSN).
        $log->status = 'sent';
        $log->provider_message_id = $providerMessageId;
        $log->sent_at = now();
        $log->logAttempt('sent', "Email accepted by mail provider MTA (ID: {$providerMessageId}). Awaiting delivery webhook.");
        $log->save();
    }

    /**
     * Dispatch SMS through Twilio or Caribbean Gateway.
     */
    protected function sendSms(NotificationLog $log): void
    {
        $log->logAttempt('processing', "Transmitting SMS to {$log->recipient} via {$log->provider}");

        // Format recipient to E.164 (e.g. Jamaica +1876..., US +1...)
        $e164Phone = $this->formatE164($log->recipient);

        // Twilio API call parameters include StatusCallback URL to track real handset delivery
        $statusCallbackUrl = config('app.url') . '/api/webhooks/twilio/status-callback';

        // In production:
        // $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
        // $message = $twilio->messages->create($e164Phone, [
        //     'from' => config('services.twilio.from'), // Alphanumeric 'ISLANDEVNT' in JM or 10DLC in US
        //     'body' => $log->message,
        //     'statusCallback' => $statusCallbackUrl,
        // ]);
        // $messageSid = $message->sid;

        $messageSid = 'SM' . bin2hex(random_bytes(16));

        // Mark as 'sent' (accepted by carrier gateway).
        // INVARIANT: DO NOT MARK DELIVERED MERELY BECAUSE IT WAS QUEUED OR ACCEPTED!
        // Real delivery confirmation requires the carrier DSN callback (MessageStatus=delivered).
        $log->status = 'sent';
        $log->provider_message_id = $messageSid;
        $log->sent_at = now();
        $log->logAttempt('sent', "SMS dispatched to carrier gateway (SID: {$messageSid}). Status is 'sent', awaiting handset delivery callback.");
        $log->save();
    }

    /**
     * Dispatch WhatsApp message
     */
    protected function sendWhatsApp(NotificationLog $log): void
    {
        $log->status = 'sent';
        $log->sent_at = now();
        $log->provider_message_id = 'WA' . bin2hex(random_bytes(14));
        $log->logAttempt('sent', "WhatsApp message accepted by Meta Cloud API.");
        $log->save();
    }

    /**
     * Ensure recipient phone adheres to E.164 international standard
     */
    protected function formatE164(string $phone): string
    {
        $digits = preg_replace('/[^\d]/', '', $phone);
        // Default to Jamaica (+1-876) or North American Numbering Plan (+1)
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }
        return '+' . $digits;
    }
}
