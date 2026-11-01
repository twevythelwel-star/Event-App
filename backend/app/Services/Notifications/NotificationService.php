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
        'payment_awaiting_verification',
        'payment_verified',      // "Payment confirmed"
        'payment_rejected',      // "Payment failed": a manual payment could not be verified
        'cash_receipt_recorded',
        'ticket_issued',
        'event_updated',
        'event_reminders',
        'refund_requested',
        'refund_completed',
        'pickup_instructions',   // "Food ready for pickup"
        'order_collection_completed',
    ];

    /** Messages that say money was received. They are only ever sent for an order whose payment is settled. */
    public const REQUIRES_SETTLED_PAYMENT = ['payment_verified', 'ticket_issued'];

    /** Emails for these moments carry the full itemized receipt. */
    public const RECEIPT_TRIGGERS = ['order_received', 'payment_verified', 'ticket_issued', 'cash_receipt_recorded', 'refund_completed'];

    /** Order states that still hold a valid ticket, so the customer should hear about event changes. */
    private const LIVE_ORDER_STATUSES = ['awaiting_payment', 'payment_submitted', 'under_verification', 'partially_paid', 'paid', 'partially_refunded'];

    /**
     * The payment is now settled: confirm it, then issue the ticket. The order's state is checked again by
     * queueForOrder, so this can never confirm money that was not verified.
     */
    public static function paymentSettled(Order $order): void
    {
        self::notifyOrder($order, 'payment_verified');
        self::notifyOrder($order, 'ticket_issued');
    }

    /** Event fields whose change ticket holders need to hear about: when and where. */
    public const EVENT_NOTICE_FIELDS = ['event_date_iso', 'date_str', 'time_str', 'timezone', 'venue_name', 'street_address', 'city', 'state_code', 'zip_code'];

    /**
     * Compare an event's when-and-where before and after a save, and message ticket holders if it changed.
     *
     * @param array<string, mixed> $before $event->only(EVENT_NOTICE_FIELDS) taken before the save
     */
    public static function afterEventSaved(\App\Models\Event $event, array $before): int
    {
        $changes = [];
        foreach (self::EVENT_NOTICE_FIELDS as $field) {
            $old = trim((string) ($before[$field] ?? ''));
            $new = trim((string) ($event->{$field} ?? ''));
            if ($old !== $new) {
                $changes[$field] = [$old, $new];
            }
        }

        try {
            return self::eventUpdated($event, $changes);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Tell everyone holding a live order for this event that its date, time or place changed.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changes field => [before, after]
     */
    public static function eventUpdated(\App\Models\Event $event, array $changes): int
    {
        if ($changes === [] || $event->status !== 'published') {
            return 0;
        }
        // One message per distinct change, so saving the same values twice does not message twice.
        $suffix = substr(sha1(json_encode(array_map(fn ($pair) => $pair[1], $changes))), 0, 16);
        $count = 0;
        Order::withoutGlobalScopes()->where('event_id', $event->id)->whereIn('status', self::LIVE_ORDER_STATUSES)
            ->each(function (Order $order) use ($changes, $suffix, &$count) {
                self::notifyOrder($order, 'event_updated', ['changes' => array_keys($changes)], $suffix);
                $count++;
            });

        return $count;
    }

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
        // Payment-confirmed messaging only when the payment really is verified, whatever the caller thought.
        if (in_array($trigger, self::REQUIRES_SETTLED_PAYMENT, true) && !in_array($order->status, ['paid', 'partially_refunded'], true)) {
            return [];
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
        $eventModel = $order->event;
        $event = $eventModel?->title ?? 'your event';
        $money = fn (int $minor) => \App\Support\Money::formatMinor($minor, $order->currency);
        $total = $money((int) $order->total_minor);
        $code = $order->ticket_code;
        $number = $order->order_number;
        // U.S. formatting in the event's own time zone, e.g. "Saturday, November 21, 2026, 6:00 PM – 11:00 PM EST"
        $when = trim(implode(', ', array_filter([\App\Services\ReceiptService::eventDate($eventModel), \App\Services\ReceiptService::eventTime($eventModel)])));
        $where = \App\Services\ReceiptService::venue($eventModel);
        $method = \App\Support\PaymentStatusPresenter::methodLabel($order->method);

        return match ($trigger) {
            'order_received' => [
                'Order received',
                "EventBite: we received order {$number} for {$event} ({$total}). "
                    . (in_array($order->status, ['paid', 'partially_refunded'], true)
                        ? "Payment is confirmed; your ticket is {$code}."
                        : "Payment by {$method} is not confirmed yet. Your ticket {$code} becomes valid once it is."),
            ],
            'payment_awaiting_verification' => [
                'Payment awaiting verification',
                "EventBite: thanks, we have your {$method} reference for order {$number}. The organizer will check it; "
                    . "your ticket {$code} is not valid until they confirm the payment.",
            ],
            'payment_verified' => [
                'Payment confirmed',
                'EventBite: payment of ' . $money((int) $order->amount_paid_minor) . " ({$method}) for order {$number} is confirmed.",
            ],
            'payment_rejected' => [
                'Payment failed',
                "EventBite: the payment for order {$number} could not be verified"
                    . ($order->rejection_reason ? " ({$order->rejection_reason})" : '')
                    . '. Your ticket is not valid. Please contact the organizer or pay again.',
            ],
            'cash_receipt_recorded' => [
                'Cash payment received',
                'EventBite: cash payment of ' . \App\Support\Money::format($context['amount'] ?? 0, $order->currency)
                    . " recorded for order {$number}" . (isset($context['receipt']) ? " (receipt {$context['receipt']})" : '')
                    . ($order->balanceMinor() > 0 ? '. Balance due: ' . $money($order->balanceMinor()) . '.' : '. Paid in full.'),
            ],
            'ticket_issued' => [
                'Your ticket is ready',
                "EventBite: your ticket {$code} for {$event} is ready" . ($when !== '' ? " ({$when})" : '')
                    . '. Open it from My tickets to show the QR code at the entrance.',
            ],
            'event_updated' => [
                'Event details changed',
                "EventBite: {$event} has changed. It is now" . ($when !== '' ? " on {$when}" : '')
                    . ($where ? " at {$where}" : '') . ". Your order {$number} and ticket {$code} still apply.",
            ],
            'event_reminders' => [
                'Event reminder',
                "EventBite: reminder, {$event} is " . ($when !== '' ? "on {$when}" : 'coming up')
                    . ($where ? " at {$where}" : '') . ". Your ticket: {$code}.",
            ],
            'refund_requested' => [
                'Refund request received',
                "EventBite: we received your refund request for order {$number}"
                    . (isset($context['reference']) ? " (case {$context['reference']})" : '')
                    . '. The organizer will review it; no money has been returned yet.',
            ],
            'refund_completed' => [
                'Refund issued',
                'EventBite: a refund of ' . \App\Support\Money::format($context['amount'] ?? 0, $order->currency)
                    . " on order {$number} has been recorded" . ($order->status === 'refunded' ? ' and the ticket cancelled' : '')
                    . '. How long it takes to reach you depends on how you paid.',
            ],
            'pickup_instructions' => [
                'Food ready for pickup',
                "EventBite: your food for order {$number} is ready! Show ticket {$code} at {$event}."
                    . ($eventModel?->pickup_instructions ? ' ' . $eventModel->pickup_instructions : ''),
            ],
            'order_collection_completed' => [
                'Order collected',
                "EventBite: all items for ticket {$code} have been handed over. Enjoy {$event}!",
            ],
        };
    }

}
