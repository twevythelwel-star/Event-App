<?php

declare(strict_types=1);

namespace App\Services;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * USTimezoneAndLocationService
 *
 * Provides authoritative US address validation, UTC timestamp persistence,
 * local IANA timezone conversion, daylight-saving (DST) transition handling,
 * and event-scoped worker shift and ordering deadline validations.
 */
class USTimezoneAndLocationService
{
    /**
     * Validate a US address structure.
     */
    public static function validateAddress(array $address): array
    {
        return MarketConfigurationService::validateUSAddress($address);
    }

    /**
     * Convert a local date and time string in an event's IANA timezone into an authoritative UTC timestamp.
     *
     * @param string $localDateTime e.g. "2026-11-20 18:00:00"
     * @param string $ianaTimezone e.g. "America/New_York", "America/Chicago"
     * @return DateTimeImmutable Timestamp in UTC
     */
    public static function toUtc(string $localDateTime, string $ianaTimezone): DateTimeImmutable
    {
        if (!MarketConfigurationService::isSupportedUSTimezone($ianaTimezone)) {
            // Allow other valid IANA timezones if system configured, but ensure valid
            if (!in_array($ianaTimezone, DateTimeZone::listIdentifiers(), true)) {
                throw new InvalidArgumentException("Invalid or unsupported IANA timezone: {$ianaTimezone}");
            }
        }

        $localTz = new DateTimeZone($ianaTimezone);
        $localDt = new DateTimeImmutable($localDateTime, $localTz);

        return $localDt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Convert an authoritative UTC timestamp into a formatted string in the event's local timezone.
     */
    public static function toLocal(
        DateTimeInterface|string $utcTimestamp,
        string $ianaTimezone,
        string $format = 'Y-m-d h:i A T'
    ): string {
        $tz = new DateTimeZone($ianaTimezone);

        if (is_string($utcTimestamp)) {
            $dt = new DateTimeImmutable($utcTimestamp, new DateTimeZone('UTC'));
        } elseif ($utcTimestamp instanceof DateTime) {
            $dt = DateTimeImmutable::createFromMutable($utcTimestamp)->setTimezone($tz);
            return $dt->format($format);
        } else {
            $dt = $utcTimestamp;
        }

        $localDt = $dt->setTimezone($tz);
        return $localDt->format($format);
    }

    /**
     * Determine if Daylight Saving Time (DST) is active for the specified timezone and date.
     */
    public static function isDaylightSavingActive(string $ianaTimezone, string $dateTimeStr = 'now'): bool
    {
        $tz = new DateTimeZone($ianaTimezone);
        $dt = new DateTimeImmutable($dateTimeStr, $tz);

        // Format 'I' returns 1 for DST, 0 for Standard Time
        return (bool) $dt->format('I');
    }

    /**
     * Get the effective UTC offset in hours and minutes for an event timezone (e.g. "-04:00" for EDT, "-05:00" for EST).
     */
    public static function getEffectiveUtcOffset(string $ianaTimezone, string $dateTimeStr = 'now'): string
    {
        $tz = new DateTimeZone($ianaTimezone);
        $dt = new DateTimeImmutable($dateTimeStr, $tz);

        return $dt->format('P'); // e.g. -04:00, -05:00, -07:00
    }

    /**
     * Inspect and handle Daylight Saving Time (DST) transitions.
     * Demonstrates that Spring Forward (EDT) and Fall Back (EST) are correctly handled.
     */
    public static function handleDaylightSavingTransition(
        string $dateStr,
        string $timeStr,
        string $ianaTimezone
    ): array {
        $localDateTimeStr = "{$dateStr} {$timeStr}";
        $utc = self::toUtc($localDateTimeStr, $ianaTimezone);
        $localFormatted = self::toLocal($utc, $ianaTimezone, 'Y-m-d h:i A T (P)');
        $isDst = self::isDaylightSavingActive($ianaTimezone, $localDateTimeStr);
        $offset = self::getEffectiveUtcOffset($ianaTimezone, $localDateTimeStr);

        return [
            'input_local' => $localDateTimeStr,
            'event_timezone' => $ianaTimezone,
            'utc_iso' => $utc->format(DateTimeInterface::ATOM),
            'local_display' => $localFormatted,
            'is_dst' => $isDst,
            'utc_offset' => $offset,
            'tz_abbreviation' => self::toLocal($utc, $ianaTimezone, 'T'),
        ];
    }

    /**
     * Validate an advance ordering deadline against current reference time in the event timezone.
     */
    public static function validateEventOrderingDeadline(
        string $deadlineUtc,
        string $eventTimezone,
        ?string $referenceTimeUtc = null
    ): array {
        $deadlineDt = new DateTimeImmutable($deadlineUtc, new DateTimeZone('UTC'));
        $nowDt = $referenceTimeUtc !== null
            ? new DateTimeImmutable($referenceTimeUtc, new DateTimeZone('UTC'))
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $diffSeconds = $deadlineDt->getTimestamp() - $nowDt->getTimestamp();
        $isPassed = $diffSeconds <= 0;
        $minutesRemaining = (int) round($diffSeconds / 60);

        return [
            'is_cutoff_passed' => $isPassed,
            'minutes_remaining' => $minutesRemaining,
            'deadline_utc' => $deadlineDt->format(DateTimeInterface::ATOM),
            'deadline_local' => self::toLocal($deadlineDt, $eventTimezone, 'l, F j, Y \a\t g:i A T'),
            'current_time_local' => self::toLocal($nowDt, $eventTimezone, 'g:i A T'),
            'event_timezone' => $eventTimezone,
        ];
    }

    /**
     * Validate a worker assignment shift window within the event's intended timezone.
     */
    public static function validateWorkerShiftInEventTimezone(
        string $shiftStartUtc,
        string $shiftEndUtc,
        string $eventTimezone
    ): array {
        $startDt = new DateTimeImmutable($shiftStartUtc, new DateTimeZone('UTC'));
        $endDt = new DateTimeImmutable($shiftEndUtc, new DateTimeZone('UTC'));

        if ($endDt <= $startDt) {
            throw new InvalidArgumentException("Shift expiration ({$shiftEndUtc}) must be chronologically after shift start ({$shiftStartUtc}).");
        }

        $durationMinutes = (int) round(($endDt->getTimestamp() - $startDt->getTimestamp()) / 60);
        $durationHours = round($durationMinutes / 60.0, 2);

        return [
            'valid' => true,
            'event_timezone' => $eventTimezone,
            'duration_minutes' => $durationMinutes,
            'duration_hours' => $durationHours,
            'local_shift_start' => self::toLocal($startDt, $eventTimezone, 'D, M j, Y g:i A T'),
            'local_shift_end' => self::toLocal($endDt, $eventTimezone, 'g:i A T'),
            'is_start_dst' => self::isDaylightSavingActive($eventTimezone, $startDt->format('Y-m-d H:i:s')),
            'is_end_dst' => self::isDaylightSavingActive($eventTimezone, $endDt->format('Y-m-d H:i:s')),
        ];
    }
}
