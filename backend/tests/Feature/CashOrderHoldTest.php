<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\InventoryReservationExpiryService;
use App\Services\OrderPlacementService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cash at pickup is paid to staff at the event, so the order is held until then; an order waiting on a transfer
 * keeps the short reservation timer and is released when it runs out.
 */
class CashOrderHoldTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function order(string $method): Order
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('is_active', true)->firstOrFail();
        $result = app(OrderPlacementService::class)->placeOrder($event, [
            'customer_name' => 'Buyer One', 'customer_phone' => '+1 305 555 0123', 'method' => $method,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ], User::where('email', 'attendee@islandevents.example')->firstOrFail());
        $this->assertTrue($result['success'], $result['error'] ?? 'order placed');

        return $result['order']->fresh();
    }

    public function test_a_cash_at_pickup_order_is_held_until_the_event_but_an_unpaid_transfer_expires(): void
    {
        $cash = $this->order('cash');
        $transfer = $this->order('bank');
        $this->assertNull($cash->reservation_expires_at, 'cash at pickup has no short hold');
        $this->assertNotNull($transfer->reservation_expires_at);

        $this->travel(20)->minutes();
        app(InventoryReservationExpiryService::class)->expire();

        $this->assertSame('awaiting_payment', $cash->fresh()->status, 'the cash order is still waiting for payment at pickup');
        $this->assertSame('expired', $transfer->fresh()->status, 'the unpaid transfer order is released');
    }

    public function test_an_older_cash_order_with_a_timer_is_not_expired_either(): void
    {
        $cash = $this->order('cash');
        $cash->forceFill(['reservation_expires_at' => now()->subMinute()])->save();

        app(InventoryReservationExpiryService::class)->expire();

        $this->assertSame('awaiting_payment', $cash->fresh()->status);
    }
}
