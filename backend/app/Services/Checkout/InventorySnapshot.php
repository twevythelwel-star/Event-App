<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\OrderItem;
use App\Models\TicketHold;
use Illuminate\Support\Facades\DB;

/**
 * Tickets sold and held for many events, read in two grouped queries, for pages that list events (the homepage,
 * the events index). Without it, every card asks once per ticket type, which is hundreds of queries per page.
 *
 * Request-scoped and read-only:
 *  - only events a listing explicitly prefetched are answered from here; everything else queries as before;
 *  - never used inside a database transaction opened after the prefetch (checkout locks the event row and must count live);
 *  - cleared whenever a ticket hold, order or order line is written, so a page never sees stale numbers.
 */
final class InventorySnapshot
{
    /** @var array<string, array<string, int>> event id => ticket type id => sold */
    private array $sold = [];

    /** @var array<string, array<string, int>> event id => ticket type id => held (by anyone) */
    private array $held = [];

    /** The transaction depth the numbers were read at; a transaction opened since then (checkout) counts live. */
    private ?int $level = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    /** @param iterable<Event> $events */
    public function prefetch(iterable $events): void
    {
        $ids = [];
        foreach ($events as $event) {
            if ($event instanceof Event && $event->exists && is_array($event->ticket_tiers) && $event->ticket_tiers !== []) {
                $ids[(string) $event->id] = true;
            }
        }
        $ids = array_keys(array_diff_key($ids, $this->sold));
        if ($ids === []) {
            return;
        }

        $this->level ??= DB::transactionLevel();
        foreach ($ids as $id) {
            $this->sold[$id] = [];
            $this->held[$id] = [];
        }

        OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.event_id', $ids)
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', TicketPricing::RELEASED_STATUSES)
            ->whereNull('orders.inventory_released_at')
            ->whereNotNull('order_items.ticket_tier_id')
            ->groupBy('orders.event_id', 'order_items.ticket_tier_id')
            ->select('orders.event_id', 'order_items.ticket_tier_id', DB::raw('SUM(order_items.quantity) as sold'))
            ->get()
            ->each(function ($row) {
                $this->sold[(string) $row->event_id][(string) $row->ticket_tier_id] = (int) $row->sold;
            });

        TicketHold::active()
            ->whereIn('event_id', $ids)
            ->groupBy('event_id', 'ticket_tier_id')
            ->select('event_id', 'ticket_tier_id', DB::raw('SUM(quantity) as held'))
            ->get()
            ->each(function ($row) {
                $this->held[(string) $row->event_id][(string) $row->ticket_tier_id] = (int) $row->held;
            });
    }

    /** @return array<string, int>|null null when this event was not prefetched (or can't be answered from here) */
    public function soldByTier(Event $event): ?array
    {
        return $this->usable() ? ($this->sold[(string) $event->id] ?? null) : null;
    }

    /** Tickets of a type held by anyone; null when not prefetched. Holds "by others" than a given buyer always query. */
    public function heldByAnyone(Event $event, string $tierId): ?int
    {
        if (!$this->usable() || !isset($this->held[(string) $event->id])) {
            return null;
        }

        return $this->held[(string) $event->id][$tierId] ?? 0;
    }

    public function flush(): void
    {
        $this->sold = [];
        $this->held = [];
        $this->level = null;
    }

    private function usable(): bool
    {
        return $this->level !== null && DB::transactionLevel() === $this->level;
    }
}
