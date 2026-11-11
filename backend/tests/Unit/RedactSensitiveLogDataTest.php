<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveLogData;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/** The log tap redacts secrets from Monolog 3 records (LogRecord objects) without breaking the log write. */
class RedactSensitiveLogDataTest extends TestCase
{
    public function test_secrets_are_redacted_and_ordinary_values_kept(): void
    {
        $handler = new TestHandler();
        $monolog = new Logger('test', [$handler]);
        // The tap receives Laravel's logger wrapper, which exposes getLogger().
        $wrapper = new class ($monolog) {
            public function __construct(private Logger $logger) {}
            public function getLogger(): Logger { return $this->logger; }
        };
        (new RedactSensitiveLogData())($wrapper);

        $monolog->log(Level::Info, 'check', [
            'api_key' => 'abc123',
            'nested' => ['password' => 'hunter2', 'note' => 'Bearer abc.def/ghi=='],
            'card' => '4242 4242 4242 4242',
            'event' => 'evt-kingston-harbour-fry',
        ]);

        $context = $handler->getRecords()[0]->context;
        $this->assertSame('[redacted]', $context['api_key']);
        $this->assertSame('[redacted]', $context['nested']['password']);
        $this->assertSame('[redacted]', $context['nested']['note']);
        $this->assertSame('[redacted]', $context['card']);
        $this->assertSame('evt-kingston-harbour-fry', $context['event']);
    }
}
