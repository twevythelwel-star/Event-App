<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketTransfer;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OrderPlacementException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Passing tickets to someone else, securely:
 * - only the order's owner can start a transfer, only for a paid order not yet used at the gate, before the event;
 * - the recipient must sign in with the email the transfer was sent to, and accepts with a one-time link (only a
 *   hash of its token is stored);
 * - on acceptance the order moves to the recipient and its ticket QR token is replaced, so the old QR code (a
 *   screenshot the sender kept, for example) no longer gets anyone in.
 */
final class TicketTransferService
{
    public const HOURS = 72;

    /** @return array{transfer: TicketTransfer, link: string} the transfer and the link to give the recipient */
    public function start(User $owner, Order $order, string $email): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new OrderPlacementException('Enter the email address of the person receiving the tickets.');
        }
        if ((int) $order->user_id !== (int) $owner->id) {
            throw new OrderPlacementException('Only the ticket holder can transfer these tickets.');
        }
        if ($email === mb_strtolower((string) $owner->email)) {
            throw new OrderPlacementException('These tickets are already yours.');
        }
        $this->assertTransferable($order);
        if (TicketTransfer::where('order_id', $order->id)->where('status', 'pending')->where('expires_at', '>', now())->exists()) {
            throw new OrderPlacementException('A transfer of these tickets is already waiting to be accepted. Cancel it first.');
        }

        $token = Str::random(48);
        $transfer = TicketTransfer::create([
            'order_id' => $order->id, 'from_user_id' => $owner->id, 'to_email' => $email,
            'token_hash' => hash('sha256', $token), 'status' => 'pending', 'expires_at' => now()->addHours(self::HOURS),
        ]);
        $this->audit($owner, $order, 'ticket_transfer_started', "Transfer of {$order->ticket_code} offered to {$email}.");

        return ['transfer' => $transfer, 'link' => route('tickets.transfer.accept', ['token' => $token])];
    }

    public function cancel(User $owner, string $transferId): void
    {
        $transfer = TicketTransfer::where('from_user_id', $owner->id)->where('status', 'pending')->findOrFail($transferId);
        $transfer->forceFill(['status' => 'cancelled'])->save();
    }

    public function findByToken(string $token): ?TicketTransfer
    {
        return TicketTransfer::where('token_hash', hash('sha256', $token))->first();
    }

    public function accept(User $recipient, string $token): Order
    {
        return DB::transaction(function () use ($recipient, $token) {
            $transfer = TicketTransfer::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (!$transfer || !$transfer->isOpen()) {
                throw new OrderPlacementException('This transfer link has expired, was cancelled or was already used.');
            }
            if (mb_strtolower((string) $recipient->email) !== $transfer->to_email) {
                throw new OrderPlacementException("This transfer was sent to {$transfer->to_email}. Sign in with that email address to accept it.");
            }
            $order = Order::withoutGlobalScopes()->whereKey($transfer->order_id)->lockForUpdate()->firstOrFail();
            if ((int) $order->user_id !== (int) $transfer->from_user_id) {
                throw new OrderPlacementException('These tickets have changed hands since the transfer was offered.');
            }
            $this->assertTransferable($order);

            $order->forceFill([
                'user_id' => $recipient->id,
                'customer_name' => $recipient->name,
                'email' => $recipient->email,
                'customer_phone' => $recipient->phone ?: $order->customer_phone,
                // A new QR token: the sender's copy of the old QR code stops working.
                'secure_ticket_token' => 'tok_' . Str::random(32),
            ])->save();
            $transfer->forceFill(['status' => 'accepted', 'to_user_id' => $recipient->id, 'accepted_at' => now()])->save();
            $this->audit($recipient, $order, 'ticket_transfer_accepted', "Tickets {$order->ticket_code} transferred to {$recipient->email}.");

            return $order;
        });
    }

    public function assertTransferable(Order $order): void
    {
        $event = Event::withoutGlobalScopes()->find($order->event_id);
        if (!$event || !$event->allow_ticket_transfers) {
            throw new OrderPlacementException('The host does not allow ticket transfers for this event.');
        }
        if (!in_array($order->status, ['paid', 'partially_refunded'], true)) {
            throw new OrderPlacementException('Only paid tickets can be transferred.');
        }
        if ($order->checked_in_at) {
            throw new OrderPlacementException('These tickets have already been used at the gate.');
        }
        if ($event->ends_at && $event->ends_at->isPast()) {
            throw new OrderPlacementException('This event is over.');
        }
    }

    private function audit(User $actor, Order $order, string $action, string $details): void
    {
        AuditLogService::log(action: $action, targetEntity: 'orders', targetId: (string) $order->id, details: $details,
            actor: $actor, organizerId: (string) $order->organizer_id, eventId: (string) $order->event_id);
    }
}
