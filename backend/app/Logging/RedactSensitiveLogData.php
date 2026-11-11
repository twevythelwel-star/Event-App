<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * Log tap that strips credentials and identity-document data from every record's context and extra.
 *
 * Registered through the "tap" option on the file and alert channels in config/logging.php, so it applies
 * to anything written to the daily log, the single log, stderr and the Slack alert webhook. Matching is
 * by key name (case-insensitive, at any nesting depth) and by value shape for bearer tokens and card
 * numbers; matched values are replaced with "[redacted]".
 */
class RedactSensitiveLogData
{
    /** Key fragments that mark a value as sensitive wherever they appear in a key name. */
    private const SENSITIVE_KEY_PATTERN = '/pass(word|wd)?|secret|token|api[_-]?key|auth(orization)?|credential|private[_-]?key|signature|cvv|cvc|ssn|social[_-]?security|document|identity|license|licence|card[_-]?number|account[_-]?number|routing|iban|swift|seed|salt|encrypt|decrypt/i';

    /** Value shapes redacted even under innocuous keys. */
    private const SENSITIVE_VALUE_PATTERNS = [
        '#Bearer\s+[A-Za-z0-9\-._~+/]+=*#i',
        '/\b(?:\d[ -]?){13,19}\b/', // payment card shaped digit runs
    ];

    public function __invoke($logger): void
    {
        if (!method_exists($logger, 'getLogger')) {
            return;
        }
        // Monolog 3 passes an immutable LogRecord (Monolog 2 passed an array); both are handled.
        $logger->getLogger()->pushProcessor(function (\Monolog\LogRecord|array $record): \Monolog\LogRecord|array {
            if ($record instanceof \Monolog\LogRecord) {
                return $record->with(context: self::redact($record->context), extra: self::redact($record->extra));
            }
            $record['context'] = self::redact($record['context'] ?? []);
            $record['extra'] = self::redact($record['extra'] ?? []);

            return $record;
        });
    }

    private static function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1
                    ? '[redacted]'
                    : self::redact($item);
            }

            return $out;
        }

        if (is_string($value) && $value !== '') {
            foreach (self::SENSITIVE_VALUE_PATTERNS as $pattern) {
                if (preg_match($pattern, $value) === 1) {
                    return '[redacted]';
                }
            }
        }

        return $value;
    }
}
