<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Money;
use App\Support\PaymentStatusPresenter;
use Illuminate\Support\Carbon;

/**
 * One receipt for an order, in U.S. formatting, built only from what the order recorded: the event as it is now,
 * the lines and charges frozen when the order was placed, and the payment state it is in. Used by the receipt
 * emails; the ticket page shows the same facts.
 *
 * It never carries the ticket's secret QR token: the admission QR is shown on the ticket page, and only once
 * the payment is settled.
 */
class ReceiptService
{
    /**
     * @return array<string, mixed>
     */
    public function forOrder(Order $order): array
    {
        $order->loadMissing(['event', 'items', 'charges']);
        $event = $order->event;
        $currency = $order->currency ?: 'USD';
        $money = fn (int $minor) => Money::ofMinor($minor, $currency)->display();

        $lines = ['tickets' => [], 'food' => []];
        foreach ($order->items as $item) {
            $isTicket = $item->ticket_tier_id !== null || $item->category === 'Tickets';
            $lines[$isTicket ? 'tickets' : 'food'][] = [
                'name' => (string) $item->name,
                'details' => trim(implode(' · ', array_filter([self::options($item->selected_options_summary), (string) $item->special_instructions]))),
                'quantity' => (int) $item->quantity,
                'unit_price' => $money((int) $item->unit_price_minor), // already includes chosen options
                'line_total' => $money((int) $item->line_total_minor),
            ];
        }

        $charges = [];
        foreach ($order->charges as $charge) {
            $charges[] = [
                'label' => (string) $charge->label,
                'kind' => (string) $charge->kind,
                'amount' => $money($charge->kind === 'discount' ? -abs((int) $charge->amount_minor) : (int) $charge->amount_minor),
            ];
        }

        $settled = in_array($order->status, ['paid', 'partially_refunded'], true);
        $manual = in_array($order->method, ['bank', 'zelle', 'cashapp'], true);

        return [
            'event_name' => $event?->title ?? 'Event',
            'event_date' => self::eventDate($event),
            'event_time' => self::eventTime($event),
            'venue' => self::venue($event),
            'order_reference' => (string) $order->order_number,
            'ticket_code' => (string) $order->ticket_code,
            'placed_at' => $order->created_at ? $order->created_at->copy()->setTimezone(self::timezone($event))->format('F j, Y g:i A T') : null,
            'currency' => $currency,
            'tickets' => $lines['tickets'],
            'food' => $lines['food'],
            'subtotal' => $money((int) $order->subtotal_minor),
            'charges' => $charges,
            'total' => $money((int) $order->total_minor),
            'amount_paid' => $money((int) $order->amount_paid_minor),
            'refunded' => (int) $order->refunded_minor > 0 ? $money((int) $order->refunded_minor) : null,
            'balance_due' => $order->balanceMinor() > 0 ? $money($order->balanceMinor()) : null,
            'payment_status' => PaymentStatusPresenter::label($order->status),
            'payment_settled' => $settled,
            'payment_method' => PaymentStatusPresenter::methodLabel($order->method),
            // The reference the customer gave for a transfer; there is no processor transaction id yet.
            'payment_reference' => $manual && $order->payment_reference ? (string) $order->payment_reference : null,
            'verified_at' => $settled && $order->verified_at ? $order->verified_at->copy()->setTimezone(self::timezone($event))->format('F j, Y g:i A T') : null,
            'ticket_url' => route('tickets.show', $order->id),
        ];
    }

    /** Plain-text version of the receipt, for the text part of emails. */
    public function toText(array $r): string
    {
        $out = ["Receipt for order {$r['order_reference']}", $r['event_name']];
        $out[] = trim(implode(' · ', array_filter([$r['event_date'], $r['event_time']])));
        if ($r['venue']) {
            $out[] = $r['venue'];
        }
        $out[] = '';
        foreach (['tickets' => 'Tickets', 'food' => 'Food'] as $key => $heading) {
            if ($r[$key] === []) {
                continue;
            }
            $out[] = $heading . ':';
            foreach ($r[$key] as $line) {
                $out[] = "  {$line['quantity']} × {$line['name']} @ {$line['unit_price']} = {$line['line_total']}" . ($line['details'] ? " ({$line['details']})" : '');
            }
        }
        $out[] = "Subtotal: {$r['subtotal']}";
        foreach ($r['charges'] as $charge) {
            $out[] = "{$charge['label']}: {$charge['amount']}";
        }
        $out[] = "Total: {$r['total']} {$r['currency']}";
        $out[] = "Paid: {$r['amount_paid']}" . ($r['refunded'] ? " · Refunded: {$r['refunded']}" : '') . ($r['balance_due'] ? " · Balance due: {$r['balance_due']}" : '');
        $out[] = "Payment: {$r['payment_status']} ({$r['payment_method']})" . ($r['payment_reference'] ? " · Reference {$r['payment_reference']}" : '');
        $out[] = "Ticket code: {$r['ticket_code']}";
        $out[] = "Your ticket: {$r['ticket_url']}";

        return implode("\n", $out);
    }

    /** The chosen options as one line, e.g. "Size: Large, Extra sauce". The column holds a list or a text. */
    private static function options(mixed $summary): string
    {
        if (!is_array($summary)) {
            return trim((string) $summary);
        }
        $parts = [];
        foreach ($summary as $key => $value) {
            $text = is_array($value) ? implode(', ', array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value))) : (string) $value;
            $parts[] = is_string($key) && $text !== '' ? "{$key}: {$text}" : $text;
        }

        return implode(', ', array_filter($parts));
    }

    public static function timezone(?\App\Models\Event $event): string
    {
        return $event?->timezone ?: (string) config('events.market.default_timezone', 'America/New_York');
    }

    /** e.g. "Saturday, November 21, 2026" */
    public static function eventDate(?\App\Models\Event $event): ?string
    {
        $iso = substr((string) ($event?->event_date_iso ?? ''), 0, 10);
        if ($iso !== '') {
            try {
                return Carbon::parse($iso, self::timezone($event))->format('l, F j, Y');
            } catch (\Throwable) {
                // fall through to the text the host entered
            }
        }

        return $event?->date_str ?: null;
    }

    /** The host's time text with the event's time-zone abbreviation for that date, e.g. "6:00 PM – 11:00 PM EST". */
    public static function eventTime(?\App\Models\Event $event): ?string
    {
        $time = trim((string) ($event?->time_str ?? ''));
        if ($time === '') {
            return null;
        }
        $iso = substr((string) ($event?->event_date_iso ?? ''), 0, 10);
        try {
            $abbr = Carbon::parse(($iso !== '' ? $iso : 'now') . ' 12:00', self::timezone($event))->format('T');
        } catch (\Throwable) {
            $abbr = '';
        }

        return $abbr !== '' && !str_contains($time, $abbr) ? "{$time} {$abbr}" : $time;
    }

    /** Venue, street, City, ST ZIP */
    public static function venue(?\App\Models\Event $event): ?string
    {
        if (!$event) {
            return null;
        }
        $cityLine = trim(implode(' ', array_filter([
            trim(implode(', ', array_filter([$event->city, $event->state_code]))),
            $event->zip_code,
        ])));
        $parts = array_filter([$event->venue_name, $event->street_address, $cityLine]);

        return $parts ? implode(', ', $parts) : ($event->venue_address ?: null);
    }
}
