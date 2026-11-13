<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Removes raw admission credentials (order passes tok_..., attendee tickets tkt_...) from every application log
 * line and its context, so a credential that reaches a log by mistake is never stored there in usable form.
 */
final class RedactCredentialsProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(message: Order::redactTokenForLog($record->message), context: self::redact($record->context));
    }

    private static function redact(mixed $value): mixed
    {
        if (is_string($value)) {
            return Order::redactTokenForLog($value);
        }
        if (is_array($value)) {
            return array_map(self::redact(...), $value);
        }

        return $value;
    }
}
