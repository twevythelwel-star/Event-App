<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeded demo events use readable keys ("evt-kingston-harbour-fry") rather than UUIDs.
 * Routes that bind {event} must still find them, and still reject anything malformed.
 */
class DemoEventBindingTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
        // Seeding runs in this PHP process and can leave a tenant selected; a real request starts without one.
        TenantContext::clear();

        $event = Event::allTenants()->findOrFail(self::EVENT);
        $this->checker = User::create(['name' => 'Gate Checker', 'email' => 'gate@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create([
            'organizer_id' => $event->organizer_id,
            'event_id' => $event->id,
            'user_id' => $this->checker->id,
            'worker_role' => 'ticket_checker',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_staff_terminal_scans_tickets_for_a_demo_event(): void
    {
        $order = Order::allTenants()->where('event_id', self::EVENT)->where('status', 'paid')->firstOrFail();

        $this->actingAs($this->checker)
            ->postJson('/worker/events/' . self::EVENT . '/scan', ['ticket_code' => $order->ticket_code])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($order->fresh()->checked_in_at);
    }

    public function test_malformed_event_keys_are_still_not_found(): void
    {
        foreach (['EVT-KINGSTON-HARBOUR-FRY', 'evt-', 'evt--kingston', 'kingston-harbour-fry', "evt-kingston'or'1", 'evt-' . str_repeat('a', 80)] as $key) {
            $this->actingAs($this->checker)
                ->postJson('/worker/events/' . rawurlencode($key) . '/scan', ['ticket_code' => 'X'])
                ->assertNotFound();
        }
    }

    public function test_uuid_events_still_bind(): void
    {
        $this->assertTrue((new Event())->resolveRouteBinding(Event::allTenants()->create([
            'organizer_id' => 'org-kingston-youth',
            'slug' => 'uuid-night',
            'title' => 'UUID Night',
            'date_str' => 'Saturday',
            'time_str' => '6:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
        ])->id) instanceof Event);
    }
}
