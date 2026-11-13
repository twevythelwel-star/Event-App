<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AttendeeTicket;
use App\Models\Event;
use App\Models\Order;

/**
 * The five separate statuses of a ticket, never collapsed into one:
 *
 *   order     - the purchase itself: placed, cancelled, expired or rejected
 *   payment   - money: unpaid, submitted (the customer says they paid; not yet verified), partially paid, paid,
 *               partially refunded or refunded. "paid" comes only from a trusted source: payment-provider
 *               reconciliation or an authorised manual recording (cash ledger, host verification). Clicking a pay
 *               button, a success redirect, an uploaded screenshot or a generated QR code never changes it.
 *   ticket    - the credential: issued (exists, waiting for payment), valid (may be admitted), revoked, void
 *               (order cancelled, rejected, expired or refunded) or keepsake (event over)
 *   admission - not admitted or admitted (with when)
 *   food      - the order's food fulfilment, handled at the food stations
 *
 * A QR code is created as soon as the order exists, so "issued" while payment is pending is normal; the scanner
 * admits only a "valid" ticket, and the gate re-checks every condition on the server at scan time.
 */
final class TicketStatuses
{
    private const ORDER_CLOSED = ['cancelled' => 'cancelled', 'expired' => 'expired', 'rejected' => 'rejected'];

    private const PAYMENT = [
        'awaiting_payment' => 'unpaid',
        'payment_submitted' => 'submitted',
        'under_verification' => 'submitted',
        'partially_paid' => 'partially_paid',
        'paid' => 'paid',
        'confirmed' => 'paid',
        'partially_refunded' => 'partially_refunded',
        'refunded' => 'refunded',
    ];

    /** @return array{order: string, payment: string, ticket: string, admission: string, admitted_at: ?string, food: string} */
    public static function for(Order $order, ?AttendeeTicket $ticket = null, ?Event $event = null): array
    {
        $status = (string) $order->status;
        $orderStatus = self::ORDER_CLOSED[$status] ?? 'placed';
        $payment = self::PAYMENT[$status] ?? ($orderStatus === 'placed' ? 'unpaid' : 'not_collected');

        $admittedAt = $ticket?->checked_in_at ?? $order->checked_in_at;
        $event ??= $order->relationLoaded('event') ? $order->event : Event::withoutGlobalScopes()->find($order->event_id);

        $ticketStatus = match (true) {
            $order->token_revoked_at !== null => 'revoked',
            $orderStatus !== 'placed' || $payment === 'refunded' => 'void',
            $event !== null && TicketLifecycle::eventIsOver($event) => 'keepsake',
            // Exactly what the gate accepts (EventWorkerService): a paid or confirmed order.
            in_array($status, ['paid', 'confirmed'], true) => 'valid',
            default => 'issued',
        };

        return [
            'order' => $orderStatus,
            'payment' => $payment,
            'ticket' => $ticketStatus,
            'admission' => $admittedAt !== null ? 'admitted' : 'not_admitted',
            'admitted_at' => $admittedAt?->toIso8601String(),
            'food' => (string) ($order->fulfillment_status ?: 'none'),
        ];
    }
}
