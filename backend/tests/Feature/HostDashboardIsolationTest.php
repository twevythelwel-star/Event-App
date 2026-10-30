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
 * The host dashboard only ever counts events and orders from organizers the host belongs to.
 */
class HostDashboardIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function organizerWithEvent(string $slug, int $orders): Organizer
    {
        $organizer = Organizer::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $event = Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => "{$slug}-night",
            'title' => ucfirst($slug) . ' Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => ucfirst($slug),
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);
        for ($i = 1; $i <= $orders; $i++) {
            Order::allTenants()->create([
                'organizer_id' => $organizer->id,
                'event_id' => $event->id,
                'ticket_code' => strtoupper($slug) . "-{$i}",
                'order_number' => 'ORD-' . strtoupper($slug) . "-{$i}",
                'customer_name' => "{$slug} guest {$i}",
                'customer_phone' => '+1 876 555 0101',
                'method' => 'cash',
                'subtotal' => 1000,
                'total_amount' => 1000,
                'amount_paid' => 1000,
                'currency' => 'JMD',
                'status' => 'paid',
                'secure_ticket_token' => "tok_{$slug}_{$i}",
            ]);
        }

        return $organizer;
    }

    private function host(Organizer ...$organizers): User
    {
        $user = User::create(['name' => 'Host', 'email' => uniqid('host') . '@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $user->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        foreach ($organizers as $organizer) {
            OrganizerUser::create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'role' => 'event_owner', 'is_active' => true]);
        }

        return $user;
    }

    public function test_host_sees_only_their_own_organizer(): void
    {
        $mine = $this->organizerWithEvent('mine', 2);
        $this->organizerWithEvent('theirs', 3);

        $this->actingAs($this->host($mine))->getJson('/host/dashboard')
            ->assertOk()
            ->assertJson(['organizer_id' => $mine->id, 'total_events' => 1, 'total_orders' => 2, 'verified_payments_jmd' => 2000])
            ->assertJsonMissing(['customer_name' => 'theirs guest 1']);
    }

    public function test_host_of_two_organizers_sees_both_and_no_others(): void
    {
        $a = $this->organizerWithEvent('alpha', 1);
        $b = $this->organizerWithEvent('bravo', 2);
        $this->organizerWithEvent('charlie', 4);

        $this->actingAs($this->host($a, $b))->getJson('/host/dashboard')
            ->assertOk()
            ->assertJson(['total_events' => 2, 'total_orders' => 3]);
    }

    public function test_host_without_an_organizer_sees_nothing(): void
    {
        $this->organizerWithEvent('someone', 2);

        $this->actingAs($this->host())->getJson('/host/dashboard')
            ->assertOk()
            ->assertJson(['total_events' => 0, 'total_orders' => 0, 'cash_receipts_total' => 0]);
    }
}
