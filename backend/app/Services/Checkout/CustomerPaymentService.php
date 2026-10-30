<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OrderPlacementException;
use App\Services\OrderPlacementService;

/**
 * The customer's side of a transfer payment: telling the organiser "I've sent it, here's the
 * reference". This is a confirmation, not a verification: the order moves to
 * payment_submitted, and only the organiser's payment desk can mark it paid.
 */
final class CustomerPaymentService
{
    public function __construct(private readonly OrderPlacementService $orders)
    {
    }

    public function submitReference(User $customer, Order $order, string $reference, ?string $senderName): Order
    {
        if (!$this->orders->isUserAuthorizedForOrder($order, $customer)) {
            throw new OrderPlacementException('This order belongs to another account.');
        }
        if ($order->method === 'cash') {
            throw new OrderPlacementException('Cash orders are paid at pickup; there is no transfer reference to send.');
        }
        if (!in_array($order->status, ['awaiting_payment', 'payment_submitted'], true)) {
            throw new OrderPlacementException('This order is no longer waiting for payment details.');
        }

        $reference = trim($reference);
        $previous = $order->status;
        $order->forceFill([
            'payment_reference' => $reference,
            'sender_name' => $senderName !== null && trim($senderName) !== '' ? trim($senderName) : $order->sender_name,
            'status' => 'payment_submitted',
        ])->save();

        AuditLogService::log(
            action: 'payment_reference_submitted',
            targetEntity: 'orders',
            targetId: (string) $order->id,
            details: "Customer sent a transfer reference for order {$order->order_number} ({$previous} -> payment_submitted).",
            actor: $customer,
            organizerId: (string) $order->organizer_id,
            eventId: $order->event_id ? (string) $order->event_id : null,
        );

        return $order;
    }
}
