<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\TicketHold;
use App\Services\OrderPlacementException;
use Illuminate\Support\Facades\DB;

/**
 * How many tickets of a type someone can buy right now, and holds while they check out.
 *
 * Available to a buyer = the type's remaining stock, limited by its release schedule, minus what other buyers hold
 * (their checkout holds and waitlist offers). A buyer's own holds are theirs to use. Holds are created with the
 * event row locked, the same lock the order uses, so holds never add up to more than the stock.
 */
final class TicketInventory
{
    /** Minutes a buyer's tickets stay held after they choose them. */
    public static function holdMinutes(): int
    {
        return max(1, (int) config('commerce.ticket_hold_minutes', 10));
    }

    /**
     * @param array<string, int>|null $sold tickets sold per type (TicketPricing::soldByTier), when already known
     * @return int|null null when the type has no limit at all
     */
    public static function availableFor(Event $event, array $tier, ?int $userId, ?array $sold = null): ?int
    {
        $tierId = (string) ($tier['id'] ?? '');
        $stock = TicketTierCatalog::available($tier);
        $sold ??= TicketPricing::soldByTier($event);
        $released = TicketPricing::releasedRemaining($tier, (int) ($sold[$tierId] ?? 0));
        $limits = array_filter([$stock, $released], fn ($v) => $v !== null);
        if ($limits === []) {
            return null;
        }

        return max(0, min($limits) - self::heldByOthers($event, $tierId, $userId));
    }

    public static function heldByOthers(Event $event, string $tierId, ?int $userId): int
    {
        return (int) TicketHold::active()->where('event_id', $event->id)->where('ticket_tier_id', $tierId)
            ->when($userId !== null, fn ($q) => $q->where('user_id', '!=', $userId))->sum('quantity');
    }

    /** @return array<string, array{quantity: int, expires_at: \Illuminate\Support\Carbon, source: string}> this buyer's active holds by type */
    public static function holdsOf(Event $event, int $userId): array
    {
        return TicketHold::active()->where('event_id', $event->id)->where('user_id', $userId)->get()
            ->groupBy('ticket_tier_id')->map(fn ($holds) => [
                'quantity' => (int) $holds->sum('quantity'),
                'expires_at' => $holds->max('expires_at'),
                'source' => $holds->contains('source', 'waitlist') ? 'waitlist' : 'checkout',
            ])->all();
    }

    /**
     * Holds the buyer's chosen tickets for a few minutes (replacing their previous checkout holds), so they are
     * not sold to someone else while the buyer finishes. Throws when not enough are free.
     *
     * @param array<string, int> $quantities
     */
    public static function hold(Event $event, int $userId, array $quantities): void
    {
        DB::transaction(function () use ($event, $userId, $quantities) {
            $locked = Event::withoutGlobalScopes()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            TicketHold::where('event_id', $locked->id)->where('user_id', $userId)->where('source', 'checkout')->delete();
            $tiers = collect(is_array($locked->ticket_tiers) ? $locked->ticket_tiers : [])->keyBy(fn ($t) => (string) ($t['id'] ?? ''));
            $sold = TicketPricing::soldByTier($locked);
            $expires = now()->addMinutes(self::holdMinutes());
            foreach ($quantities as $tierId => $quantity) {
                $quantity = (int) $quantity;
                $tier = $tiers->get((string) $tierId);
                if ($quantity <= 0 || !$tier) {
                    continue;
                }
                $available = self::availableFor($locked, $tier, $userId, $sold);
                if ($available !== null && $available < $quantity) {
                    $name = (string) ($tier['name'] ?? 'tickets');
                    throw new OrderPlacementException($available === 0
                        ? "{$name} is sold out right now." . (TicketPricing::nextRelease($tier) ? ' More are released later.' : '')
                        : "Only {$available} {$name} can be bought right now.");
                }
                // A waitlist offer the buyer already holds covers part of what they chose.
                $own = (int) TicketHold::active()->where('event_id', $locked->id)->where('user_id', $userId)->where('ticket_tier_id', (string) $tierId)->sum('quantity');
                if ($quantity > $own) {
                    TicketHold::create(['event_id' => $locked->id, 'ticket_tier_id' => (string) $tierId, 'quantity' => $quantity - $own,
                        'user_id' => $userId, 'source' => 'checkout', 'expires_at' => $expires]);
                }
            }
        });
    }

    /** Releases a buyer's holds for an event once their order has taken the tickets (or they leave). */
    public static function release(Event $event, int $userId): void
    {
        TicketHold::where('event_id', $event->id)->where('user_id', $userId)->delete();
    }
}
