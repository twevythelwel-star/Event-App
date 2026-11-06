<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueArea;
use App\Models\VenueSeat;
use App\Services\Events\SeatingService;
use App\Services\OrderPlacementException;
use App\Services\OrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The venue designer and seat selection: hosts lay out areas, tables and rows of seats; buyers of seated ticket types
 * choose a seat per ticket; a seat is never sold twice and frees itself when its order is no longer live.
 */
class VenueSeatingTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $host;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $organizer = Organizer::create(['name' => 'Seat Org', 'slug' => 'seat-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $organizer->id, 'slug' => 'gala', 'title' => 'Harbour Gala', 'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM', 'currency' => 'JMD', 'venue_name' => 'Kingston Pier', 'venue_address' => 'Kingston',
            'organizer_name' => 'Seat Org', 'organizer_phone' => '+18765550000', 'advance_order_deadline' => now()->addWeek(),
            'status' => 'published', 'is_ordering_open' => true,
            'ticket_tiers' => [
                ['id' => 'tier-ga', 'name' => 'General Admission', 'price_jmd' => 2000, 'quantity_available' => 100, 'max_per_order' => 6],
                ['id' => 'tier-table', 'name' => 'VIP Table', 'price_jmd' => 9000, 'quantity_available' => 16, 'max_per_order' => 8],
                ['id' => 'tier-hall', 'name' => 'Hall Seat', 'price_jmd' => 4000, 'quantity_available' => 10, 'max_per_order' => 6],
            ],
        ]);
        HostPaymentPolicy::create(['organizer_id' => $organizer->id, 'enable_cash' => true, 'bank_transfer_instructions' => 'Bank: Example']);
        $this->host = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $this->host->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);
        $this->buyer = User::create(['name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '+18765550123', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function plan(array $extra = []): array
    {
        return array_merge([
            ['kind' => 'stage', 'label' => 'Stage', 'x' => 400, 'y' => 20, 'width' => 200, 'height' => 60],
            ['kind' => 'general_admission', 'label' => 'Lawn', 'x' => 20, 'y' => 100, 'width' => 300, 'height' => 200, 'ticket_tier_id' => 'tier-ga', 'capacity' => 100],
            ['kind' => 'table', 'label' => 'Table 1', 'x' => 400, 'y' => 120, 'width' => 120, 'height' => 120, 'ticket_tier_id' => 'tier-table', 'seats_per_table' => 8],
            ['kind' => 'seat_rows', 'label' => 'Hall', 'x' => 560, 'y' => 120, 'width' => 300, 'height' => 120, 'ticket_tier_id' => 'tier-hall', 'rows' => 2, 'seats_per_row' => 5, 'is_accessible' => '1'],
            ['kind' => 'food_station', 'label' => 'Grill', 'x' => 20, 'y' => 400, 'width' => 160, 'height' => 100],
            ['kind' => 'vendor_booth', 'label' => 'Crafts', 'x' => 200, 'y' => 400, 'width' => 160, 'height' => 100],
            ['kind' => 'restricted', 'label' => 'Staff only', 'x' => 800, 'y' => 500, 'width' => 160, 'height' => 100],
        ], $extra);
    }

    private function save(array $areas, bool $seats = true): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->host)->post(route('events.venue-designer.save', $this->event->id), ['areas' => $areas, 'seat_selection_enabled' => $seats ? '1' : '0']);
    }

    private function order(array $quantities, array $seatIds): array
    {
        return app(OrderPlacementService::class)->placeOrder($this->event->fresh(), [
            'customer_name' => 'Buyer', 'customer_phone' => '+18765550123', 'method' => 'cash',
            'items' => collect($quantities)->map(fn ($qty, $tier) => ['menu_item_id' => $tier, 'quantity' => $qty])->values()->all(),
            'seats' => $seatIds,
        ], $this->buyer);
    }

    public function test_hosts_lay_out_areas_and_seats_are_generated(): void
    {
        $this->actingAs($this->host)->get(route('events.venue-designer', $this->event->id))->assertOk()->assertSee('Venue designer')->assertSee('Reserved table');
        $this->save($this->plan())->assertRedirect(route('events.venue-designer', $this->event->id))->assertSessionHas('status');

        $this->assertSame(7, VenueArea::where('event_id', $this->event->id)->count());
        $this->assertSame(18, VenueSeat::where('event_id', $this->event->id)->count(), '8 at the table and 2 rows of 5.');
        $this->assertSame(['Hall A1', 'Hall A2'], VenueSeat::where('event_id', $this->event->id)->where('row_label', 'A')->orderBy('number')->limit(2)->pluck('label')->all());
        $this->assertTrue(VenueSeat::where('label', 'Hall B5')->firstOrFail()->is_accessible);
        $this->assertSame('Table 1, seat 3', VenueSeat::where('label', 'Table 1-3')->firstOrFail()->spokenName());
        $this->assertTrue(app(SeatingService::class)->seatSelectionOpen($this->event));
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'venue_layout_saved']);
    }

    public function test_plans_are_checked(): void
    {
        $this->save([['kind' => 'table', 'label' => 'Off plan', 'x' => 990, 'y' => 0, 'width' => 120, 'height' => 120, 'seats_per_table' => 4]])->assertSessionHasErrors('venue');
        $this->save([['kind' => 'table', 'label' => 'T', 'x' => 0, 'y' => 0, 'width' => 120, 'height' => 120, 'seats_per_table' => 4, 'ticket_tier_id' => 'tier-from-elsewhere']])->assertSessionHasErrors('venue');
        $this->save([['kind' => 'table', 'label' => 'Same', 'x' => 0, 'y' => 0, 'width' => 120, 'height' => 120, 'seats_per_table' => 4],
            ['kind' => 'table', 'label' => 'Same', 'x' => 200, 'y' => 0, 'width' => 120, 'height' => 120, 'seats_per_table' => 4]])->assertSessionHasErrors('venue');

        $outsider = User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($outsider)->get(route('events.venue-designer', $this->event->id))->assertNotFound();
        $this->actingAs($outsider)->post(route('events.venue-designer.save', $this->event->id), ['areas' => $this->plan()])->assertNotFound();
        $this->assertSame(0, VenueArea::count());
    }

    public function test_buyers_choose_one_seat_per_seated_ticket_and_see_them_on_their_ticket(): void
    {
        $this->save($this->plan());
        $tickets = $this->actingAs($this->buyer)->get(route('checkout.tickets', $this->event))->assertOk();
        $tickets->assertSee('Choose your seats')->assertSee('Table 1, seat 1, VIP Table, available');

        $seats = VenueSeat::where('event_id', $this->event->id)->whereIn('label', ['Table 1-1', 'Table 1-2'])->pluck('id')->all();
        // Two table tickets need two table seats; one is not enough. General admission needs none.
        $this->actingAs($this->buyer)->from(route('checkout.tickets', $this->event))
            ->post(route('checkout.tickets.save', $this->event), ['tiers' => ['tier-table' => 2, 'tier-ga' => 1], 'seats' => [$seats[0]]])
            ->assertSessionHasErrors('seats');
        $this->actingAs($this->buyer)->post(route('checkout.tickets.save', $this->event), ['tiers' => ['tier-table' => 2, 'tier-ga' => 1], 'seats' => $seats])
            ->assertSessionHasNoErrors();

        $result = $this->order(['tier-table' => 2, 'tier-ga' => 1], $seats);
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $order = $result['order'];
        $this->assertSame(2, VenueSeat::where('order_id', $order->id)->count());

        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()->assertSee('Table 1, seat 1 · Table 1, seat 2');
        $this->actingAs($this->buyer)->get(route('checkout.tickets', $this->event))->assertSee('Table 1, seat 1, VIP Table, taken');
    }

    public function test_a_seat_is_never_sold_twice_and_frees_itself_when_the_order_ends(): void
    {
        $this->save($this->plan());
        $seat = VenueSeat::where('label', 'Hall A1')->firstOrFail();
        $first = $this->order(['tier-hall' => 1], [$seat->id]);
        $this->assertTrue($first['success'], $first['error'] ?? '');

        $second = $this->order(['tier-hall' => 1], [$seat->id]);
        $this->assertFalse($second['success']);
        $this->assertStringContainsString('has just been taken', $second['error']);
        $this->assertSame(1, Order::allTenants()->count(), 'The failed order left nothing behind.');

        // Seat counts must match ticket counts, and a seat from another ticket type does not count.
        $this->assertFalse($this->order(['tier-hall' => 2], [VenueSeat::where('label', 'Hall A2')->value('id')])['success']);
        $this->assertFalse($this->order(['tier-hall' => 1], [VenueSeat::where('label', 'Table 1-5')->value('id')])['success']);

        $first['order']->forceFill(['status' => 'expired', 'inventory_released_at' => now()])->save();
        $again = $this->order(['tier-hall' => 1], [$seat->id]);
        $this->assertTrue($again['success'], 'An expired order no longer holds its seat.');
    }

    public function test_sold_seats_protect_their_area_but_it_can_still_move(): void
    {
        $this->save($this->plan());
        $seat = VenueSeat::where('label', 'Hall A1')->firstOrFail();
        $this->assertTrue($this->order(['tier-hall' => 1], [$seat->id])['success']);
        $areas = VenueArea::where('event_id', $this->event->id)->orderBy('sort_order')->get()->map(fn ($a) => $a->only(['id', 'kind', 'label', 'x', 'y', 'width', 'height', 'ticket_tier_id', 'capacity', 'rows', 'seats_per_row', 'seats_per_table']))->all();

        $fewer = $areas;
        $fewer[3]['rows'] = 1;
        $this->save($fewer)->assertSessionHasErrors('venue');
        $this->save(array_values(array_filter($areas, fn ($a) => $a['label'] !== 'Hall')))->assertSessionHasErrors('venue');

        $moved = $areas;
        $moved[3]['y'] = 300;
        $this->save($moved)->assertSessionHas('status');
        $this->assertTrue($this->order(['tier-ga' => 1], [])['success'], 'Unseated tickets still sell without a seat.');
        $this->assertNotNull(VenueSeat::find($seat->id)->order_id, 'Moving the area kept the sold seat and its order.');
        $this->assertGreaterThan($seat->y, VenueSeat::find($seat->id)->y);
    }
}
