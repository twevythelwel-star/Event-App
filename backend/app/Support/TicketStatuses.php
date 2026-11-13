<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AttendeeTicket;
use App\Models\Event;
use App\Models\Order;
use App\Services\Tickets\TicketLifecycleService;

/**
 * A ticket's statuses, kept separate and never collapsed into one:
 *
 *   order     - the purchase: placed, cancelled, expired or rejected
 *   payment   - money: unpaid, submitted (the customer says they paid; not yet verified), partially paid, paid,
 *               partially refunded, refunded or charged back. "paid" comes only from a trusted source: payment
 *               provider reconciliation or an authorised manual recording (cash ledger, host verification). A pay
 *               button, a success redirect, an uploaded screenshot or a generated QR code never changes it.
 *   ticket    - the credential's lifecycle state (below)
 *   admission - not admitted or admitted (with when)
 *   food      - the order's food fulfilment, handled at the food stations
 *
 * Ticket lifecycle states, most decisive first:
 *   charged_back     the provider reclaimed the money; every credential revoked
 *   revoked          the credential was revoked on the server (reason in the order and the credential history)
 *   refunded         refunded in full
 *   cancelled        the order was cancelled, rejected or expired
 *   event_cancelled  the host cancelled the event
 *   completed        the event is over; the ticket is a keepsake
 *   valid            paid or confirmed: may be admitted
 *   pending_payment  the credential exists (activated on order creation) but does not admit until payment is verified
 * and `lifecycle` adds `admitted` once admission is recorded on a valid or completed ticket.
 *
 * The gate never relies on these labels: it re-checks every condition on the server at scan time.
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

    public const TICKET_STATES = ['pending_payment', 'valid', 'admitted', 'cancelled', 'refunded', 'charged_back', 'revoked', 'event_cancelled', 'completed'];

    /** @return array{order: string, payment: string, ticket: string, lifecycle: string, admission: string, admitted_at: ?string, food: string} */
    public static function for(Order $order, ?AttendeeTicket $ticket = null, ?Event $event = null): array
    {
        $status = (string) $order->status;
        $orderStatus = self::ORDER_CLOSED[$status] ?? 'placed';
        $chargedBack = TicketLifecycleService::isChargedBack($order);
        $payment = $chargedBack ? 'charged_back' : (self::PAYMENT[$status] ?? ($orderStatus === 'placed' ? 'unpaid' : 'not_collected'));

        $admittedAt = $ticket?->checked_in_at ?? $order->checked_in_at;
        $event ??= $order->relationLoaded('event') ? $order->event : Event::withoutGlobalScopes()->find($order->event_id);

        $ticketStatus = match (true) {
            $chargedBack => 'charged_back',
            $order->token_revoked_at !== null => 'revoked',
            $status === 'refunded' => 'refunded',
            $orderStatus !== 'placed' => 'cancelled',
            $event !== null && $event->status === 'cancelled' => 'event_cancelled',
            $event !== null && TicketLifecycle::eventIsOver($event) => 'completed',
            // Exactly what the gate accepts (EventWorkerService): a paid or confirmed order.
            in_array($status, ['paid', 'confirmed'], true) => 'valid',
            default => 'pending_payment',
        };
        $admitted = $admittedAt !== null;

        return [
            'order' => $orderStatus,
            'payment' => $payment,
            'ticket' => $ticketStatus,
            'lifecycle' => $admitted && in_array($ticketStatus, ['valid', 'completed'], true) ? 'admitted' : $ticketStatus,
            'admission' => $admitted ? 'admitted' : 'not_admitted',
            'admitted_at' => $admittedAt?->toIso8601String(),
            'food' => (string) ($order->fulfillment_status ?: 'none'),
        ];
    }
}
