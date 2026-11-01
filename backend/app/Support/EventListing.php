<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Services\Checkout\TicketTierCatalog;

/**
 * What an event card or listing may say about an event, derived only from the event's own data.
 *
 * The availability status is never asserted beyond what the data supports: "Sold out" only when every ticket
 * type has a stock limit and none is left; "Tickets on sale" only when at least one ticket type is in its sale
 * window with stock remaining (or no stock limit); and ordering states come from the organiser's own switches.
 */
final class EventListing
{
    /**
     * @return array{key: string, label: string, tone: string}
     */
    public static function availability(Event $event): array
    {
        $status = static fn (string $key, string $label, string $tone) => compact('key', 'label', 'tone');

        if ($event->status === 'cancelled') {
            return $status('cancelled', 'Cancelled', 'danger');
        }
        if (self::hasEnded($event)) {
            return $status('ended', 'Event has ended', 'neutral');
        }
        if (!$event->isOrderingAllowed()) {
            return $status('closed', 'Ordering closed', 'warning');
        }

        $tiers = TicketTierCatalog::sellable($event);
        if ($tiers !== []) {
            if (collect($tiers)->contains(fn (array $t) => $t['on_sale'])) {
                return $status('on_sale', 'Tickets on sale', 'success');
            }
            if (collect($tiers)->every(fn (array $t) => $t['sold_out'])) {
                return $status('sold_out', 'Sold out', 'danger');
            }
            if (collect($tiers)->every(fn (array $t) => $t['sale_window'] === 'ended' || $t['sold_out'])) {
                return $status('sales_ended', 'Ticket sales ended', 'neutral');
            }

            return $status('not_yet', 'Tickets not on sale yet', 'neutral');
        }

        if ($event->supports_food_ordering && $event->menuItems->where('is_active', true)->isNotEmpty()) {
            return $status('food', 'Food ordering open', 'success');
        }

        return $status('unavailable', 'Not on sale', 'neutral');
    }

    /** The cheapest ticket that can be bought right now, in minor units; null when none is on sale. */
    public static function fromPriceMinor(Event $event): ?int
    {
        $prices = collect(TicketTierCatalog::sellable($event))->where('on_sale', true)->pluck('price_minor');

        return $prices->isEmpty() ? null : (int) $prices->min();
    }

    public static function fromPriceText(Event $event): ?string
    {
        $minor = self::fromPriceMinor($event);
        if ($minor === null) {
            return null;
        }

        return $minor === 0 ? 'Free entry' : 'From ' . Money::formatMinor($minor, (string) ($event->currency ?: Currency::defaultCode()));
    }

    public static function location(Event $event): string
    {
        return trim(implode(', ', array_filter([$event->city, $event->state_code])));
    }

    /** The event date has passed in the event's own time zone. */
    public static function hasEnded(Event $event): bool
    {
        $date = substr((string) ($event->event_date_iso ?? ''), 0, 10);
        if ($date === '') {
            return false;
        }

        return $date < now($event->timezone ?: 'America/New_York')->toDateString();
    }
}
