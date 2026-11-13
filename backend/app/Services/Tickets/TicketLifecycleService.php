<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Models\AttendeeTicket;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\TicketCredentialEvent;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OrderPlacementException;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle steps that are not part of checkout or the gate: reissuing one guest's credential and recording a
 * chargeback. Both keep history: a reissue appends to ticket_credential_events with the reason, time and actor; a
 * chargeback appends an order_adjustments row (that table refuses updates and deletes), so the original payment
 * history is never overwritten.
 *
 * The other lifecycle states come from existing records and are reported by App\Support\TicketStatuses:
 * pending payment, valid, admitted, cancelled, refunded, revoked, event cancelled and completed.
 */
class TicketLifecycleService
{
    public function __construct(private readonly AttendeeTicketService $tickets)
    {
    }

    /**
     * A new credential for one guest; their previous QR code (and every copy of it) stops working at once.
     * Refused once the guest is admitted or the ticket can no longer be used.
     */
    public function reissue(AttendeeTicket $ticket, User $actor, string $reason): AttendeeTicket
    {
        return DB::transaction(function () use ($ticket, $actor, $reason) {
            $order = Order::withoutGlobalScopes()->whereKey($ticket->order_id)->lockForUpdate()->firstOrFail();
            $locked = AttendeeTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($locked->checked_in_at !== null) {
                throw new OrderPlacementException('This guest has already been admitted; their ticket cannot be reissued.');
            }
            if ($order->isTicketRevoked()) {
                throw new OrderPlacementException("This ticket is {$order->status}" . ($order->token_revoked_at ? ' and revoked' : '') . '; it cannot be reissued.');
            }

            $this->tickets->rotate($locked, $actor, $reason);
            AuditLogService::log(action: 'attendee_ticket_reissued', targetEntity: 'attendee_tickets', targetId: (string) $locked->id,
                details: "Ticket {$locked->ticket_number} reissued (v{$locked->token_version}) by {$actor->email}. Previous QR invalidated. Reason: {$reason}",
                actor: $actor, organizerId: (string) $order->organizer_id, eventId: (string) $order->event_id);

            return $locked;
        });
    }

    /**
     * Records a chargeback the payment provider reported: an append-only chargeback adjustment, every credential
     * on the order revoked, and the step in each ticket's history. Payment rows are left exactly as they were.
     */
    public function recordChargeback(Order $order, User $actor, int $amountMinor, string $reason, ?string $providerReference = null): Order
    {
        return DB::transaction(function () use ($order, $actor, $amountMinor, $reason, $providerReference) {
            $locked = Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (self::isChargedBack($locked)) {
                throw new OrderPlacementException('A chargeback is already recorded for this order.');
            }
            if ($amountMinor < 1 || $amountMinor > max(1, (int) $locked->amount_paid_minor, (int) $locked->total_minor)) {
                throw new OrderPlacementException('The chargeback amount must be more than zero and no more than the order total.');
            }

            OrderAdjustment::create([
                'order_id' => $locked->id,
                'organizer_id' => $locked->organizer_id,
                'type' => OrderAdjustment::TYPE_CHARGEBACK,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
                'reason' => mb_substr($reason, 0, 255),
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'metadata' => array_filter(['provider_reference' => $providerReference]),
                'occurred_at' => now(),
            ]);

            if ($locked->token_revoked_at === null) {
                $locked->revokeTicketToken($actor, "Chargeback: {$reason}");
            }
            $this->tickets->recordForOrder($locked, TicketCredentialEvent::CHARGEBACK, $actor, $reason);

            AuditLogService::log(action: 'order_chargeback_recorded', targetEntity: 'orders', targetId: (string) $locked->id,
                details: "Chargeback of {$amountMinor} {$locked->currency} minor units recorded on order {$locked->order_number} by {$actor->email}. All ticket credentials revoked. Reason: {$reason}",
                actor: $actor, organizerId: (string) $locked->organizer_id, eventId: (string) $locked->event_id);

            return $locked->fresh() ?? $locked;
        });
    }

    public static function isChargedBack(Order $order): bool
    {
        return OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_CHARGEBACK)->exists();
    }
}
