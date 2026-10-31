<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * Shared Blade components render the right content, wording and accessibility hooks.
 */
class BladeComponentsTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $overrides = []): Event
    {
        $organizer = Organizer::firstOrCreate(['slug' => 'comp-org'], ['name' => 'Component Org']);

        return Event::allTenants()->create(array_merge([
            'organizer_id' => $organizer->id,
            'slug' => 'comp-night-' . uniqid(),
            'title' => 'Component Night',
            'category' => 'Food',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'currency' => 'JMD',
            'venue_name' => 'Kingston Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Component Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
            'is_ordering_open' => true,
        ], $overrides));
    }

    public function test_money_format(): void
    {
        $this->assertSame('J$2,250.00', Money::format(2250, 'JMD'));
        $this->assertSame('$13.50', Money::format('13.5', 'usd')); // USD symbol comes from config/money.php
    }

    public function test_status_badge_uses_one_payment_wording(): void
    {
        $this->blade('@include("components.status-indicator", ["status" => "awaiting_payment"])')->assertSee('Pending');
        $this->blade('@include("components.status-indicator", ["status" => "payment_submitted"])')->assertSee('Confirmation submitted');
        $this->blade('@include("components.status-indicator", ["status" => "paid"])')->assertSee('Verified')->assertSee('eb-badge-success', false);
        $this->blade('@include("components.status-indicator", ["status" => "rejected"])')->assertSee('Rejected')->assertSee('eb-badge-error', false);
        $this->blade('@include("components.status-indicator", ["status" => "refunded"])')->assertSee('Refunded');
        $this->blade('<x-status-indicator status="published" />')->assertSee('Published');
    }

    public function test_event_card_links_the_whole_card_and_uses_safe_image_urls(): void
    {
        $event = $this->event(['flyer_path' => '/images/escovitch_fish.jpg']);
        $this->blade('<x-event-card :event="$event" price="From J$2,000.00" />', ['event' => $event])
            ->assertSee('Component Night')
            ->assertSee(route('events.show', $event->id), false)
            ->assertSee('/images/escovitch_fish.jpg', false)
            ->assertSee('From J$2,000.00')
            ->assertDontSee('Ordering closed');

        $uploaded = $this->event(['flyer_path' => 'tenants/abc/flyers/x.jpg', 'is_ordering_open' => false]);
        $this->blade('<x-event-card :event="$event" />', ['event' => $uploaded])
            ->assertSee(route('api.media.flyer', $uploaded->id), false)
            ->assertDontSee('tenants/abc/flyers/x.jpg', false)
            ->assertSee('Ordering closed');
    }

    public function test_food_card_shows_price_in_order_currency_and_stock_state(): void
    {
        $event = $this->event();
        $item = MenuItem::allTenants()->create([
            'organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'name' => 'Curry Goat',
            'description' => 'Slow-cooked', 'category' => 'Mains', 'pricing_type' => 'fixed',
            'price_usd' => 12, 'price_jmd' => 1800, 'available_stock' => 3, 'is_active' => true,
        ]);

        $this->blade('<x-food-card :item="$item" currency="JMD">ADD</x-food-card>', ['item' => $item])
            ->assertSee('Curry Goat')->assertSee('J$1,800.00')->assertSee('3 left')->assertSee('ADD');

        $item->available_stock = 0;
        $this->blade('<x-food-card :item="$item" currency="USD" />', ['item' => $item])
            ->assertSee('$12.00')->assertSee('Sold out')->assertSee('aria-disabled="true"', false);
    }

    public function test_ticket_card_shows_code_total_and_status_without_qr(): void
    {
        $event = $this->event();
        $order = Order::allTenants()->create([
            'organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'ticket_code' => 'SFF-123456',
            'order_number' => 'ORD-2026-123456', 'customer_name' => 'Guest', 'customer_phone' => '+18765550101',
            'method' => 'bank', 'subtotal' => 2000, 'total_amount' => 2000, 'currency' => 'JMD',
            'status' => 'payment_submitted', 'secure_ticket_token' => 'tok_comp_1',
        ]);

        $this->blade('<x-ticket-card :order="$order" href="/tickets/1" />', ['order' => $order->load('event')])
            ->assertSee('SFF-123456')->assertSee('J$2,000.00')->assertSee('Confirmation submitted')
            ->assertSee('View ticket')->assertDontSee('tok_comp_1');
    }

    public function test_form_components_have_labels(): void
    {
        $this->blade('<x-payment-method value="bank" label="Bank transfer" description="NCB or Scotiabank" :checked="true" />')
            ->assertSee('for="pm-method-bank"', false)->assertSee('id="pm-method-bank"', false)
            ->assertSee('checked', false)->assertSee('Bank transfer');

        $this->blade('<x-quantity-control name="items[0][quantity]" :value="2" :max="6" label="Quantity of Jerk Chicken" />')
            ->assertSee('Quantity of Jerk Chicken')->assertSee('max="6"', false)->assertSee('value="2"', false)
            ->assertSee('Decrease Quantity of Jerk Chicken');

        $this->blade('<x-search-field name="q" label="Search events" value="fish" />')
            ->assertSee('for="search-q"', false)->assertSee('value="fish"', false);
    }

    public function test_price_summary_formats_amounts_it_is_given(): void
    {
        $this->blade('<x-price-summary :lines="$lines" :fee="100" :total="2100" currency="JMD" note="Prices checked again at checkout." />', [
            'lines' => [['label' => '1 × General Admission', 'amount' => 2000]],
        ])->assertSee('1 × General Admission')->assertSee('J$2,000.00')->assertSee('Packaging fee')->assertSee('J$2,100.00');

        $this->blade('<x-price-summary :lines="[]" :fee="0" :total="0" />')->assertDontSee('Packaging fee');
    }

    public function test_metric_card_shows_its_definition(): void
    {
        $this->blade('<x-metric-card label="Verified orders" value="12" note="Last 30 days" definition="Paid or partially paid." />')
            ->assertSee('Verified orders')->assertSee('12')->assertSee('Last 30 days')->assertSee('Paid or partially paid.');
    }

    public function test_pagination_and_table(): void
    {
        $page = new LengthAwarePaginator(['a', 'b'], 3, 2, 1, ['path' => '/list']);
        $this->blade('<x-pagination :paginator="$p" />', ['p' => $page])
            ->assertSee('Showing 1–2 of 3')->assertSee('Page 1 of 2')->assertSee('/list?page=2', false);

        $this->blade('<x-data-table caption="Orders" :columns="[\'Order\', [\'label\' => \'Amount\', \'numeric\' => true]]"><tr><td>X</td></tr></x-data-table>')
            ->assertSee('<caption', false)->assertSee('scope="col"', false)->assertSee('eb-table-num', false);

        $this->blade('<x-data-table caption="Orders" :empty="true" empty-title="No orders yet" />')->assertSee('No orders yet');
    }

    public function test_feedback_components_announce_correctly(): void
    {
        $this->blade('<x-modal id="m1" title="Confirm">Body</x-modal>')
            ->assertSee('role="dialog"', false)->assertSee('aria-labelledby="m1-title"', false)->assertSee('display: none', false);
        $this->blade('<x-modal id="m2" title="Errors" :open="true">Body</x-modal>')->assertDontSee('display: none', false);

        $this->blade('<x-loading label="Loading orders…" />')->assertSee('role="status"', false)->assertSee('Loading orders…');
        $this->blade('<x-alert type="error">Sold out</x-alert>')->assertSee('role="alert"', false)->assertSee('Sold out');
        $this->blade('<x-alert type="success">Saved</x-alert>')->assertSee('role="status"', false);

        session()->flash('success', 'Order placed');
        $this->blade('<x-alert :flash="true" />')->assertSee('Order placed');
    }
}
