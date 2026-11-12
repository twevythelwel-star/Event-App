<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\TicketTransfer;
use App\Models\User;
use App\Support\TicketLifecycle;
use Illuminate\Support\Collection;

/**
 * The attendee's "My Tickets" collection: every ticket they hold, grouped by where it is in its life
 * (App\Support\TicketLifecycle), plus the tickets they transferred to someone else.
 */
final class TicketCollectionService
{
    public const TABS = [
        'upcoming' => ['label' => 'Upcoming', 'hint' => 'Every booking for an event still ahead, paid or not yet.'],
        'active' => ['label' => 'Active', 'hint' => 'Paid and issued: open one to show its QR code at the gate.'],
        'past' => ['label' => 'Past', 'hint' => 'Keepsakes from events you went to. They are no longer valid for entry.'],
        'cancelled' => ['label' => 'Cancelled', 'hint' => 'Cancelled, expired, declined or revoked. Not valid for entry.'],
        'refunded' => ['label' => 'Refunded', 'hint' => 'Refunded tickets. Not valid for entry.'],
        'transferred' => ['label' => 'Transferred', 'hint' => 'Tickets you passed to someone else. They are theirs now.'],
    ];

    public function __construct(private readonly OrderPlacementService $orders) {}

    /**
     * @return array{tabs: array<string, Collection<int, Order>|Collection<int, TicketTransfer>>, counts: array<string, int>}
     */
    public function forUser(User $user): array
    {
        $transfers = TicketTransfer::where('from_user_id', $user->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->with(['order.event' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();
        $transferredAway = $transfers->pluck('order_id')->all();

        $orders = Order::withoutGlobalScopes()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (!empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
                if (!empty($user->phone)) {
                    $q->orWhere('customer_phone', $user->phone);
                }
            })
            ->with(['items', 'event' => fn ($q) => $q->withoutGlobalScopes()])
            ->get()
            // Tickets this person passed on belong to the recipient now, whatever contact details still match.
            ->reject(fn (Order $order) => in_array($order->id, $transferredAway, true))
            ->filter(fn (Order $order) => $this->orders->isUserAuthorizedForOrder($order, $user))
            ->values();

        $byState = $orders->groupBy(fn (Order $order) => TicketLifecycle::stateOf($order));
        $soonest = fn (Order $order) => TicketLifecycle::eventMoment($order->event)?->getTimestamp() ?? PHP_INT_MAX;
        $latest = fn (Order $order) => -(TicketLifecycle::eventMoment($order->event)?->getTimestamp() ?? 0);

        $active = ($byState['active'] ?? collect())->sortBy($soonest)->values();
        $tabs = [
            'upcoming' => $active->concat($byState['upcoming'] ?? collect())->sortBy($soonest)->values(),
            'active' => $active,
            'past' => ($byState['past'] ?? collect())->sortBy($latest)->values(),
            'cancelled' => ($byState['cancelled'] ?? collect())->sortByDesc('created_at')->values(),
            'refunded' => ($byState['refunded'] ?? collect())->sortByDesc('created_at')->values(),
            'transferred' => $transfers,
        ];

        return ['tabs' => $tabs, 'counts' => array_map(fn (Collection $c) => $c->count(), $tabs)];
    }

    /** The tab to open first: the one asked for, else upcoming tickets, else keepsakes, else upcoming. */
    public static function initialTab(?string $asked, array $counts): string
    {
        if ($asked !== null && array_key_exists($asked, self::TABS)) {
            return $asked;
        }

        return ($counts['upcoming'] ?? 0) === 0 && ($counts['past'] ?? 0) > 0 ? 'past' : 'upcoming';
    }
}
