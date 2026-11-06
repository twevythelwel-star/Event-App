<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\TicketHold;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\OrderPlacementException;
use Illuminate\Support\Facades\DB;

/**
 * Waitlists for sold-out ticket types. When tickets come free (an unpaid order expires, an order is cancelled, the
 * host adds stock or a release opens), waiting buyers are offered them in the order they joined: the tickets are
 * held for them for a limited time and they get a notice in EventBite. An offer that is not taken up passes to the
 * next person.
 */
final class WaitlistService
{
    public static function offerMinutes(): int
    {
        return max(5, (int) config('commerce.waitlist_offer_minutes', 60));
    }

    public function join(User $user, Event $event, string $tierId, int $quantity): WaitlistEntry
    {
        if (!$event->waitlist_enabled) {
            throw new OrderPlacementException('This event does not keep a waitlist.');
        }
        $tier = collect(is_array($event->ticket_tiers) ? $event->ticket_tiers : [])->first(fn ($t) => (string) ($t['id'] ?? '') === $tierId);
        if (!$tier || ($tier['isActive'] ?? true) === false) {
            throw new OrderPlacementException('That ticket type is not on sale.');
        }
        $quantity = max(1, min(TicketTierCatalog::maxPerOrder($tier) ?: 10, $quantity));
        $available = TicketInventory::availableFor($event, $tier, $user->id);
        if ($available === null || $available >= $quantity) {
            throw new OrderPlacementException('These tickets are available now, so you can buy them straight away.');
        }
        $existing = WaitlistEntry::where('event_id', $event->id)->where('ticket_tier_id', $tierId)->where('user_id', $user->id)
            ->whereIn('status', ['waiting', 'offered'])->first();
        if ($existing) {
            $existing->forceFill(['quantity' => $quantity])->save();

            return $existing;
        }

        return WaitlistEntry::create(['event_id' => $event->id, 'ticket_tier_id' => $tierId, 'user_id' => $user->id, 'quantity' => $quantity, 'status' => 'waiting']);
    }

    public function leave(User $user, string $entryId): void
    {
        $entry = WaitlistEntry::where('user_id', $user->id)->findOrFail($entryId);
        if ($entry->hold_id) {
            TicketHold::whereKey($entry->hold_id)->delete();
        }
        $entry->forceFill(['status' => 'left'])->save();
    }

    /** Position in line (1 = next), or null when not waiting. */
    public function position(WaitlistEntry $entry): ?int
    {
        if ($entry->status !== 'waiting') {
            return null;
        }

        return WaitlistEntry::where('event_id', $entry->event_id)->where('ticket_tier_id', $entry->ticket_tier_id)->where('status', 'waiting')
            ->where('created_at', '<=', $entry->created_at)->count();
    }

    /**
     * Expires offers that were not taken up, then offers free tickets to the people next in line. Safe to run at
     * any time (it is run after reservations expire and orders are cancelled, and on a schedule).
     *
     * @return int offers made
     */
    public function offerFreedTickets(Event $event): int
    {
        if (!$event->waitlist_enabled) {
            return 0;
        }

        return DB::transaction(function () use ($event) {
            $locked = Event::withoutGlobalScopes()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            WaitlistEntry::where('event_id', $locked->id)->where('status', 'offered')->where('offer_expires_at', '<=', now())->update(['status' => 'expired']);

            $offers = 0;
            $sold = TicketPricing::soldByTier($locked);
            $tiers = collect(is_array($locked->ticket_tiers) ? $locked->ticket_tiers : [])->keyBy(fn ($t) => (string) ($t['id'] ?? ''));
            $waiting = WaitlistEntry::where('event_id', $locked->id)->where('status', 'waiting')->orderBy('created_at')->get()->groupBy('ticket_tier_id');
            foreach ($waiting as $tierId => $entries) {
                $tier = $tiers->get((string) $tierId);
                if (!$tier) {
                    continue;
                }
                foreach ($entries as $entry) {
                    $free = TicketInventory::availableFor($locked, $tier, null, $sold);
                    if ($free === null || $free < $entry->quantity) {
                        break; // first come, first served: nobody further back jumps the queue
                    }
                    $expires = now()->addMinutes(self::offerMinutes());
                    $hold = TicketHold::create(['event_id' => $locked->id, 'ticket_tier_id' => (string) $tierId, 'quantity' => $entry->quantity,
                        'user_id' => $entry->user_id, 'source' => 'waitlist', 'expires_at' => $expires]);
                    $entry->forceFill(['status' => 'offered', 'hold_id' => $hold->id, 'offered_at' => now(), 'offer_expires_at' => $expires])->save();
                    $this->notify($locked, $entry, (string) ($tier['name'] ?? 'tickets'));
                    $offers++;
                }
            }

            return $offers;
        });
    }

    /** Marks a waiting buyer's entry as bought once their order takes the tickets. */
    public function markPurchased(Event $event, int $userId, array $tierIds): void
    {
        WaitlistEntry::where('event_id', $event->id)->where('user_id', $userId)->whereIn('ticket_tier_id', $tierIds)
            ->whereIn('status', ['waiting', 'offered'])->update(['status' => 'purchased']);
    }

    private function notify(Event $event, WaitlistEntry $entry, string $tierName): void
    {
        $user = User::find($entry->user_id);
        if (!$user) {
            return;
        }
        $until = $entry->offer_expires_at?->copy()->setTimezone($event->timezone ?: config('app.timezone'))->format('M j, g:i A T');
        // An in-app notice (shown with the account's other notifications, matched by email).
        NotificationLog::create([
            'organizer_id' => $event->organizer_id, 'idempotency_key' => 'waitlist-offer-' . $entry->id . '-' . $entry->offered_at?->getTimestamp(),
            'customer_name' => $user->name, 'channel' => 'in_app', 'recipient' => (string) $user->email, 'trigger' => 'waitlist_offer',
            'title' => "Tickets held for you: {$event->title}", 'status' => 'sent', 'sent_at' => now(),
            'message' => "Good news: {$entry->quantity} {$tierName} " . ($entry->quantity === 1 ? 'ticket is' : 'tickets are') . " held for you for {$event->title} until {$until}. Buy them from the event page before then.",
        ]);
    }
}
