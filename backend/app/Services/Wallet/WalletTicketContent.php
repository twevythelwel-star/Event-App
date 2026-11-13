<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Event;
use App\Models\Order;
use App\Models\WalletPass;
use App\Services\ReceiptService;
use App\Support\TicketLifecycle;
use Illuminate\Support\Carbon;

/**
 * Provider-neutral content of a ticket pass (what Google and Samsung passes show; Apple builds its own pass.json from
 * the same record), plus the fingerprints the WalletPassManager compares to decide whether a provider is out of date.
 * Fingerprints are hashes of what a pass shows and never contain the admission token itself.
 */
final class WalletTicketContent
{
    /** @return array<string, mixed> */
    public static function of(WalletPass $pass, Order $order): array
    {
        $event = $order->event;
        $state = WalletTicketState::of($pass, $order);
        $tz = $event?->timezone ?: 'America/New_York';
        $tickets = $order->relationLoaded('items') ? $order->items->where('category', 'Tickets') : collect();
        $ticketType = $tickets->isNotEmpty()
            ? $tickets->map(fn ($i) => ($i->quantity > 1 ? $i->quantity . ' × ' : '') . $i->name)->join(', ')
            : 'Event pass';

        return [
            'title' => (string) ($event?->title ?: 'EventBite event'),
            'status' => $state['status'],
            'state' => $state['code'],
            'voided' => $state['voided'],
            'barcode' => $state['barcode'],
            'starts_at' => self::start($event, $tz),
            'ends_at' => $event?->ends_at?->copy(),
            'over_at' => TicketLifecycle::overAt($event),
            'timezone' => $tz,
            'venue' => (string) ($event?->venue_name ?: ($event ? ReceiptService::venue($event) : '')),
            'address' => $event ? collect([$event->street_address, $event->city, $event->state_code, $event->zip_code])->filter()->implode(', ') : '',
            'latitude' => is_numeric($event?->latitude) ? (float) $event->latitude : null,
            'longitude' => is_numeric($event?->longitude) ? (float) $event->longitude : null,
            'ticket_type' => $ticketType,
            'ticket_code' => (string) $order->ticket_code,
            'order_number' => (string) $order->order_number,
            'holder' => (string) ($order->customer_name ?? ''),
            'organizer' => (string) ($event?->organizer_name ?? ''),
            'ticket_url' => route('tickets.show', $order),
        ];
    }

    /** The admission token for the barcode, only while the pass may carry one. Never stored, hashed or logged here. */
    public static function barcodeValue(WalletPass $pass, Order $order): ?string
    {
        return WalletTicketState::of($pass, $order)['barcode'] ? $order->deliverTicketQrToken() : null;
    }

    /** Hash of the event fields a pass shows: changes when the time, venue, title or status changes. */
    public static function eventHash(?Event $event): string
    {
        $values = [];
        foreach (WalletPassService::EVENT_FIELDS as $field) {
            $value = $event?->getAttribute($field);
            $values[$field] = $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
        }

        return hash('sha256', json_encode($values));
    }

    /** Hash of everything a pass shows, including which admission token version it carries (not the token). */
    public static function fingerprint(WalletPass $pass, Order $order): string
    {
        $state = WalletTicketState::of($pass, $order);

        return hash('sha256', json_encode([
            'state' => $state['code'],
            'event' => self::eventHash($order->event),
            'token_version' => (int) $order->token_version,
            'token_hash' => (string) $order->secure_ticket_token_hash,
            'holder' => (string) $order->customer_name,
            'user' => $order->user_id,
            'checked_in' => $order->checked_in_at !== null,
        ]));
    }

    private static function start(?Event $event, string $tz): ?Carbon
    {
        if ($event?->starts_at) {
            return $event->starts_at->copy();
        }
        $date = substr((string) ($event?->event_date_iso ?? ''), 0, 10);

        return $date !== '' ? Carbon::parse($date, $tz) : null;
    }
}
