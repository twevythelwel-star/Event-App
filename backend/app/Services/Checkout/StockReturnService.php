<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;

/**
 * Puts stock back when an order is fully refunded, so refunded tickets and food can be sold again.
 *
 * Only what the customer did not use comes back: tickets are returned only if the ticket was never checked in,
 * and food only for portions not yet handed over. Rows are locked the same way checkout locks them
 * (OrderPlacementService), so a return and a sale cannot overwrite each other.
 * Partial refunds return nothing: they are amounts, not specific items.
 *
 * Must be called inside the database transaction that marks the order refunded.
 */
final class StockReturnService
{
    /** @return array{tickets: int, portions: int} what was returned, for the audit record */
    public function returnForRefund(Order $order): array
    {
        $order->loadMissing('items');
        $returned = ['tickets' => 0, 'portions' => 0];

        $ticketLines = $order->items->filter(fn ($item) => $item->ticket_tier_id !== null);
        if ($ticketLines->isNotEmpty() && $order->checked_in_at === null) {
            $event = Event::withoutGlobalScopes()->where('id', $order->event_id)->lockForUpdate()->first();
            if ($event && is_array($event->ticket_tiers)) {
                $tiers = $event->ticket_tiers;
                foreach ($ticketLines as $line) {
                    foreach ($tiers as $index => $tier) {
                        if (!is_array($tier) || (string) ($tier['id'] ?? '') !== (string) $line->ticket_tier_id) {
                            continue;
                        }
                        $key = TicketTierCatalog::availabilityKey($tier);
                        if ($key !== null) {
                            $tiers[$index][$key] = (int) $tier[$key] + (int) $line->quantity;
                            $returned['tickets'] += (int) $line->quantity;
                        }
                        break;
                    }
                }
                $event->ticket_tiers = $tiers;
                $event->save();
            }
        }

        foreach ($order->items->filter(fn ($item) => $item->menu_item_id !== null) as $line) {
            $unused = max(0, (int) $line->quantity - (int) $line->collected_quantity);
            if ($unused === 0) {
                continue;
            }
            $menuItem = MenuItem::withoutGlobalScopes()->where('id', $line->menu_item_id)->lockForUpdate()->first();
            if ($menuItem && $menuItem->available_stock !== null) {
                $menuItem->increment('available_stock', $unused);
                $returned['portions'] += $unused;
            }
        }

        return $returned;
    }
}
