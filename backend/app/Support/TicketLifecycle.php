<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Where a ticket is in its life, for the attendee's "My Tickets" collection and the ticket page:
 *
 *   upcoming  - the event is still ahead and the order stands (paid or still being paid)
 *   active    - upcoming and issued for admission (paid, QR not revoked)
 *   past      - the event is over: the ticket is kept as a keepsake and is never valid for admission again
 *   cancelled - cancelled, rejected, expired or revoked
 *   refunded  - fully refunded
 *
 * "Transferred" is not a state of an order the attendee still holds: once a transfer is accepted the order belongs
 * to the recipient, so the collection lists the sender's accepted transfers separately (App\Models\TicketTransfer).
 */
final class TicketLifecycle
{
    public const CANCELLED_STATUSES = ['cancelled', 'rejected', 'expired'];

    public const SETTLED_STATUSES = ['paid', 'partially_refunded'];

    /** Hours after the scheduled end (or after the start when no end is set) before the event counts as over. */
    private const GRACE_AFTER_END_HOURS = 3;

    private const ASSUMED_LENGTH_HOURS = 12;

    /**
     * Whether the event is over. The same rule closes admission at the gate (TicketVerificationService,
     * EventWorkerService) and expires Apple Wallet passes, so a keepsake can never admit anyone. An event with no
     * date at all is never over.
     */
    public static function eventIsOver(?Event $event, ?Carbon $now = null): bool
    {
        $over = self::overAt($event);

        return $over !== null && ($now ?? now())->greaterThanOrEqualTo($over);
    }

    /**
     * The moment the event counts as over: a short grace after its end; with no end, a usual length after its start;
     * with only a date, 6 AM local the next day (a late-night event still scans after midnight).
     */
    public static function overAt(?Event $event): ?Carbon
    {
        if ($event === null) {
            return null;
        }
        if ($event->ends_at) {
            return $event->ends_at->copy()->addHours(self::GRACE_AFTER_END_HOURS)->addSecond();
        }
        if ($event->starts_at) {
            return $event->starts_at->copy()->addHours(self::ASSUMED_LENGTH_HOURS + self::GRACE_AFTER_END_HOURS)->addSecond();
        }
        $date = substr((string) ($event->event_date_iso ?? ""), 0, 10);
        if ($date === "") {
            return null;
        }

        return Carbon::parse($date, $event->timezone ?: "America/New_York")->addDay()->setTime(6, 0);
    }

    public static function stateOf(Order $order): string
    {
        $status = (string) $order->status;

        return match (true) {
            $status === 'refunded' => 'refunded',
            $order->token_revoked_at !== null, in_array($status, self::CANCELLED_STATUSES, true) => 'cancelled',
            self::eventIsOver($order->event) => 'past',
            in_array($status, self::SETTLED_STATUSES, true) => 'active',
            default => 'upcoming',
        };
    }

    /** A past ticket that stood (not cancelled or refunded): shown as a keepsake, never as an admission credential. */
    public static function isKeepsake(Order $order): bool
    {
        return self::stateOf($order) === 'past';
    }

    /** When the event happens, for sorting: start, else the event date, else never. */
    public static function eventMoment(?Event $event): ?Carbon
    {
        if ($event?->starts_at) {
            return $event->starts_at->copy();
        }
        $date = substr((string) ($event?->event_date_iso ?? ''), 0, 10);

        return $date !== '' ? Carbon::parse($date, $event->timezone ?: 'America/New_York') : null;
    }
}
