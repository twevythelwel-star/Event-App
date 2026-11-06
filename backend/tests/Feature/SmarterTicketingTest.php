<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\MenuItem;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\TicketHold;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WaitlistEntry;
use App\Services\Checkout\TicketPricing;
use App\Services\Checkout\WaitlistService;
use App\Services\OrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smarter ticketing: price steps, early-bird and group prices, limited releases, checkout holds, discount codes,
 * promoters, transfers, waitlists, complimentary tickets and bundles — all under the same locks as ordinary sales.
 */
class SmarterTicketingTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $host;
    private User $buyer;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $organizer = Organizer::create(['name' => 'Tix Org', 'slug' => 'tix-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $organizer->id, 'slug' => 'tix', 'title' => 'Harbour Nights', 'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM', 'currency' => 'USD', 'venue_name' => 'Pier', 'venue_address' => 'Kingston',
            'organizer_name' => 'Tix Org', 'organizer_phone' => '+18765550000', 'advance_order_deadline' => now()->addWeek(),
            'status' => 'published', 'is_ordering_open' => true, 'timezone' => 'America/New_York',
            'ticket_tiers' => [
                ['id' => 'ga', 'name' => 'General', 'price_usd' => 20, 'availableStock' => 200, 'max_per_order' => 10,
                    'price_steps' => [['up_to' => 2, 'price_minor' => 1000], ['up_to' => 5, 'price_minor' => 2000], ['up_to' => 8, 'price_minor' => 2500]]],
                ['id' => 'vip', 'name' => 'VIP', 'price_usd' => 80, 'availableStock' => 50, 'max_per_order' => 6,
                    'releases' => [['quantity' => 2, 'opens_iso' => null], ['quantity' => 2, 'opens_iso' => now()->addDay()->toIso8601String()]]],
                ['id' => 'team', 'name' => 'Team', 'price_usd' => 10, 'availableStock' => 2, 'max_per_order' => 10,
                    'group_discounts' => [['min_quantity' => 2, 'percent' => 10]]],
            ],
        ]);
        HostPaymentPolicy::create(['organizer_id' => $organizer->id, 'enable_cash' => true, 'bank_transfer_instructions' => 'Bank: Example']);
        $this->host = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $this->host->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);
        $this->buyer = User::create(['name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '+18765550123', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'phone' => '+18765550124', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function order(array $quantities, ?User $as = null, array $extra = []): array
    {
        return app(OrderPlacementService::class)->placeOrder($this->event->fresh(), [
            'customer_name' => 'Buyer', 'customer_phone' => '+18765550123', 'method' => 'cash',
            'items' => collect($quantities)->map(fn ($qty, $tier) => ['menu_item_id' => $tier, 'quantity' => $qty])->values()->all(),
        ] + $extra, $as ?? $this->buyer);
    }

    private function updateTier(string $id, array $changes): void
    {
        $tiers = collect($this->event->fresh()->ticket_tiers)->map(fn ($t) => $t['id'] === $id ? array_merge($t, $changes) : $t)->all();
        Event::allTenants()->whereKey($this->event->id)->update(['ticket_tiers' => json_encode($tiers)]);
    }

    public function test_price_steps_advance_with_sales_and_a_crossing_purchase_pays_each_price(): void
    {
        $first = $this->order(['ga' => 1]);
        $this->assertSame(1000, (int) $first['order']->total_minor);
        // One left at $10, then $20: three tickets cost 10 + 20 + 20.
        $second = $this->order(['ga' => 3]);
        $this->assertTrue($second['success'], $second['error'] ?? '');
        $this->assertSame(5000, (int) $second['order']->total_minor);
        $this->assertSame([[1, 1000], [2, 2000]], $second['order']->items->map(fn ($i) => [(int) $i['quantity'], (int) $i['unit_price_minor']])->values()->all());
        // Sold 4: the listing now shows the $20 step with one left at it.
        $tier = collect(\App\Services\Checkout\TicketTierCatalog::sellable($this->event->fresh()))->firstWhere('id', 'ga');
        $this->assertSame(2000, $tier['price_minor']);
        $this->assertSame(1, $tier['price_step']['left_at_price']);
        // An expired order gives its tickets (and their place in the price steps) back.
        $second['order']->forceFill(['status' => 'expired', 'inventory_released_at' => now()])->save();
        $this->assertSame(1, TicketPricing::soldByTier($this->event)['ga']);
    }

    public function test_early_bird_prices_switch_at_their_date_in_any_currency(): void
    {
        $this->updateTier('ga', ['price_steps' => [], 'early_bird_price_minor' => 1500, 'early_bird_ends_iso' => now()->addHour()->toIso8601String()]);
        $this->assertSame(1500, (int) $this->order(['ga' => 1])['order']->total_minor);
        $this->travel(2)->hours();
        $this->assertSame(2000, (int) $this->order(['ga' => 1])['order']->total_minor);
    }

    public function test_limited_releases_and_group_prices(): void
    {
        $tooMany = $this->order(['vip' => 3]);
        $this->assertFalse($tooMany['success']);
        $this->assertStringContainsString("Only 2 'VIP' can be bought right now", $tooMany['error']);
        $this->assertTrue($this->order(['vip' => 2])['success']);
        $this->assertStringContainsString('More are released later', $this->order(['vip' => 1])['error']);
        $this->travel(25)->hours();
        $this->assertTrue($this->order(['vip' => 2])['success'], 'The second release opened.');

        $group = $this->order(['team' => 2]);
        $this->assertSame(1800, (int) $group['order']->total_minor, 'Two Team tickets at 10% off.');
    }

    public function test_checkout_holds_keep_tickets_for_the_buyer_for_a_few_minutes(): void
    {
        $this->actingAs($this->buyer)->post(route('checkout.tickets.save', $this->event), ['tiers' => ['team' => 2]])->assertSessionHasNoErrors();
        $this->assertSame(2, (int) TicketHold::where('user_id', $this->buyer->id)->sum('quantity'));

        // Someone else cannot take the held tickets, at the ticket step or at placement.
        $this->actingAs($this->other)->from(route('checkout.tickets', $this->event))->post(route('checkout.tickets.save', $this->event), ['tiers' => ['team' => 1]])
            ->assertSessionHas('error');
        $this->assertFalse($this->order(['team' => 1], $this->other)['success']);
        // The holder can buy them, and the hold is used up.
        $this->assertTrue($this->order(['team' => 2])['success']);
        $this->assertSame(0, TicketHold::count());

        // An expired hold frees the tickets.
        $this->updateTier('team', ['availableStock' => 2]);
        TicketHold::create(['event_id' => $this->event->id, 'ticket_tier_id' => 'team', 'quantity' => 2, 'user_id' => $this->buyer->id, 'source' => 'checkout', 'expires_at' => now()->subMinute()]);
        $this->assertTrue($this->order(['team' => 1], $this->other)['success']);
    }

    public function test_discount_codes_with_limits_dates_and_restrictions(): void
    {
        $this->actingAs($this->host)->post(route('events.ticketing.codes.store', $this->event->id), [
            'code' => 'harbour10', 'kind' => 'percent', 'amount' => 10, 'applies_to' => 'tickets', 'max_uses' => 1, 'ticket_tier_ids' => ['team'],
        ])->assertSessionHas('status');
        $this->assertSame('HARBOUR10', DiscountCode::firstOrFail()->code);

        // Applied at review: the quote shows the saving.
        $this->actingAs($this->buyer)->post(route('checkout.tickets.save', $this->event), ['tiers' => ['team' => 1]]);
        $this->actingAs($this->buyer)->from(route('checkout.review', $this->event))->post(route('checkout.code.apply', $this->event), ['discount_code' => 'Harbour10'])
            ->assertSessionHas('status');
        $this->actingAs($this->buyer)->get(route('checkout.review', $this->event))->assertSee('Code HARBOUR10');

        $used = $this->order(['team' => 1], extra: ['discount_code' => 'HARBOUR10']);
        $this->assertTrue($used['success'], $used['error'] ?? '');
        $this->assertSame(900, (int) $used['order']->total_minor);
        $this->assertDatabaseHas('discount_redemptions', ['order_id' => $used['order']->id, 'amount_minor' => 100]);

        // Used up; not valid for another ticket type; unknown and expired codes are refused.
        $this->assertStringContainsString('fully used', $this->order(['team' => 1], $this->other, ['discount_code' => 'HARBOUR10'])['error']);
        DiscountCode::create(['event_id' => $this->event->id, 'code' => 'OLD', 'kind' => 'fixed', 'amount' => 500, 'applies_to' => 'tickets', 'ends_at' => now()->subDay()]);
        $this->assertStringContainsString('expired', $this->order(['ga' => 1], extra: ['discount_code' => 'OLD'])['error']);
        DiscountCode::create(['event_id' => $this->event->id, 'code' => 'VIPONLY', 'kind' => 'fixed', 'amount' => 500, 'applies_to' => 'tickets', 'ticket_tier_ids' => ['vip']]);
        $this->assertStringContainsString('does not apply', $this->order(['ga' => 1], extra: ['discount_code' => 'VIPONLY'])['error']);
        // A cancelled order gives its use back.
        $used['order']->forceFill(['status' => 'cancelled'])->save();
        $this->assertSame(0, DiscountCode::where('code', 'HARBOUR10')->firstOrFail()->liveUses());
    }

    public function test_promoters_are_credited_for_sales_through_their_link(): void
    {
        $this->actingAs($this->host)->post(route('events.ticketing.promoters.store', $this->event->id), ['name' => 'DJ Rae', 'code' => 'rae', 'commission_percent' => 10])
            ->assertSessionHas('status');
        $rae = Affiliate::firstOrFail();

        $this->actingAs($this->buyer)->get(route('events.show', [$this->event->id, 'ref' => 'RAE']))->assertOk();
        $this->assertSame($rae->id, session("affiliate.{$this->event->id}"));
        $order = $this->order(['ga' => 2], extra: ['affiliate_id' => $rae->id])['order'];
        $this->assertSame($rae->id, $order->affiliate_id);
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();

        $report = app(\App\Services\Checkout\TicketingStudioService::class)->affiliateReport($this->event)->first();
        $this->assertSame([1, 2, 2000, 200], [$report['orders'], $report['tickets'], $report['revenue_minor'], $report['commission_minor']]);
        $this->actingAs($this->host)->get(route('events.ticketing', ['id' => $this->event->id, 'tab' => 'promoters']))->assertOk()->assertSee('DJ Rae')->assertSee('ref=RAE', false);
    }

    public function test_paid_tickets_transfer_securely_to_the_named_recipient(): void
    {
        $order = $this->order(['ga' => 1])['order'];
        $this->actingAs($this->buyer)->post(route('tickets.transfer.start', $order), ['email' => 'other@example.test'])->assertSessionHasErrors('transfer'); // unpaid
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        $oldToken = $order->secure_ticket_token;

        $this->actingAs($this->other)->post(route('tickets.transfer.start', $order), ['email' => 'other@example.test'])->assertSessionHasErrors('transfer'); // not theirs
        $this->actingAs($this->buyer)->post(route('tickets.transfer.start', $order), ['email' => 'other@example.test'])->assertSessionHas('transfer_link');
        $link = session('transfer_link');
        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->assertDatabaseMissing('ticket_transfers', ['token_hash' => $token]); // only the hash is stored

        $stranger = User::create(['name' => 'S', 'email' => 's@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($stranger)->get($link)->assertOk()->assertSee('Sign in with the right account');
        $this->actingAs($stranger)->post(route('tickets.transfer.confirm', $token))->assertSessionHas('error');

        $this->actingAs($this->other)->post(route('tickets.transfer.confirm', $token))->assertRedirect(route('tickets.show', $order));
        $order->refresh();
        $this->assertSame((int) $this->other->id, (int) $order->user_id);
        $this->assertNotSame($oldToken, $order->secure_ticket_token, 'The old QR code no longer admits anyone.');
        $this->actingAs($this->other)->post(route('tickets.transfer.confirm', $token))->assertSessionHas('error'); // one use only

        $this->event->forceFill(['allow_ticket_transfers' => false])->save();
        $this->actingAs($this->other)->post(route('tickets.transfer.start', $order), ['email' => 'buyer@example.test'])->assertSessionHasErrors('transfer');
    }

    public function test_waitlisted_buyers_are_offered_freed_tickets_in_turn(): void
    {
        $blocker = $this->order(['team' => 2], $this->other)['order'];
        $this->actingAs($this->buyer)->post(route('checkout.waitlist.join', $this->event), ['tier_id' => 'team', 'quantity' => 2])->assertSessionHas('status');
        $entry = WaitlistEntry::firstOrFail();

        $blocker->forceFill(['status' => 'expired', 'inventory_released_at' => now()])->save();
        $this->updateTier('team', ['availableStock' => 2]);
        $this->assertSame(1, app(WaitlistService::class)->offerFreedTickets($this->event->fresh()));
        $this->assertSame('offered', $entry->fresh()->status);
        $this->assertDatabaseHas('notification_logs', ['recipient' => 'buyer@example.test', 'trigger' => 'waitlist_offer']);

        // The offer is the buyer's: nobody else can take those tickets meanwhile.
        $third = User::create(['name' => 'T', 'email' => 't@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->assertFalse($this->order(['team' => 1], $third)['success']);
        $this->assertTrue($this->order(['team' => 2])['success']);
        $this->assertSame('purchased', $entry->fresh()->status);
    }

    public function test_hosts_issue_complimentary_tickets_from_stock(): void
    {
        $this->actingAs($this->buyer)->post(route('events.ticketing.complimentary', $this->event->id), ['tier_id' => 'team', 'quantity' => 1, 'email' => 'other@example.test', 'reason' => 'Press'])
            ->assertNotFound();
        $this->actingAs($this->host)->post(route('events.ticketing.complimentary', $this->event->id), ['tier_id' => 'team', 'quantity' => 1, 'email' => 'nobody@example.test', 'reason' => 'Press'])
            ->assertSessionHasErrors('ticketing');
        $this->actingAs($this->host)->post(route('events.ticketing.complimentary', $this->event->id), ['tier_id' => 'team', 'quantity' => 2, 'email' => 'OTHER@example.test', 'reason' => 'Press: Island Times'])
            ->assertSessionHas('status');

        $comp = Order::allTenants()->where('is_complimentary', true)->firstOrFail();
        $this->assertSame(['paid', 0, (int) $this->other->id, (int) $this->host->id], [$comp->status, (int) $comp->total_minor, (int) $comp->user_id, (int) $comp->issued_by]);
        $this->assertSame(0, (int) collect($this->event->fresh()->ticket_tiers)->firstWhere('id', 'team')['availableStock']);
        $this->assertFalse($this->order(['team' => 1])['success'], 'Comps came out of the same stock.');
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'complimentary_tickets_issued', 'target_id' => $comp->id]);
    }

    public function test_bundles_include_food_reserved_from_stock(): void
    {
        $dish = MenuItem::allTenants()->create(['organizer_id' => $this->event->organizer_id, 'event_id' => $this->event->id, 'name' => 'Jerk Plate',
            'category' => 'Mains', 'pricing_type' => 'fixed', 'price_usd' => 12, 'price_minor' => 1200, 'currency' => 'USD', 'available_stock' => 3, 'is_active' => true]);
        $this->actingAs($this->host)->post(route('events.ticketing.pricing', [$this->event->id, 'vip']), [
            'releases' => [['quantity' => 10]], 'bundle_items' => [['menu_item_id' => $dish->id, 'quantity' => 1]],
        ])->assertSessionHas('status');

        $order = $this->order(['vip' => 2]);
        $this->assertTrue($order['success'], $order['error'] ?? '');
        $this->assertSame(16000, (int) $order['order']->total_minor, 'The food is part of the ticket price.');
        $part = $order['order']->items->firstWhere('menu_item_id', $dish->id);
        $this->assertSame([2, 0], [(int) $part->quantity, (int) $part->unit_price_minor]);
        $this->assertSame(1, (int) $dish->fresh()->available_stock);
        $this->assertStringContainsString('only 1 are left', $this->order(['vip' => 2])['error']);
    }
}
