<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Turns order lifecycle moments into customer messages (SMS to the order's phone, email when the
 * order has one), records each in notification_logs and queues it for real delivery.
 *
 * Each (order, moment, channel) is sent at most once thanks to a deterministic idempotency key, and a
 * notification problem never breaks the business action that triggered it.
 */
class NotificationService
{
    public const TRIGGERS = [
        'order_received',
        'payment_verified',
        'payment_rejected',
        'cash_receipt_recorded',
        'pickup_instructions',
        'order_collection_completed',
        'event_reminders',
    ];

    /**
     * Safe entry point for business services: never throws.
     *
     * @param array<string, mixed> $context Extra details for the message (e.g. cash amount, receipt number)
     */
    public static function notifyOrder(Order $order, string $trigger, array $context = [], ?string $uniqueSuffix = null): void
    {
        try {
            app(self::class)->queueForOrder($order, $trigger, $context, $uniqueSuffix);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /**
     * @return NotificationLog[] The log entries created (already-sent duplicates are skipped)
     */
    public function queueForOrder(Order $order, string $trigger, array $context = [], ?string $uniqueSuffix = null): array
    {
        if (!in_array($trigger, self::TRIGGERS, true)) {
            throw new \InvalidArgumentException("Unknown notification trigger '{$trigger}'.");
        }

        $order->loadMissing('event');
        [$title, $text] = $this->compose($order, $trigger, $context);

        $targets = [];
        if (trim((string) $order->customer_phone) !== '') {
            $targets['sms'] = $order->customer_phone;
        }
        if (filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
            $targets['email'] = $order->email;
        }

        $created = [];
        foreach ($targets as $channel => $recipient) {
            $key = "order:{$order->id}:{$trigger}:{$channel}" . ($uniqueSuffix ? ":{$uniqueSuffix}" : '');

            $log = NotificationLog::withoutGlobalScopes()->firstOrCreate(['idempotency_key' => $key], [
                'organizer_id' => $order->organizer_id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'ticket_code' => $order->ticket_code,
                'customer_name' => $order->customer_name,
                'recipient' => $recipient,
                'channel' => $channel,
                'trigger' => $trigger,
                'title' => $title,
                'message' => $text,
                'email_subject' => $channel === 'email' ? "EventBite: {$title}" : null,
                'status' => 'queued',
                'provider' => $channel === 'sms' ? 'Twilio SMS' : 'Email (' . config('mail.default') . ')',
                'retry_count' => 0,
                'queued_at' => now(),
                'attempts_history' => [['timestamp' => now()->toIso8601String(), 'status' => 'queued', 'note' => 'Queued for delivery.']],
            ]);

            if ($log->wasRecentlyCreated) {
                self::dispatchAfterCommit($log->id);
                $created[] = $log;
            }
        }

        return $created;
    }

    /**
     * Queue a failed message again (hosts can retry from the dashboard).
     */
    public function retry(NotificationLog $log): NotificationLog
    {
        if ($log->status !== 'failed') {
            throw new \InvalidArgumentException("Only failed messages can be retried (this one is {$log->status}).");
        }

        $log->status = 'queued';
        $log->failure_reason = null;
        // A retry is a new message; never let it be mistaken for the previous attempt
        $log->provider_message_id = null;
        $log->sent_at = null;
        $log->queued_at = now();
        $log->save();
        $log->logAttempt('queued', 'Retry requested by host.');

        self::dispatchAfterCommit($log->id);

        return $log->fresh();
    }

    /**
     * Queue the send once the surrounding transaction commits. On the sync queue the job runs right
     * there, so its errors are caught: a provider outage must never fail the order or payment action.
     */
    private static function dispatchAfterCommit(string $logId): void
    {
        DB::afterCommit(function () use ($logId) {
            try {
                SendNotificationJob::dispatch($logId);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * @return array{0: string, 1: string} [title, message text] — kept short enough for 2 SMS segments
     */
    private function compose(Order $order, string $trigger, array $context): array
    {
        $event = $order->event?->title ?? 'your event';
        $total = self::money((float) $order->total_amount, $order->currency);
        $code = $order->ticket_code;

        return match ($trigger) {
            'order_received' => [
                'Order received',
                "EventBite: we got your order {$order->order_number} for {$event} ({$total}). "
                    . ($order->status === 'paid' || $order->authorized_unpaid
                        ? 'Your ticket is ' . $code . '.'
                        : "Payment is being checked; your ticket {$code} activates once it is verified."),
            ],
            'payment_verified' => [
                'Payment verified',
                "EventBite: payment of " . self::money((float) $order->amount_paid, $order->currency)
                    . " for order {$order->order_number} is confirmed. Show ticket {$code} at pickup for {$event}.",
            ],
            'payment_rejected' => [
                'Payment not verified',
                "EventBite: we could not verify payment for order {$order->order_number}"
                    . ($order->rejection_reason ? " ({$order->rejection_reason})" : '')
                    . '. Please contact the organizer.',
            ],
            'cash_receipt_recorded' => [
                'Cash payment received',
                'EventBite: cash payment of ' . self::money((float) ($context['amount'] ?? 0), $order->currency)
                    . " recorded for order {$order->order_number}" . (isset($context['receipt']) ? " (receipt {$context['receipt']})" : '')
                    . '. Balance due: ' . self::money(max(0, (float) $order->total_amount - (float) $order->amount_paid), $order->currency) . '.',
            ],
            'pickup_instructions' => [
                'Order ready for pickup',
                "EventBite: your order {$order->order_number} is ready! Show ticket {$code} at {$event}."
                    . ($order->event?->pickup_instructions ? ' ' . $order->event->pickup_instructions : ''),
            ],
            'order_collection_completed' => [
                'Order collected',
                "EventBite: all items for ticket {$code} have been handed over. Enjoy {$event}!",
            ],
            'event_reminders' => [
                'Event reminder',
                "EventBite: reminder, {$event} is on " . ($order->event?->date_str ?? 'soon')
                    . ($order->event?->time_str ? ", {$order->event->time_str}" : '') . ". Your ticket: {$code}.",
            ],
        };
    }

    private static function money(float $amount, ?string $currency): string
    {
        return ($currency === 'USD' ? 'US$' : 'J$') . number_format($amount, $currency === 'USD' ? 2 : 0);
    }
}
