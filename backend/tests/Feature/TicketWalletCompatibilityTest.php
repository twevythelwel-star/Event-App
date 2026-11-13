<?php

namespace Tests\Feature;

use App\Models\AttendeeTicket;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\TicketAdmission;
use App\Models\User;
use App\Services\OrderPlacementService;
use App\Services\Tickets\AttendeeTicketService;
use App\Services\Wallet\AppleWalletPackageValidator;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WalletTestCertificates;
use Tests\TestCase;

/**
 * Wallet tickets and attendee QR codes work together: the Apple Wallet pass carries the order's pass (it admits
 * the whole party), each guest's own code admits one person, and neither can be used to admit anyone twice.
 */
class TicketWalletCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    private WalletTestCertificates $certs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        $this->certs = new WalletTestCertificates();
        config($this->certs->config());
        Event::allTenants()->findOrFail(self::EVENT)->forceFill([
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(5), 'event_date_iso' => now()->toDateString(),
            'status' => 'published', 'is_ordering_open' => true, 'doors_open_time' => null,
            'ticket_tiers' => [['id' => 'tier-ga', 'name' => 'General Admission', 'price_jmd' => 2000, 'price_usd' => 15, 'quantity_available' => 50, 'max_per_order' => 4]],
        ])->save();
    }

    protected function tearDown(): void
    {
        $this->certs->cleanUp();
        parent::tearDown();
    }

    private function holder(): User
    {
        return User::where('email', 'attendee@islandevents.example')->firstOrFail();
    }

    private function paidOrder(int $tickets): Order
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);
        $result = app(OrderPlacementService::class)->placeOrder($event, [
            'customer_name' => 'Wallet Holder', 'customer_phone' => '+1 305 555 0142', 'method' => 'cash',
            'items' => [['menu_item_id' => 'tier-ga', 'quantity' => $tickets]],
        ], $this->holder());
        $this->assertTrue($result['success'], $result['error'] ?? 'order placed');
        $order = $result['order']->fresh();
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();

        return $order->fresh();
    }

    /** The validated pass.json Apple Wallet would install. */
    private function pass(Order $order): array
    {
        $bytes = $this->actingAs($this->holder())->get(route('tickets.wallet', $order))->assertOk()->getContent();
        $result = app(AppleWalletPackageValidator::class)->validate($bytes, $this->certs->caPath);
        $this->assertTrue($result['valid'], implode("\n", $result['problems']));

        return $result['pass'];
    }

    private function checker(): User
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);
        $user = User::create(['name' => 'Wallet Gate', 'email' => 'wallet-gate@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        EventWorkerAssignment::create(['organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'user_id' => $user->id,
            'worker_role' => 'ticket_checker', 'station_assignment' => 'all', 'status' => 'active', 'is_active' => true]);

        return $user;
    }

    private function scan(User $checker, string $code)
    {
        return $this->actingAs($checker)->postJson('/worker/events/' . self::EVENT . '/scan', ['ticket_code' => $code]);
    }

    public function test_the_wallet_pass_admits_the_party_and_uses_up_every_guest_code(): void
    {
        $order = $this->paidOrder(2);
        $pass = $this->pass($order);
        $barcode = $pass['barcodes'][0];

        $this->assertSame('PKBarcodeFormatQR', $barcode['format']);
        $this->assertStringStartsWith('tok_', $barcode['message']);
        $this->assertStringNotContainsString($order->id, $barcode['message']);
        $this->assertStringNotContainsString('Wallet Holder', json_encode($barcode));

        $checker = $this->checker();
        $this->scan($checker, $barcode['message'])->assertJson(['success' => true, 'result' => 'admit', 'headline' => 'ADMIT · Whole party on this order']);
        $this->assertSame(2, TicketAdmission::where('order_id', $order->id)->where('admitted_via', 'order_pass')->count());

        // The guests' own codes are used up with it, and the wallet pass cannot be scanned twice.
        $guest = AttendeeTicket::where('order_id', $order->id)->where('sequence', 2)->firstOrFail();
        $this->scan($checker, app(AttendeeTicketService::class)->credential($guest))->assertJson(['success' => false, 'already_admitted' => true]);
        $this->scan($checker, $barcode['message'])->assertJson(['success' => false, 'already_admitted' => true]);
    }

    public function test_after_a_guest_comes_in_on_their_own_code_the_wallet_pass_cannot_readmit_the_party(): void
    {
        $order = $this->paidOrder(2);
        $message = $this->pass($order)['barcodes'][0]['message'];
        $checker = $this->checker();

        $guest = AttendeeTicket::where('order_id', $order->id)->where('sequence', 1)->firstOrFail();
        $this->scan($checker, app(AttendeeTicketService::class)->credential($guest))->assertJson(['success' => true]);

        $this->scan($checker, $message)->assertJson(['success' => false, 'already_admitted' => true, 'result' => 'reject']);
        $this->assertSame(1, TicketAdmission::where('order_id', $order->id)->count());
    }

    public function test_replacing_one_guest_code_leaves_the_wallet_pass_working_and_vice_versa(): void
    {
        $order = $this->paidOrder(2);
        $message = $this->pass($order)['barcodes'][0]['message'];
        $guest = AttendeeTicket::where('order_id', $order->id)->where('sequence', 1)->firstOrFail();

        // One guest's replacement does not touch the order's pass in the wallet.
        $this->actingAs($this->holder())->post(route('tickets.guests.reissue', [$order, $guest]), ['reason' => 'Shared screenshot'])->assertSessionHas('success');
        $this->assertSame(Order::hashTicketToken($message), $order->fresh()->secure_ticket_token_hash);

        // Replacing the order's QR updates the wallet barcode and every guest code together.
        $before = AttendeeTicket::where('order_id', $order->id)->pluck('token_hash')->all();
        $this->actingAs($this->holder())->post(route('tickets.reissue', $order))->assertSessionHas('success');
        $after = $this->pass($order->fresh())['barcodes'][0]['message'];
        $this->assertNotSame($message, $after);
        $this->assertEmpty(array_intersect($before, AttendeeTicket::where('order_id', $order->id)->pluck('token_hash')->all()));
        $this->scan($this->checker(), $message)->assertNotFound();
        $this->scan(User::where('email', 'wallet-gate@example.test')->first(), $after)->assertJson(['success' => true]);
    }
}
