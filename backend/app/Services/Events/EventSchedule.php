<?php

declare(strict_types=1);

namespace App\Services\Events;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * An event's timetable, read in the event's own time zone and checked for order.
 *
 * Dates and times are entered as the host sees them locally (a start date, an optional end date, HH:MM times).
 * From them come exact UTC instants (starts_at, ends_at) so later checks never guess:
 * - With no end date, an end time at or before the start time means the event runs past midnight and ends the
 *   next day (a 9 PM – 2 AM party).
 * - With an end date, the event may run several days; it must still end after it starts.
 * - Doors open and setup happen on the start date, before the start. Food service starts no earlier than the
 *   doors (or the start) and may itself run past midnight; it must finish by the end of the event.
 * - Ticket sales and the early-bird period are date-times in the event's time zone: sales close no later than
 *   the end of the event, and early-bird pricing ends inside the sales period, before the event starts.
 */
final class EventSchedule
{
    public const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    private const MAX_DAYS = 31;

    /**
     * The validated schedule fields with starts_at / ends_at (UTC) and the sale instants in UTC.
     *
     * @param array<string, mixed> $data Event fields; the schedule keys may be absent or empty.
     * @return array<string, mixed> Only the schedule keys, normalised.
     */
    public static function resolve(array $data, string $timezone): array
    {
        $errors = [];
        $out = [];
        $date = self::blank($data['event_date_iso'] ?? null) ? null : substr((string) $data['event_date_iso'], 0, 10);
        $endDate = self::blank($data['end_date_iso'] ?? null) ? null : substr((string) $data['end_date_iso'], 0, 10);
        $start = self::time($data, 'start_time', $errors);
        $end = self::time($data, 'end_time', $errors);
        $doors = self::time($data, 'doors_open_time', $errors);
        $setup = self::time($data, 'setup_time', $errors);
        $foodStart = self::time($data, 'food_service_start_time', $errors);
        $foodEnd = self::time($data, 'food_service_end_time', $errors);
        foreach (['start_time' => $start, 'end_time' => $end, 'doors_open_time' => $doors, 'setup_time' => $setup,
                     'food_service_start_time' => $foodStart, 'food_service_end_time' => $foodEnd] as $key => $value) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $value;
            }
        }
        if (array_key_exists('end_date_iso', $data)) {
            $out['end_date_iso'] = $endDate;
        }

        $startsAt = $endsAt = null;
        if ($date !== null && $endDate !== null && $endDate < $date) {
            $errors['end_date_iso'] = 'The end date cannot be before the start date.';
        }
        if ($endDate !== null && $endDate !== $date && $end === null && $start !== null) {
            $errors['end_time'] = 'Give an end time for an event that runs over more than one day.';
        }
        if ($date !== null && $start !== null) {
            $startsAt = self::at($date, $start, $timezone);
            if ($end !== null) {
                $endsAt = self::at($endDate ?? $date, $end, $timezone);
                if ($endsAt->lessThanOrEqualTo($startsAt)) {
                    if ($endDate === null) {
                        $endsAt = $endsAt->addDay(); // Past midnight: ends the next morning.
                    } elseif (!isset($errors['end_date_iso'])) {
                        $errors['end_time'] = 'The event must end after it starts. For an event that runs past midnight, leave the end date empty or set it to the next day.';
                    }
                }
                if ($endsAt->diffInDays($startsAt, true) > self::MAX_DAYS) {
                    $errors['end_date_iso'] = 'An event can run for at most ' . self::MAX_DAYS . ' days. Create separate events for longer runs.';
                }
            }

            $doorsAt = null;
            if ($doors !== null) {
                $doorsAt = self::at($date, $doors, $timezone);
                if ($doorsAt->greaterThan($startsAt)) {
                    $errors['doors_open_time'] = 'Doors must open at or before the start time.';
                } elseif ($doorsAt->diffInHours($startsAt, true) > 12) {
                    $errors['doors_open_time'] = 'Doors should open no more than 12 hours before the start.';
                }
            }
            if ($setup !== null) {
                $setupAt = self::at($date, $setup, $timezone);
                $firstGuest = $doorsAt && $doorsAt->lessThan($startsAt) ? $doorsAt : $startsAt;
                if ($setupAt->greaterThanOrEqualTo($firstGuest)) {
                    $errors['setup_time'] = 'Setup must begin before doors open' . ($doorsAt ? '' : ' and the event starts') . '.';
                }
            }

            if ($foodStart !== null || $foodEnd !== null) {
                if ($foodStart === null || $foodEnd === null) {
                    $errors[$foodStart === null ? 'food_service_start_time' : 'food_service_end_time'] = 'Give both the food service start and end times, or neither.';
                } else {
                    $earliest = $doorsAt && $doorsAt->lessThan($startsAt) ? $doorsAt : $startsAt;
                    $foodStartAt = self::at($date, $foodStart, $timezone);
                    if ($foodStartAt->lessThan($earliest) && $endsAt && $foodStartAt->copy()->addDay()->lessThan($endsAt)) {
                        $foodStartAt = $foodStartAt->addDay(); // Late-night service after midnight.
                    }
                    $foodEndAt = self::at($foodStartAt->setTimezone($timezone)->format('Y-m-d'), $foodEnd, $timezone);
                    if ($foodEndAt->lessThanOrEqualTo($foodStartAt)) {
                        $foodEndAt = $foodEndAt->addDay();
                    }
                    if ($foodStartAt->lessThan($earliest)) {
                        $errors['food_service_start_time'] = 'Food service cannot start before ' . ($doorsAt ? 'doors open' : 'the event starts') . '.';
                    } elseif ($endsAt && $foodEndAt->greaterThan($endsAt)) {
                        $errors['food_service_end_time'] = 'Food service must finish by the end of the event.';
                    }
                }
            }
        } elseif ($date !== null && $start === null && ($end !== null || $doors !== null || $foodStart !== null)) {
            $errors['start_time'] = 'Give a start time before setting other times.';
        }
        if (array_key_exists('event_date_iso', $data) || array_key_exists('start_time', $data)) {
            $out['starts_at'] = $startsAt?->utc();
            $out['ends_at'] = $endsAt?->utc();
        }

        $salesStart = self::dateTime($data, 'ticket_sales_start_at', $timezone, $errors);
        $salesEnd = self::dateTime($data, 'ticket_sales_end_at', $timezone, $errors);
        $earlyStart = self::dateTime($data, 'early_bird_starts_at', $timezone, $errors);
        $earlyEnd = self::dateTime($data, 'early_bird_ends_at', $timezone, $errors);
        foreach (['ticket_sales_start_at' => $salesStart, 'ticket_sales_end_at' => $salesEnd, 'early_bird_starts_at' => $earlyStart, 'early_bird_ends_at' => $earlyEnd] as $key => $value) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $value?->utc();
            }
        }
        $closesBy = $endsAt ?? $startsAt;
        if ($salesStart && $salesEnd && $salesEnd->lessThanOrEqualTo($salesStart)) {
            $errors['ticket_sales_end_at'] = 'Ticket sales must end after they start.';
        } elseif ($salesEnd && $closesBy && $salesEnd->greaterThan($closesBy)) {
            $errors['ticket_sales_end_at'] = 'Ticket sales must end by the time the event ' . ($endsAt ? 'ends' : 'starts') . '.';
        }
        if ($salesStart && $closesBy && $salesStart->greaterThanOrEqualTo($closesBy)) {
            $errors['ticket_sales_start_at'] = 'Ticket sales must open before the event.';
        }
        if ($earlyStart && $salesStart && $earlyStart->lessThan($salesStart)) {
            $errors['early_bird_starts_at'] = 'Early-bird sales cannot start before ticket sales open.';
        }
        if ($earlyStart && $salesEnd && $earlyStart->greaterThanOrEqualTo($salesEnd)) {
            $errors['early_bird_starts_at'] = 'Early-bird sales must start before ticket sales close.';
        }
        if ($earlyStart && $earlyEnd && $earlyEnd->lessThanOrEqualTo($earlyStart)) {
            $errors['early_bird_ends_at'] = 'Early-bird sales must end after they start.';
        }
        if ($earlyStart && $startsAt && $earlyStart->greaterThanOrEqualTo($startsAt)) {
            $errors['early_bird_starts_at'] = 'Early-bird sales must start before the event.';
        }
        if ($earlyEnd) {
            if (($earlyStart ?? $salesStart) && $earlyEnd->lessThanOrEqualTo($earlyStart ?? $salesStart)) {
                $errors['early_bird_ends_at'] = 'The early-bird period must end after ticket sales open.';
            } elseif ($salesEnd && $earlyEnd->greaterThan($salesEnd)) {
                $errors['early_bird_ends_at'] = 'The early-bird period must end before ticket sales close.';
            } elseif ($startsAt && $earlyEnd->greaterThan($startsAt)) {
                $errors['early_bird_ends_at'] = 'The early-bird period must end before the event starts.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * Pickup windows for food, checked against each other and the food service hours.
     *
     * @param list<array{start?: ?string, end?: ?string, max_orders?: mixed}> $windows
     * @return list<array{start: string, end: string, max_orders: ?int}>
     */
    public static function pickupWindows(array $windows, ?string $serviceStart, ?string $serviceEnd): array
    {
        $clean = [];
        foreach (array_values($windows) as $i => $window) {
            $startTime = trim((string) ($window['start'] ?? ''));
            $endTime = trim((string) ($window['end'] ?? ''));
            if ($startTime === '' && $endTime === '') {
                continue;
            }
            if (!preg_match(self::TIME, $startTime) || !preg_match(self::TIME, $endTime)) {
                throw ValidationException::withMessages(["food_pickup_windows.{$i}.start" => 'Give each pickup window a start and end time.']);
            }
            if (!self::withinService($startTime, $endTime, $serviceStart, $serviceEnd)) {
                throw ValidationException::withMessages(["food_pickup_windows.{$i}.start" => "Pickup window {$startTime}–{$endTime} must fall within the food service hours ({$serviceStart}–{$serviceEnd})."]);
            }
            $max = ($window['max_orders'] ?? '') === '' || ($window['max_orders'] ?? null) === null ? null : (int) $window['max_orders'];
            $clean[] = ['start' => $startTime, 'end' => $endTime, 'max_orders' => $max !== null && $max > 0 ? $max : null];
        }
        usort($clean, fn ($a, $b) => strcmp($a['start'], $b['start']));
        foreach ($clean as $i => $window) {
            if ($i > 0 && self::minutes($window['start']) < self::minutes($clean[$i - 1]['end']) && self::minutes($clean[$i - 1]['end']) > self::minutes($clean[$i - 1]['start'])) {
                throw ValidationException::withMessages(['food_pickup_windows' => "Pickup windows {$clean[$i - 1]['start']}–{$clean[$i - 1]['end']} and {$window['start']}–{$window['end']} overlap."]);
            }
        }

        return $clean;
    }

    /** "HH:MM-HH:MM", the key an order stores for its pickup window. */
    public static function windowKey(array $window): string
    {
        return $window['start'] . '-' . $window['end'];
    }

    private static function withinService(string $start, string $end, ?string $serviceStart, ?string $serviceEnd): bool
    {
        if ($serviceStart === null || $serviceEnd === null) {
            return true;
        }
        // Minutes from the start of service, so a service running past midnight compares correctly.
        $offset = fn (string $t) => (self::minutes($t) - self::minutes($serviceStart) + 1440) % 1440;
        $length = $offset($serviceEnd) ?: 1440;
        $windowStart = $offset($start);
        $windowEnd = $offset($end) ?: 1440;

        return $windowStart < $windowEnd && $windowEnd <= $length;
    }

    private static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    private static function at(string $date, string $time, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $timezone);
    }

    private static function time(array $data, string $key, array &$errors): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        $value = substr($value, 0, 5);
        if (!preg_match(self::TIME, $value)) {
            $errors[$key] = 'Use a 24-hour time such as 18:30.';

            return null;
        }

        return $value;
    }

    private static function dateTime(array $data, string $key, string $timezone, array &$errors): ?Carbon
    {
        $value = $data[$key] ?? null;
        if (self::blank($value)) {
            return null;
        }
        try {
            return $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value, $timezone);
        } catch (\Throwable) {
            $errors[$key] = 'Enter a valid date and time.';

            return null;
        }
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
