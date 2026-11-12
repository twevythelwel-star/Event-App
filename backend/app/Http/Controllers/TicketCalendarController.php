<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPlacementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Add to calendar" for a ticket: an iCalendar (.ics) file with the event's time and place, the ticket code and a
 * link back to the ticket. The admission QR (its secure token) is never in the file: the ticket page shows it.
 * Same access as the ticket itself; someone else's order is not found.
 */
class TicketCalendarController extends Controller
{
    public function __construct(private readonly OrderPlacementService $orders) {}

    public function show(Request $request, Order $order): Response
    {
        abort_unless($this->orders->isUserAuthorizedForOrder($order, $request->user()), 404);
        $order->loadMissing(['event' => fn ($q) => $q->withoutGlobalScopes()]);
        $event = $order->event;
        abort_if($event === null, 404);

        $tz = $event->timezone ?: 'America/New_York';
        $starts = $event->starts_at?->copy();
        $ends = $event->ends_at?->copy();
        if ($starts) {
            $ends ??= $starts->copy()->addHours(3);
            $when = ['DTSTART:' . $starts->utc()->format('Ymd\THis\Z'), 'DTEND:' . $ends->utc()->format('Ymd\THis\Z')];
        } elseif ($event->event_date_iso) {
            $day = \Illuminate\Support\Carbon::parse((string) $event->event_date_iso, $tz);
            $when = ['DTSTART;VALUE=DATE:' . $day->format('Ymd'), 'DTEND;VALUE=DATE:' . $day->copy()->addDay()->format('Ymd')];
        } else {
            abort(404); // no date to put in a calendar yet
        }

        $place = collect([$event->venue_name, $event->street_address, $event->city, $event->state_code])->filter()->implode(', ');
        $lines = array_merge([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//EventBite//Tickets//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $order->id . '@eventbite',
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
        ], $when, [
            'SUMMARY:' . self::escape((string) $event->title),
            'LOCATION:' . self::escape($place),
            'DESCRIPTION:' . self::escape("Ticket {$order->ticket_code}. Show your ticket from EventBite at the entrance: " . route('tickets.show', $order)),
            'URL:' . route('tickets.show', $order),
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $body = implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
        $file = \Illuminate\Support\Str::slug((string) $event->title ?: 'event') . '.ics';

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** RFC 5545 text: backslash, semicolon, comma and newlines escaped. */
    private static function escape(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $text);
    }

    /** RFC 5545 lines are at most 75 octets; longer ones continue on lines starting with a space. */
    private static function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--; // never split a multi-byte character
            }
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
        }

        return $out . $line;
    }
}
