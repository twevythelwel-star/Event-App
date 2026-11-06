<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organizer;
use App\Models\User;
use App\Services\Checkout\OrderQuoteService;
use App\Services\Events\EventDetails;
use App\Services\Events\EventSchedule;
use App\Services\Events\MapLocationService;
use App\Services\OrderOperationsService;
use App\Services\OrderPlacementService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Event timetable and venue, the food service plan, the menu and package builder, guest mix-and-match ordering,
 * and item-level fulfillment.
 */
class FoodMenuAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'America/New_York';

    private Event $event;
    private User $buyer;
    /** @var array<string, MenuItem> */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();

        $organizer = Organizer::create(['name' => 'Sunday Kitchen', 'slug' => 'sunday-kitchen']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => 'sunday-dinner-fair',
            'title' => 'Sunday Dinner Fair',
            'date_str' => 'Sunday, December 6, 2026',
            'time_str' => '1:00 PM – 9:00 PM',
            'event_date_iso' => now()->addMonth()->toDateString(),
            'timezone' => self::TZ,
            'currency' => 'USD',
            'venue_name' => 'Riverside Park',
            'venue_address' => '1 Park Ave, Miami, FL 33101, United States',
            'organizer_name' => 'Sunday Kitchen',
            'organizer_phone' => '+13055550100',
            'advance_order_deadline' => now()->addWeeks(2),
            'status' => 'published',
            'is_ordering_open' => true,
            'ticket_tiers' => [['id' => 'tier-ga', 'name' => 'General Admission', 'price_usd' => 10, 'availableStock' => 50, 'maxPerCustomer' => 4]],
        ]);
        foreach ([
            'chicken' => ['Jerk Chicken', 'Poultry', '12.00', 20],
            'rice' => ['Rice and Peas', 'Rice and Grains', '4.00', 30],
            'veg' => ['Steamed Vegetables', 'Sides', '3.00', null],
            'festival' => ['Festival', 'Sides', '2.00', null],
            'plantain' => ['Plantain', 'Sides', '3.50', 10],
            'oxtail' => ['Oxtail', 'Meat', '18.00', 5],
            'fish' => ['Fried Fish', 'Seafood', '15.00', null],
        ] as $key => [$name, $category, $price, $stock]) {
            $this->items[$key] = MenuItem::allTenants()->create([
                'organizer_id' => $organizer->id, 'event_id' => $this->event->id, 'name' => $name, 'category' => $category,
                'pricing_type' => 'fixed', 'price_usd' => $price, 'price_jmd' => 0, 'currency' => 'USD',
                'available_stock' => $stock, 'is_active' => true,
            ]);
        }
        $this->buyer = User::create(['name' => 'Dana Guest', 'email' => 'dana@example.test', 'phone' => '+13055550123', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function sundayDinner(array $overrides = []): MenuItem
    {
        $id = fn (string $key) => $this->items[$key]->id;

        return MenuItem::allTenants()->create(array_merge([
            'organizer_id' => $this->event->organizer_id, 'event_id' => $this->event->id, 'name' => 'Jamaican Sunday Dinner',
            'category' => 'Meal Packages', 'item_type' => 'package', 'pricing_type' => 'fixed', 'price_usd' => '18.00', 'price_jmd' => 0,
            'currency' => 'USD', 'is_active' => true,
            'package_config' => [
                'components' => [
                    ['menu_item_id' => $id('chicken'), 'quantity' => 1, 'substitutes' => [['menu_item_id' => $id('oxtail'), 'extra_minor' => 500]]],
                    ['menu_item_id' => $id('rice'), 'quantity' => 1, 'substitutes' => []],
                    ['menu_item_id' => $id('veg'), 'quantity' => 1, 'substitutes' => []],
                    ['menu_item_id' => $id('festival'), 'quantity' => 2, 'substitutes' => []],
                ],
                'choice_groups' => [],
                'add_ons' => [['menu_item_id' => $id('plantain'), 'price_minor' => 250, 'max' => 3]],
            ],
        ], $overrides));
    }

    private function place(array $items, array $extra = [], ?User $user = null): array
    {
        return app(OrderPlacementService::class)->placeOrder(Event::allTenants()->findOrFail($this->event->id), array_merge([
            'customer_name' => 'Dana Guest', 'customer_phone' => '+1 305 555 0123', 'method' => 'cash', 'items' => $items,
        ], $extra), $user ?? $this->buyer);
    }

    // ---- Scheduling -------------------------------------------------------------------------------------------

    public function test_an_end_time_before_the_start_time_runs_past_midnight(): void
    {
        $out = EventSchedule::resolve(['event_date_iso' => '2026-12-05', 'start_time' => '21:00', 'end_time' => '02:00'], self::TZ);

        $this->assertSame('2026-12-06 02:00', $out['ends_at']->setTimezone(self::TZ)->format('Y-m-d H:i'));
        $this->assertSame(5, (int) $out['starts_at']->diffInHours($out['ends_at']));
    }

    public function test_multi_day_events_and_out_of_order_times_are_checked(): void
    {
        $days = EventSchedule::resolve(['event_date_iso' => '2026-12-04', 'end_date_iso' => '2026-12-06', 'start_time' => '10:00', 'end_time' => '18:00'], self::TZ);
        $this->assertSame('2026-12-06 18:00', $days['ends_at']->setTimezone(self::TZ)->format('Y-m-d H:i'));

        foreach ([
            'end_date_iso' => ['event_date_iso' => '2026-12-04', 'end_date_iso' => '2026-12-03', 'start_time' => '10:00', 'end_time' => '18:00'],
            'end_time' => ['event_date_iso' => '2026-12-04', 'end_date_iso' => '2026-12-04', 'start_time' => '18:00', 'end_time' => '10:00'],
            'doors_open_time' => ['event_date_iso' => '2026-12-04', 'start_time' => '18:00', 'end_time' => '23:00', 'doors_open_time' => '19:00'],
            'setup_time' => ['event_date_iso' => '2026-12-04', 'start_time' => '18:00', 'end_time' => '23:00', 'doors_open_time' => '17:00', 'setup_time' => '17:30'],
            'food_service_end_time' => ['event_date_iso' => '2026-12-04', 'start_time' => '18:00', 'end_time' => '23:00', 'food_service_start_time' => '19:00', 'food_service_end_time' => '23:30'],
            'ticket_sales_end_at' => ['event_date_iso' => '2026-12-04', 'start_time' => '18:00', 'end_time' => '23:00', 'ticket_sales_end_at' => '2026-12-05 01:00'],
            'early_bird_ends_at' => ['event_date_iso' => '2026-12-04', 'start_time' => '18:00', 'end_time' => '23:00', 'early_bird_ends_at' => '2026-12-04 19:00'],
        ] as $field => $input) {
            try {
                EventSchedule::resolve($input, self::TZ);
                $this->fail("Expected a schedule error on {$field}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors(), "Wrong field for {$field}: " . json_encode($e->errors()));
            }
        }
    }

    public function test_late_night_food_service_and_pickup_windows_inside_it(): void
    {
        $out = EventSchedule::resolve(['event_date_iso' => '2026-12-05', 'start_time' => '20:00', 'end_time' => '03:00',
            'food_service_start_time' => '21:00', 'food_service_end_time' => '01:30'], self::TZ);
        $this->assertSame('21:00', $out['food_service_start_time']);

        $windows = EventSchedule::pickupWindows([['start' => '23:00', 'end' => '00:30', 'max_orders' => '20'], ['start' => '21:00', 'end' => '22:00']], '21:00', '01:30');
        $this->assertSame(['21:00', '23:00'], array_column($windows, 'start'));
        $this->assertSame(20, $windows[1]['max_orders']);

        $this->expectException(ValidationException::class);
        EventSchedule::pickupWindows([['start' => '19:00', 'end' => '20:00']], '21:00', '01:30');
    }

    public function test_display_date_and_time_are_written_from_the_schedule(): void
    {
        $data = EventDetails::prepare(['event_date_iso' => '2026-12-04', 'end_date_iso' => '2026-12-06', 'start_time' => '10:00', 'end_time' => '18:00',
            'timezone' => self::TZ, 'date_str' => '', 'time_str' => '']);

        $this->assertSame('Friday, December 4 – Sunday, December 6, 2026', $data['date_str']);
        $this->assertSame('10:00 AM – 6:00 PM', $data['time_str']);
    }

    // ---- Packages and mix-and-match --------------------------------------------------------------------------

    public function test_a_package_is_charged_once_with_its_upgrades_and_each_part_reserves_stock(): void
    {
        $package = $this->sundayDinner();
        $result = $this->place([['menu_item_id' => $package->id, 'quantity' => 2, 'package' => [
            'substitutions' => [0 => $this->items['oxtail']->id],
            'add_ons' => [$this->items['plantain']->id => 1],
        ]]]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $order = $result['order'];
        // (18.00 + 5.00 oxtail upgrade + 2.50 extra plantain) × 2; the included items add nothing.
        $this->assertSame(5100, (int) $order->subtotal_minor);
        $header = $order->items->firstWhere('line_type', OrderItem::PACKAGE);
        $this->assertSame(2550, (int) $header->unit_price_minor);
        $parts = $order->items->where('package_line_id', $header->id);
        $this->assertSame(['Oxtail' => 2, 'Rice and Peas' => 2, 'Steamed Vegetables' => 2, 'Festival' => 4, 'Extra Plantain' => 2],
            $parts->pluck('quantity', 'name')->map(fn ($q) => (int) $q)->all());
        $this->assertSame(0, (int) $parts->sum('line_total_minor'));
        $this->assertSame(3, (int) $this->items['oxtail']->fresh()->available_stock);
        $this->assertSame(20, (int) $this->items['chicken']->fresh()->available_stock, 'The swapped-out chicken is not reserved.');
        $this->assertSame(8, (int) $this->items['plantain']->fresh()->available_stock);
    }

    public function test_the_checkout_quote_and_the_placed_order_agree_for_packages_and_build_your_own(): void
    {
        $package = $this->sundayDinner();
        $this->items['chicken']->update(['pricing_type' => 'customizable', 'custom_option_groups' => [
            ['id' => 'g1', 'title' => 'Sauce', 'required' => false, 'max' => 1, 'choices' => [['label' => 'Extra jerk sauce', 'extraPriceUSD' => 1, 'extraPriceJMD' => 0]]],
            ['id' => 'g2', 'title' => 'Extras', 'required' => false, 'max' => 2, 'choices' => [
                ['label' => 'Coleslaw', 'extraPriceUSD' => 1.5, 'extraPriceJMD' => 0],
                ['label' => 'Bammy', 'extraPriceUSD' => 2, 'extraPriceJMD' => 0],
                ['label' => 'Avocado', 'extraPriceUSD' => 2.5, 'extraPriceJMD' => 0],
            ]],
        ]]);
        $cart = [
            'tiers' => [], 'key' => 'k1',
            'items' => [$this->items['chicken']->id => ['quantity' => 1, 'choices' => ['g2' => [0, 1]]], $this->items['plantain']->id => ['quantity' => 2]],
            'packages' => [['menu_item_id' => $package->id, 'quantity' => 1, 'selection' => ['add_ons' => [$this->items['plantain']->id => 2]]]],
        ];
        $quote = app(OrderQuoteService::class)->quote(Event::allTenants()->findOrFail($this->event->id), $cart);
        $this->assertSame([], $quote['problems']);
        // Chicken 12 + coleslaw 1.50 + bammy 2 (no sauce chosen, so none charged) + plantain 2 × 3.50 + package 18 + 2 × 2.50
        $this->assertSame(1550 + 700 + 2300, $quote['totals']->subtotalMinor);

        $result = $this->place(app(OrderQuoteService::class)->toOrderItems($cart));
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame($quote['total_minor'], (int) $result['order']->total_minor);
    }

    public function test_choice_limits_unavailable_items_and_per_customer_limits_are_enforced(): void
    {
        $this->items['chicken']->update(['pricing_type' => 'customizable', 'custom_option_groups' => [
            ['id' => 'g1', 'title' => 'Extras', 'required' => false, 'max' => 1, 'choices' => [['label' => 'A', 'extraPriceUSD' => 1], ['label' => 'B', 'extraPriceUSD' => 1]]],
        ]]);
        $tooMany = $this->place([['menu_item_id' => $this->items['chicken']->id, 'quantity' => 1, 'selected_choices' => ['g1' => [0, 1]]]]);
        $this->assertFalse($tooMany['success']);
        $this->assertStringContainsString('at most 1', $tooMany['error']);

        $this->items['fish']->update(['available_from' => now()->addDay()]);
        $later = $this->place([['menu_item_id' => $this->items['fish']->id, 'quantity' => 1]]);
        $this->assertFalse($later['success']);
        $this->assertStringContainsString('not available', $later['error']);

        $this->items['rice']->update(['max_per_order' => 3]);
        $this->assertTrue($this->place([['menu_item_id' => $this->items['rice']->id, 'quantity' => 2]])['success']);
        $again = $this->place([['menu_item_id' => $this->items['rice']->id, 'quantity' => 2]]);
        $this->assertFalse($again['success']);
        $this->assertStringContainsString('limited to 3 per customer', $again['error']);

        $this->items['plantain']->update(['available_stock' => 1]);
        $package = $this->sundayDinner();
        $short = $this->place([['menu_item_id' => $package->id, 'quantity' => 1, 'package' => ['add_ons' => [$this->items['plantain']->id => 2]]]]);
        $this->assertFalse($short['success']);
        $this->assertSame(1, (int) $this->items['plantain']->fresh()->available_stock, 'A refused order reserves nothing.');
    }

    // ---- Food service plan ------------------------------------------------------------------------------------

    public function test_closed_food_ordering_still_sells_tickets_and_event_day_orders_follow_service_hours(): void
    {
        $this->event->update(['advance_order_deadline' => now()->subHour()]);
        $food = $this->place([['menu_item_id' => $this->items['rice']->id, 'quantity' => 1]]);
        $this->assertFalse($food['success']);
        $this->assertStringContainsString('preorders', $food['error']);
        $this->assertTrue($this->place([['menu_item_id' => 'tier-ga', 'quantity' => 1]])['success'], 'Tickets stay on sale after food preorders close.');

        $now = Carbon::now(self::TZ);
        $this->event->update(['food_ordering_mode' => 'event_day', 'event_date_iso' => $now->toDateString(),
            'food_service_start_time' => $now->copy()->subHour()->format('H:i'), 'food_service_end_time' => $now->copy()->addHour()->format('H:i')]);
        $this->assertTrue($this->place([['menu_item_id' => $this->items['rice']->id, 'quantity' => 1]])['success'], 'Open during food service.');

        $this->event->update(['food_service_start_time' => $now->copy()->addHours(2)->format('H:i'), 'food_service_end_time' => $now->copy()->addHours(3)->format('H:i')]);
        $this->assertFalse($this->place([['menu_item_id' => $this->items['rice']->id, 'quantity' => 1]])['success']);
    }

    public function test_pickup_windows_fill_up_and_delivery_needs_a_table(): void
    {
        $this->event->update(['food_pickup_windows' => [['start' => '13:00', 'end' => '14:00', 'max_orders' => 1], ['start' => '14:00', 'end' => '15:00', 'max_orders' => null]],
            'food_fulfillment' => 'pickup_and_delivery', 'food_service_fee_minor' => 150]);
        $rice = [['menu_item_id' => $this->items['rice']->id, 'quantity' => 1]];

        $this->assertStringContainsString('pickup time', $this->place($rice)['error']);
        $first = $this->place($rice, ['food' => ['pickup_window' => '13:00-14:00']]);
        $this->assertTrue($first['success'], $first['error'] ?? '');
        $this->assertSame('13:00-14:00', $first['order']->pickup_window);
        $this->assertSame(400 + 150, (int) $first['order']->total_minor, 'The food service fee is charged once per food order.');
        $this->assertStringContainsString('is full', $this->place($rice, ['food' => ['pickup_window' => '13:00-14:00']])['error']);

        $this->assertStringContainsString('table number', $this->place($rice, ['food' => ['method' => 'delivery']])['error']);
        $delivered = $this->place($rice, ['food' => ['method' => 'delivery', 'delivery_location' => 'Table 12']]);
        $this->assertSame(['delivery', 'Table 12'], [$delivered['order']->food_fulfillment_method, $delivered['order']->delivery_location]);
    }

    public function test_early_bird_price_applies_until_it_ends_and_event_sales_window_limits_tiers(): void
    {
        $this->event->update(['ticket_tiers' => [['id' => 'tier-ga', 'name' => 'General Admission', 'priceUSD' => 10, 'earlyBirdPriceUSD' => 7.5, 'availableStock' => 50]],
            'early_bird_ends_at' => now()->addDay()]);
        $early = $this->place([['menu_item_id' => 'tier-ga', 'quantity' => 2]]);
        $this->assertSame(1500, (int) $early['order']->total_minor);

        $this->event->update(['early_bird_ends_at' => now()->subMinute()]);
        $this->assertSame(1000, (int) $this->place([['menu_item_id' => 'tier-ga', 'quantity' => 1]])['order']->total_minor);

        $this->event->update(['ticket_sales_end_at' => now()->subMinute()]);
        $closed = $this->place([['menu_item_id' => 'tier-ga', 'quantity' => 1]]);
        $this->assertFalse($closed['success']);
        $this->assertStringContainsString('have ended', $closed['error']);
    }

    // ---- Item-level fulfillment --------------------------------------------------------------------------------

    public function test_package_items_are_handed_over_one_by_one_and_complete_the_package(): void
    {
        $package = $this->sundayDinner(['package_config' => ['components' => [
            ['menu_item_id' => $this->items['chicken']->id, 'quantity' => 1, 'substitutes' => []],
            ['menu_item_id' => $this->items['festival']->id, 'quantity' => 2, 'substitutes' => []],
        ], 'choice_groups' => [], 'add_ons' => []]]);
        $order = $this->place([['menu_item_id' => $package->id, 'quantity' => 1]])['order'];
        $order->update(['status' => 'paid', 'amount_paid_minor' => $order->total_minor]);
        $host = User::create(['name' => 'Host', 'email' => 'h@example.test', 'password' => bcrypt('x'), 'status' => 'active']);
        \App\Models\UserRole::create(['user_id' => $host->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        \App\Models\OrganizerUser::create(['organizer_id' => $order->organizer_id, 'user_id' => $host->id, 'role' => 'event_owner', 'is_active' => true]);
        $ops = app(OrderOperationsService::class);
        $header = $order->items->firstWhere('line_type', OrderItem::PACKAGE);

        try {
            $ops->recordHandover($host, $order->fresh(), $header->id, 1, []);
            $this->fail('A package line must not be handed over as a whole.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('one by one', $e->getMessage());
        }

        $chicken = $order->items->first(fn ($i) => $i->name === 'Jerk Chicken');
        $festival = $order->items->first(fn ($i) => $i->name === 'Festival');
        $ops->recordHandover($host, $order->fresh(), $chicken->id, 1, []);
        $this->assertSame(0, (int) $header->fresh()->collected_quantity);
        $this->assertSame('collected', $chicken->fresh()->pickup_status);
        $this->assertSame('paid', $chicken->fresh()->payment_status);
        $ops->recordHandover($host, $order->fresh(), $festival->id, 2, []);

        $this->assertSame(1, (int) $header->fresh()->collected_quantity);
        $this->assertTrue((bool) Order::allTenants()->find($order->id)->all_handed_over);
    }

    // ---- Map pins ---------------------------------------------------------------------------------------------

    public function test_a_map_pin_needs_a_signed_lookup_result_and_is_cleared_when_the_address_changes(): void
    {
        $this->assertSame([], app(MapLocationService::class)->lookup('1 Park Ave, Miami, FL'), 'No provider configured: no lookup.');
        $data = EventDetails::prepare(['latitude' => 25.1, 'longitude' => -80.2, 'timezone' => self::TZ]);
        $this->assertArrayNotHasKey('latitude', $data, 'Typed-in coordinates are never saved as a pin.');

        config(['services.maps.provider' => 'mapbox', 'services.maps.mapbox_token' => 'test-token']);
        Http::fake(['api.mapbox.com/*' => Http::response(['features' => [
            ['id' => 'address.1', 'place_name' => '1 Park Ave, Miami, Florida 33101, United States', 'center' => [-80.19, 25.77]],
            ['id' => 'bad', 'place_name' => 'Nowhere', 'center' => [500, 500]],
        ]])]);
        $results = app(MapLocationService::class)->lookup('1 Park Ave, Miami, FL');
        $this->assertCount(1, $results, 'Out-of-range coordinates from the provider are dropped.');

        $pinned = EventDetails::prepare(['location_token' => $results[0]['token'], 'timezone' => self::TZ]);
        $this->assertSame([25.77, -80.19, 'mapbox'], [$pinned['latitude'], $pinned['longitude'], $pinned['location_source']]);
        $this->assertNotNull($pinned['location_verified_at']);

        $this->expectException(ValidationException::class);
        EventDetails::prepare(['location_token' => 'forged', 'timezone' => self::TZ]);
    }

    public function test_changing_the_address_removes_a_verified_pin(): void
    {
        $this->event->update(['street_address' => '1 Park Ave', 'latitude' => 25.77, 'longitude' => -80.19, 'location_source' => 'mapbox', 'location_verified_at' => now()]);
        $data = EventDetails::prepare(['street_address' => '9 Other St', 'timezone' => self::TZ], $this->event->fresh());

        $this->assertNull($data['latitude']);
        $this->assertNull($data['location_verified_at']);
    }

    // ---- Menu studio --------------------------------------------------------------------------------------------

    public function test_hosts_build_items_and_packages_in_the_menu_studio_and_preview_them(): void
    {
        Storage::fake('local');
        Storage::fake('s3_flyers');
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $eventId = 'evt-kingston-harbour-fry';

        $this->actingAs($host)->post(route('events.menu-studio.items.store', $eventId), [
            'name' => 'Coconut Rice and Peas', 'category' => 'Rice and Grains', 'price' => '4.50', 'description' => 'Kidney beans and coconut milk',
            'ingredients' => 'Rice, kidney beans, coconut milk, thyme', 'dietary_tags' => ['vegan'], 'allergens' => [], 'max_per_order' => 4,
            'option_groups' => [['title' => 'Size', 'mode' => 'required', 'choices' => "Regular\nLarge, 1.50"]],
            'photo' => UploadedFile::fake()->image('rice.jpg', 800, 600), 'image_is_illustrative' => '1', 'image_position' => '50% 20%',
        ])->assertRedirect(route('events.menu-studio', ['id' => $eventId, 'tab' => 'menu']))->assertSessionHas('status');
        $rice = MenuItem::allTenants()->where('event_id', $eventId)->where('name', 'Coconut Rice and Peas')->firstOrFail();
        $this->assertSame([450, 'customizable', ['vegan'], true], [(int) $rice->price_minor, $rice->pricing_type, $rice->dietary_tags, $rice->image_is_illustrative]);
        $this->assertSame(1.5, (float) $rice->custom_option_groups[0]['choices'][1]['extraPriceUSD']);
        $this->assertNotNull($rice->image_path);

        $main = MenuItem::allTenants()->where('event_id', $eventId)->where('item_type', 'item')->where('id', '!=', $rice->id)->firstOrFail();
        $this->actingAs($host)->post(route('events.menu-studio.packages.store', $eventId), [
            'name' => 'Island Plate', 'category' => 'Meal Packages', 'price' => '16.00',
            'components' => [['menu_item_id' => $main->id, 'quantity' => 1], ['menu_item_id' => $rice->id, 'quantity' => 1]],
            'add_ons' => [$rice->id => ['on' => '1', 'price' => '2.00', 'max' => 2]],
        ])->assertSessionHas('status');
        $package = MenuItem::allTenants()->where('event_id', $eventId)->where('item_type', 'package')->firstOrFail();
        $this->assertSame([$main->id, $rice->id], array_column($package->package_config['components'], 'menu_item_id'));
        $this->assertSame(200, $package->package_config['add_ons'][0]['price_minor']);

        $this->actingAs($host)->delete(route('events.menu-studio.destroy', [$eventId, $rice->id]))->assertSessionHas('error');
        $this->assertNotNull($rice->fresh(), 'An item used by a package cannot be removed.');

        $this->actingAs($host)->get(route('events.menu-studio', ['id' => $eventId, 'tab' => 'preview']))
            ->assertOk()->assertSee('Menu Builder')->assertSee('Package Builder')->assertSee('Attendee Preview')
            ->assertSee('Island Plate')->assertSee('Illustrative photo')->assertSee('Estimated food total');

        $stranger = User::create(['name' => 'Stranger', 'email' => 's@example.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->actingAs($stranger)->get(route('events.menu-studio', $eventId))->assertNotFound();
        $this->actingAs($stranger)->post(route('events.menu-studio.items.store', $eventId), ['name' => 'X', 'category' => 'Sides', 'price' => 1])->assertNotFound();
    }

    public function test_the_guest_food_page_shows_packages_and_build_your_own_and_saves_both(): void
    {
        $package = $this->sundayDinner();
        $this->event->update(['food_pickup_windows' => [['start' => '13:00', 'end' => '14:00', 'max_orders' => null]]]);

        $this->actingAs($this->buyer)->get(route('checkout.food', $this->event))
            ->assertOk()->assertSee('Meal packages')->assertSee('Build your own meal')->assertSee('Jamaican Sunday Dinner')
            ->assertSee('Swap for Oxtail')->assertSee('Pickup time');

        $this->actingAs($this->buyer)->post(route('checkout.food.save', $this->event), [
            'items' => [$this->items['plantain']->id => ['quantity' => 1]],
            'packages' => [['menu_item_id' => $package->id, 'quantity' => 1, 'substitutions' => [0 => $this->items['oxtail']->id]]],
            'food' => ['method' => 'pickup', 'pickup_window' => '13:00-14:00'],
        ])->assertRedirect(route('checkout.review', $this->event));

        $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))
            ->assertOk()->assertSee('Jamaican Sunday Dinner')->assertSee('Oxtail (instead of Jerk Chicken)')->assertSee('$26.50');
    }

    // ---- Interactive pieces -------------------------------------------------------------------------------------

    public function test_hosts_reorder_the_menu_by_drag_and_drop_or_the_arrow_buttons(): void
    {
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $eventId = 'evt-kingston-harbour-fry';
        $order = fn () => MenuItem::allTenants()->where('event_id', $eventId)->orderBy('sort_order')->orderBy('name')->pluck('id')->all();
        $before = $order();

        $reversed = array_reverse($before);
        $this->actingAs($host)->postJson(route('events.menu-studio.order', $eventId), ['ids' => $reversed])->assertOk()->assertJson(['saved' => true]);
        $this->assertSame($reversed, $order());

        // Without JavaScript the arrows are plain forms: move the second item up.
        $this->actingAs($host)->post(route('events.menu-studio.order', $eventId), ['move' => $reversed[1], 'direction' => 'up'])
            ->assertRedirect()->assertSessionHas('status', 'Menu order saved.');
        $this->assertSame([$reversed[1], $reversed[0]], array_slice($order(), 0, 2));

        $this->actingAs($host)->get(route('events.menu-studio', $eventId))->assertOk()
            ->assertSee('data-sortable', false)->assertSee('Move ' . MenuItem::allTenants()->find($reversed[1])->name . ' down', false);
        $stranger = User::create(['name' => 'S', 'email' => 'sx@example.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->actingAs($stranger)->postJson(route('events.menu-studio.order', $eventId), ['ids' => $before])->assertNotFound();
    }

    public function test_the_event_form_has_country_cards_and_a_live_ticket_designer_and_saves_countries(): void
    {
        $this->seed([\Database\Seeders\CulturalCatalogSeeder::class, FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $eventId = 'evt-kingston-harbour-fry';

        $page = $this->actingAs($host)->get(route('events.edit', $eventId))->assertOk();
        $page->assertSee('Countries and cuisines')->assertSee('data-country-picker', false)->assertSee('🇯🇲')
            ->assertSee('data-ticket-designer', false)->assertSee('Live preview')->assertSee('Sample. The QR code appears on each guest')
            ->assertDontSee('placeholder-flyer.jpg');

        $event = Event::allTenants()->findOrFail($eventId);
        $countries = \App\Models\Country::whereIn('id', ['JM', 'HT'])->pluck('id')->all();
        $this->assertCount(2, $countries, 'The catalog seeder provides Jamaica and Haiti.');
        $form = [
            'title' => $event->title, 'venue_name' => $event->venue_name, 'organizer_name' => $event->organizer_name,
            'organizer_phone' => $event->organizer_phone, 'event_date_iso' => substr((string) $event->event_date_iso, 0, 10),
            'advance_order_deadline' => $event->advance_order_deadline->copy()->setTimezone($event->timezone ?: self::TZ)->format('Y-m-d\TH:i'),
            'street_address' => $event->street_address, 'city' => $event->city, 'state_code' => $event->state_code,
            'zip_code' => $event->zip_code, 'country_code' => 'US', 'country_ids_submitted' => '1',
        ];

        $this->actingAs($host)->put(route('events.update', $eventId), $form + ['country_ids' => $countries])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing($countries, $event->countries()->pluck('countries.id')->all());

        $this->actingAs($host)->put(route('events.update', $eventId), $form + ['country_ids' => ['XX']])->assertSessionHasErrors('country_ids.0');
        $this->actingAs($host)->put(route('events.update', $eventId), $form)->assertSessionHasNoErrors();
        $this->assertSame([], $event->countries()->pluck('countries.id')->all(), 'Unticking every country clears them.');
    }

    public function test_an_issued_ticket_wears_its_design_and_can_be_printed_and_food_without_a_photo_shows_a_placeholder(): void
    {
        $this->event->update(['ticket_tiers' => [['id' => 'tier-ga', 'name' => 'General Admission', 'priceUSD' => 10, 'availableStock' => 50,
            'designStyle' => 'elegant_gold', 'accentColor' => 'gold']]]);
        $order = $this->place([['menu_item_id' => 'tier-ga', 'quantity' => 1]])['order'];
        $order->update(['status' => 'paid', 'amount_paid_minor' => $order->total_minor]);

        $this->actingAs($this->buyer)->get(route('tickets.show', $order))->assertOk()
            ->assertSee('data-design="elegant_gold"', false)->assertSee('data-accent="gold"', false)->assertSee('Print ticket');

        $this->actingAs($this->buyer)->get(route('checkout.food', $this->event))->assertOk()
            ->assertSee('No photo yet')->assertDontSee('placeholder-flyer.jpg');
    }
}
