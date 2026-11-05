<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Event;
use Illuminate\Validation\ValidationException;

/**
 * The schedule, venue, map pin and food plan fields of an event, prepared for saving on create and on edit.
 *
 * - The timetable is checked as a whole (EventSchedule), with anything not being changed taken from the event.
 * - A map pin is kept only with a fresh token from MapLocationService. Changing the address without a new pin
 *   clears the old one, since it no longer matches; the host can also remove a pin.
 * - The food plan: the service fee is entered in dollars and stored in minor units, and pickup windows must fall
 *   inside the food service hours.
 */
final class EventDetails
{
    private const ADDRESS_FIELDS = ['street_address', 'venue_building_name', 'city', 'state_code', 'zip_code', 'country_code'];

    /**
     * @param array<string, mixed> $data validated (and address-normalised) fields being saved
     * @return array<string, mixed> $data with the derived columns set and the form-only keys removed
     */
    public static function prepare(array $data, ?Event $event = null): array
    {
        $timezone = (string) ($data['timezone'] ?? $event?->timezone ?? config('events.market.default_timezone', 'America/New_York'));

        $scheduleKeys = ['event_date_iso', 'end_date_iso', 'start_time', 'end_time', 'doors_open_time', 'setup_time',
            'food_service_start_time', 'food_service_end_time', 'ticket_sales_start_at', 'ticket_sales_end_at', 'early_bird_starts_at', 'early_bird_ends_at'];
        $touched = array_intersect($scheduleKeys, array_keys($data));
        if ($touched !== [] || array_key_exists('timezone', $data)) {
            $merged = [];
            foreach ($scheduleKeys as $key) {
                $merged[$key] = array_key_exists($key, $data) ? $data[$key] : self::stored($event, $key, $timezone);
            }
            // Older events stored display times ("4:00 PM"); they are only re-read when the host changes them.
            foreach (['start_time', 'end_time'] as $key) {
                if (!array_key_exists($key, $data) && !preg_match(EventSchedule::TIME, (string) $merged[$key])) {
                    $merged[$key] = null;
                }
            }
            // Only the fields being saved (and the derived instants) are written back.
            $resolved = EventSchedule::resolve($merged, $timezone);
            foreach (array_merge($touched, ['starts_at', 'ends_at']) as $key) {
                if (array_key_exists($key, $resolved)) {
                    $data[$key] = $resolved[$key];
                }
            }
        }

        if (array_key_exists('food_service_fee', $data)) {
            $fee = $data['food_service_fee'];
            $data['food_service_fee_minor'] = ($fee === null || $fee === '') ? 0
                : \App\Support\Money::parse((string) $fee, (string) ($data['currency'] ?? $event?->currency ?? 'USD'))->minor();
        }
        unset($data['food_service_fee']);
        // The form sends a marker with its pickup windows, so removing the last window clears them.
        if (!empty($data['food_pickup_windows_submitted']) && !array_key_exists('food_pickup_windows', $data)) {
            $data['food_pickup_windows'] = [];
        }
        unset($data['food_pickup_windows_submitted']);
        if (array_key_exists('food_pickup_windows', $data)) {
            $data['food_pickup_windows'] = EventSchedule::pickupWindows((array) ($data['food_pickup_windows'] ?? []),
                $data['food_service_start_time'] ?? $event?->food_service_start_time,
                $data['food_service_end_time'] ?? $event?->food_service_end_time) ?: null;
        }
        if (($data['food_fulfillment'] ?? $event?->food_fulfillment) !== 'pickup_and_delivery' && array_key_exists('food_fulfillment', $data)) {
            $data['food_delivery_instructions'] = null;
        }

        $data = self::location($data, $event);
        unset($data['location_token'], $data['clear_location_pin']);

        return self::displayText($data, $event, $timezone);
    }

    /**
     * The date and time as printed on the event page and tickets, written from the schedule when the host left
     * them empty: "Saturday, August 15, 2027" or "Friday, August 14 – Sunday, August 16, 2027", and "6:00 PM – 2:00 AM".
     */
    private static function displayText(array $data, ?Event $event, string $timezone): array
    {
        $date = $data['event_date_iso'] ?? $event?->event_date_iso;
        if (array_key_exists('date_str', $data) && !filled($data['date_str']) || !$event && !filled($data['date_str'] ?? null)) {
            if (filled($date)) {
                $start = \Illuminate\Support\Carbon::parse(substr((string) $date, 0, 10), $timezone);
                $endDate = $data['end_date_iso'] ?? $event?->end_date_iso;
                $end = filled($endDate) ? \Illuminate\Support\Carbon::parse(substr((string) $endDate, 0, 10), $timezone) : null;
                $data['date_str'] = $end && !$end->isSameDay($start)
                    ? $start->format($start->year === $end->year ? 'l, F j' : 'l, F j, Y') . ' – ' . $end->format('l, F j, Y')
                    : $start->format('l, F j, Y');
            } else {
                $data['date_str'] = '';
            }
        }
        if (array_key_exists('time_str', $data) && !filled($data['time_str']) || !$event && !filled($data['time_str'] ?? null)) {
            $twelve = fn (?string $t) => $t && preg_match(EventSchedule::TIME, $t) ? \Illuminate\Support\Carbon::createFromFormat('H:i', $t)->format('g:i A') : null;
            $from = $twelve($data['start_time'] ?? $event?->start_time);
            $to = $twelve($data['end_time'] ?? $event?->end_time);
            $data['time_str'] = $from ? ($to ? "{$from} – {$to}" : $from) : '';
        }

        return $data;
    }

    /** The map pin to save: a verified new one, none, or the existing one while the address is unchanged. */
    private static function location(array $data, ?Event $event): array
    {
        $maps = app(MapLocationService::class);
        $clear = ['latitude' => null, 'longitude' => null, 'location_source' => null, 'location_place_id' => null, 'location_verified_at' => null];

        if (filled($data['location_token'] ?? null)) {
            $pin = $maps->verify((string) $data['location_token']);
            if ($pin === null) {
                throw ValidationException::withMessages(['location_token' => 'The map location could not be confirmed. Look up the address again and choose it from the list.']);
            }

            return array_merge($data, [
                'latitude' => $pin['latitude'],
                'longitude' => $pin['longitude'],
                'location_source' => $pin['location_source'],
                'location_place_id' => $pin['location_place_id'] ?: null,
                'location_verified_at' => now(),
            ]);
        }
        if (!empty($data['clear_location_pin'])) {
            return array_merge($data, $clear);
        }
        // Coordinates typed into a form or API call are never shown as a verified pin.
        unset($data['latitude'], $data['longitude']);
        if ($event && $event->location_verified_at) {
            foreach (self::ADDRESS_FIELDS as $field) {
                if (array_key_exists($field, $data) && (string) ($data[$field] ?? '') !== (string) ($event->{$field} ?? '')) {
                    return array_merge($data, $clear);
                }
            }
        }

        return $data;
    }

    private static function stored(?Event $event, string $key, string $timezone): mixed
    {
        if (!$event) {
            return null;
        }
        $value = $event->{$key};
        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($value)->setTimezone($timezone)->format('Y-m-d H:i');
        }

        return $value === null ? null : (str_ends_with($key, '_iso') ? substr((string) $value, 0, 10) : (string) $value);
    }
}
