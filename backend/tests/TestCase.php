<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach real services (Twilio, mail APIs): any outgoing HTTP call
        // that a test has not faked fails loudly instead of sending a real message.
        Http::preventStrayRequests();
    }
}
