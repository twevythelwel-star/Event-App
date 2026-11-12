<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Releases only expired, unpaid inventory holds, once, under the same locks used at checkout. */
final class InventoryReservationExpiryService
{
    /** @return array{expired: int, inventory_released: int, held_for_provider: int} */
    public function expire(int $batchSize = 200): array
    {
        $totals = ['expired' => 0, 'inventory_released' => 0, 'held_for_provider' => 0];
        $ids = Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->whereIn('status', ['awaiting_payment', 'payment_submitted', 'under_verification', 'pending_verification', 'rejected'])
            ->whereNull('inventory_released_at')->whereNotNull('reservation_expires_at')
            // Cash-at-pickup orders are held for the event (older ones may still carry a timer).
            ->where(fn ($q) => $q->whereNull('authorized_unpaid')->orWhere('authorized_unpaid', false))
            ->where('reservation_expires_at', '<=', now())->orderBy('reservation_expires_at')
            ->limit(max(1, min(1000, $batchSize)))->pluck('id');

        foreach ($ids as $id) {
            $result = DB::transaction(function () use ($id): string {
                $order = Order::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();
                if (!$order || $order->inventory_released_at || !$order->reservation_expires_at
                    || $order->reservation_expires_at->isFuture()
                    || !in_array($order->status, ['awaiting_payment', 'payment_submitted', 'under_verification', 'pending_verification', 'rejected'], true)
                    || (int) $order->amount_paid_minor > 0 || $order->checked_in_at) {
                    return 'skipped';
                }

                // A processing provider attempt can still settle after a delayed webhook. Never
                // release its stock until provider reconciliation confirms a terminal outcome.
                if (Payment::where('order_id', $order->id)
                    ->whereIn('status', [...Payment::OPEN, Payment::SUCCEEDED])->exists()) {
                    return 'provider';
                }

                $event = Event::withoutGlobalScopes()->whereKey($order->event_id)->lockForUpdate()->first();
                if (!$event) {
                    Log::critical('inventory.reservation_event_missing', ['order_id' => $order->id, 'event_id' => $order->event_id]);
                    return 'skipped';
                }

                $tiers = is_array($event->ticket_tiers) ? $event->ticket_tiers : [];
                $tiersChanged = false;
                $items = $order->items()->orderBy('id')->lockForUpdate()->get();
                foreach ($items as $item) {
                    if ($item->ticket_tier_id !== null) {
                        foreach ($tiers as $index => $tier) {
                            if ((string) ($tier['id'] ?? '') !== (string) $item->ticket_tier_id) continue;
                            $availabilityKey = TicketTierCatalog::availabilityKey($tier);
                            if ($availabilityKey !== null) {
                                $tiers[$index][$availabilityKey] = max(0, (int) $tier[$availabilityKey]) + (int) $item->quantity;
                                $tiersChanged = true;
                            }
                            break;
                        }
                        continue;
                    }

                    if ($item->menu_item_id === null) continue;
                    $menuItem = MenuItem::withoutGlobalScopes()->whereKey($item->menu_item_id)
                        ->where('event_id', $event->id)->lockForUpdate()->first();
                    // Uncapped items are not decremented during checkout and need no restoration.
                    if ($menuItem && $menuItem->available_stock !== null) {
                        $menuItem->available_stock = (int) $menuItem->available_stock + (int) $item->quantity;
                        $menuItem->save();
                    }
                }

                if ($tiersChanged) {
                    $event->ticket_tiers = $tiers;
                    $event->save();
                }
                $order->status = 'expired';
                $order->inventory_released_at = now();
                $order->save();
                \App\Services\AuditLogService::log(action: 'inventory_reservation_expired', targetEntity: 'orders',
                    targetId: (string) $order->id, details: 'Unpaid order reservation expired and its tracked inventory was released.',
                    organizerId: $order->organizer_id, eventId: $order->event_id);

                return 'expired';
            }, 3);

            if ($result === 'expired') {
                $totals['expired']++;
                $totals['inventory_released']++;
                // Tickets came free: offer them to the event's waitlist, first come first served.
                $freedEvent = Event::withoutGlobalScopes()->find(Order::withoutGlobalScopes()->whereKey($id)->value('event_id'));
                if ($freedEvent) {
                    app(WaitlistService::class)->offerFreedTickets($freedEvent);
                }
            } elseif ($result === 'provider') {
                $totals['held_for_provider']++;
            }
        }

        return $totals;
    }
}
