<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Operational alerts to the critical-alert channel (Slack) in production. Best effort: an unreachable webhook is
 * logged to the default channel and never thrown, so an alert can never roll back the transaction or fail the
 * request that raised it.
 */
final class Alert
{
    /** @param array<string, mixed> $context */
    public static function send(string $level, string $message, array $context = []): void
    {
        if (!app()->environment('production')) {
            return;
        }
        try {
            Log::channel('slack')->log($level, $message, $context);
        } catch (\Throwable $e) {
            Log::warning('alert.delivery_failed', ['alert' => $message, 'exception_type' => $e::class]);
        }
    }
}
