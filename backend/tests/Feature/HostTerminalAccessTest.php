<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The people who run an event's organizer can use its staff terminal without a staff assignment;
 * hosts of other organizers cannot.
 */
class HostTerminalAccessTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Organizer $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = Organizer::create(['name' => 'Terminal Org', 'slug' => 'terminal-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'slug' => 'terminal-night',
            'title' => 'Terminal Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Terminal Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);
    }

    private function host(?Organizer $organizer, string $email, string $role = 'event_owner'): User
    {
        $user = User::create(['name' => 'Host ' . $email, 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $user->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        if ($organizer) {
            OrganizerUser::create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'role' => $role, 'is_active' => true]);
        }

        return $user;
    }

    private function paidOrder(string $code): Order
    {
        return Order::allTenants()->create([
            'organizer_id' => $this->organizer->id,
            'event_id' => $this->event->id,
            'ticket_code' => "SFF-{$code}",
            'order_number' => "ORD-2026-{$code}",
            'customer_name' => 'Gate Guest',
            'customer_phone' => '+1 876 555 0101',
            'email' => 'guest@example.test',
            'method' => 'bank',
            'subtotal' => 2000,
            'total_amount' => 2000,
            'amount_paid' => 2000,
            'currency' => 'JMD',
            'status' => 'paid',
            'secure_ticket_token' => "tok_terminal_{$code}",
        ]);
    }

    public function test_the_events_own_host_can_open_its_terminal_and_admit_guests(): void
    {
        $owner = $this->host($this->organizer, 'owner@example.test');
        $order = $this->paidOrder('300001');

        $this->actingAs($owner)->get("/worker/events/{$this->event->id}/terminal")->assertOk()->assertSee('Ticket Check-in');

        $response = $this->actingAs($owner)->postJson("/worker/events/{$this->event->id}/scan", ['ticket_code' => $order->secure_ticket_token])
            ->assertOk()->assertJson(['success' => true]);

        // The scan response used to read columns that do not exist on orders, so these were always null.
        $this->assertSame('guest@example.test', $response->json('ticket.customer_email'));
        $this->assertSame('bank', $response->json('ticket.payment_method'));
        $this->assertSame(200000, $response->json('ticket.total_cents'));
        $this->assertNotNull($order->fresh()->checked_in_at);
    }

    public function test_an_organizer_supervisor_membership_also_runs_the_terminal(): void
    {
        $supervisor = $this->host($this->organizer, 'supervisor@example.test', 'event_supervisor');

        $this->actingAs($supervisor)->get("/worker/events/{$this->event->id}/terminal")->assertOk();
    }

    public function test_hosts_of_other_organizers_and_hosts_without_one_are_refused(): void
    {
        $other = $this->host(Organizer::create(['name' => 'Other Org', 'slug' => 'other-org']), 'other@example.test');
        $lone = $this->host(null, 'lone@example.test');
        $inactive = $this->host($this->organizer, 'former@example.test');
        OrganizerUser::where('user_id', $inactive->id)->update(['is_active' => false]);
        $order = $this->paidOrder('300002');

        foreach ([$other, $lone, $inactive] as $user) {
            $this->actingAs($user)->get("/worker/events/{$this->event->id}/terminal")->assertForbidden();
            $this->actingAs($user)->postJson("/worker/events/{$this->event->id}/scan", ['ticket_code' => $order->secure_ticket_token])->assertForbidden();
        }

        $this->assertNull($order->fresh()->checked_in_at);
    }
}
