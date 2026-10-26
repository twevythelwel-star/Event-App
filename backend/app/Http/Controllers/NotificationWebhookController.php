<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationWebhookController extends Controller
{
    /**
     * Handle Twilio SMS Status Callback
     * Twilio POSTs MessageSid and MessageStatus ('queued', 'sent', 'delivered', 'undelivered', 'failed').
     *
     * INVARIANT: Only mark as 'delivered' when Twilio explicitly confirms MessageStatus === 'delivered'!
     */
    public function handleTwilioStatusCallback(Request $request): JsonResponse
    {
        $messageSid = $request->input('MessageSid');
        $messageStatus = strtolower($request->input('MessageStatus', ''));
        $errorCode = $request->input('ErrorCode');
        $errorMessage = $request->input('ErrorMessage');

        Log::info("[TwilioWebhook] Callback received for SID {$messageSid} with status: {$messageStatus}");

        $log = NotificationLog::where('provider_message_id', $messageSid)->first();

        if (!$log) {
            Log::warning("[TwilioWebhook] No NotificationLog matching SID: {$messageSid}");
            return response()->json(['status' => 'not_found'], 404);
        }

        switch ($messageStatus) {
            case 'delivered':
                // Verified handset delivery callback from mobile network (Digicel / Flow / T-Mobile / AT&T)
                $log->status = 'delivered';
                $log->delivered_at = now();
                $log->logAttempt('delivered', "Carrier handset delivery confirmed via Twilio webhook callback.");
                break;

            case 'undelivered':
            case 'failed':
                $log->status = 'failed';
                $log->failure_reason = "Carrier failure: [Error {$errorCode}] {$errorMessage}";
                $log->logAttempt('failed', "Delivery failed: [Error {$errorCode}] {$errorMessage}");
                break;

            case 'sent':
                // Intermediate status: Gateway sent to downstream mobile network, but handset not yet acknowledged
                $log->status = 'sent';
                $log->logAttempt('sent', "Carrier network received message; awaiting handset acknowledgment.");
                break;

            case 'queued':
                // Carrier queue: Do NOT mark delivered!
                $log->status = 'queued';
                break;
        }

        $log->save();

        return response()->json([
            'status' => 'acknowledged',
            'updated_status' => $log->status,
        ]);
    }

    /**
     * Handle Email Webhook Status Callback (Postmark / Mailgun / SendGrid)
     */
    public function handleEmailWebhook(Request $request): JsonResponse
    {
        $messageId = $request->input('MessageID') ?? $request->input('event-data.message.headers.message-id');
        $event = strtolower($request->input('RecordType') ?? $request->input('event') ?? '');

        Log::info("[EmailWebhook] Event '{$event}' for Message ID: {$messageId}");

        $log = NotificationLog::where('provider_message_id', $messageId)
            ->orWhere('idempotency_key', $request->header('X-Idempotency-Key'))
            ->first();

        if (!$log) {
            return response()->json(['status' => 'not_found'], 404);
        }

        if (in_array($event, ['delivery', 'delivered'])) {
            $log->status = 'delivered';
            $log->delivered_at = now();
            $log->logAttempt('delivered', "Recipient mail server accepted and delivered message.");
        } elseif (in_array($event, ['bounce', 'hard_bounce', 'failed'])) {
            $log->status = 'failed';
            $log->failure_reason = $request->input('Description', 'Email bounced by receiving mail server');
            $log->logAttempt('failed', "Email bounce recorded: " . $log->failure_reason);
        }

        $log->save();

        return response()->json(['status' => 'processed']);
    }
}
