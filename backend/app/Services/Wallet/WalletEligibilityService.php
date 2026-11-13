<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Order;
use App\Models\User;
use App\Support\TicketLifecycle;

/**
 * May this ticket have a wallet pass for this person right now? The same rule for every provider: only the ticket's
 * holder, only while the ticket is valid for entry (paid, not revoked, cancelled, refunded or past). Whether a
 * provider is switched on is the provider's own question (WalletProvider::available), asked by the manager.
 */
final class WalletEligibilityService
{
    /** Reason codes: null when eligible. */
    public function reason(Order $order, ?User $user): ?string
    {
        if ($user === null) {
            return 'signed_out';
        }
        if ($order->user_id !== null && (int) $order->user_id !== (int) $user->id) {
            return 'not_holder';
        }

        return $this->ticketReason($order);
    }

    /** The ticket's own state, whoever is asking. */
    public function ticketReason(Order $order): ?string
    {
        if ($order->checked_in_at !== null) {
            return 'checked_in';
        }

        return match (TicketLifecycle::stateOf($order)) {
            'active' => null,
            'past' => 'past',
            'upcoming' => 'unpaid',
            default => 'void',
        };
    }

    public function eligible(Order $order, ?User $user): bool
    {
        return $this->reason($order, $user) === null;
    }

    /** What to tell the holder, for a reason code. */
    public function message(string $reason, string $label): string
    {
        return match ($reason) {
            'signed_out' => "Sign in to add your ticket to {$label}.",
            'not_holder' => "Only the ticket holder can add this ticket to {$label}.",
            'checked_in' => 'This ticket has already been used for entry.',
            'past' => 'This event is over: the ticket is a keepsake now and isn’t valid for entry.',
            'unpaid' => "Your ticket can be added to {$label} once the payment is confirmed.",
            'unavailable' => "{$label} passes aren’t available yet.",
            default => "This ticket isn’t valid for entry, so it can’t be added to {$label}.",
        };
    }
}
