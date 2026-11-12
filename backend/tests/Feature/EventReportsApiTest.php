<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The host's event reports (financial, check-in, fulfillment): available to the event's managers only. */
class EventReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry'; // run by the demo host's organizer

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    public function test_the_events_host_gets_every_report(): void
    {
        $host = $this->user('host');
        foreach (['financial', 'check-in', 'fulfillment'] as $report) {
            $this->actingAs($host)->getJson("/api/v1/host/events/" . self::EVENT . "/reports/{$report}")
                ->assertOk()->assertJsonPath('event_id', self::EVENT);
        }
    }

    public function test_nobody_else_sees_them(): void
    {
        foreach (['attendee', 'admin', 'staff'] as $who) {
            $this->actingAs($this->user($who))->getJson("/api/v1/host/events/" . self::EVENT . "/reports/financial")->assertNotFound();
        }
        app('auth')->forgetGuards();
        $this->getJson("/api/v1/host/events/" . self::EVENT . "/reports/financial")->assertUnauthorized();
    }
}
