<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPlacementService;
use App\Support\TicketTheme;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ticket themes follow the kind of event the host declared; "Add to calendar" gives the holder an .ics file. */
class TicketThemeTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function attendee(): User
    {
        return User::where('email', 'attendee@islandevents.example')->firstOrFail();
    }

    private function order(): Order
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->where('is_active', true)->firstOrFail();
        $result = app(OrderPlacementService::class)->placeOrder($event, [
            'customer_name' => 'Ticket Holder', 'customer_phone' => '+1 305 555 0123', 'method' => 'cash',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ], $this->attendee());
        $this->assertTrue($result['success'], $result['error'] ?? 'order placed');

        return $result['order']->fresh();
    }

    public function test_the_theme_follows_the_declared_kind_of_event_never_the_title(): void
    {
        $event = new Event(['title' => 'Reggae Wedding Conference', 'category' => null]);
        $this->assertSame('classic', TicketTheme::for($event)->key, 'the title alone chooses nothing');

        $event->photo_subject = 'wedding';
        $this->assertSame('celebration', TicketTheme::for($event)->key);
        $event->photo_subject = 'dancehall_event';
        $this->assertSame('island', TicketTheme::for($event)->key);
        $event->photo_subject = 'corporate_event';
        $this->assertSame('professional', TicketTheme::for($event)->key);

        // No kind of event: the host's chosen category.
        $event->photo_subject = null;
        $event->category = 'Music and Nightlife';
        $this->assertSame('stage', TicketTheme::for($event)->key);
        $event->category = 'Food Festival';
        $this->assertSame('feast', TicketTheme::for($event)->key);
        $this->assertSame('classic', TicketTheme::for(null)->key);
    }

    public function test_the_ticket_wears_its_theme_and_keeps_the_qr_rules(): void
    {
        $order = $this->order(); // cash at pickup: not paid yet
        $page = $this->actingAs($this->attendee())->get(route('tickets.show', $order))->assertOk();
        $page->assertSee('eb-ticket-theme-island', false)->assertSee('Caribbean · Admission pass');
        $page->assertSee('City &amp; state', false)->assertSee('Lauderhill, FL');
        $page->assertDontSee('data-qr-type="admission"', false); // no QR until paid

        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        $this->get(route('tickets.show', $order))->assertOk()->assertSee('data-qr-type="admission"', false);
    }

    public function test_add_to_calendar_gives_the_holder_an_ics_file_without_the_qr_token(): void
    {
        $order = $this->order();
        $response = $this->actingAs($this->attendee())->get(route('tickets.calendar', $order))->assertOk();
        $this->assertStringStartsWith('text/calendar', $response->headers->get('Content-Type'));
        $ics = $response->getContent();
        $this->assertStringContainsString("BEGIN:VEVENT\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:South Florida Jamaican Fish Fry', $ics);
        $this->assertStringContainsString($order->ticket_code, $ics);
        $this->assertStringNotContainsString((string) $order->secure_ticket_token, $ics);
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'lines are folded at 75 octets');
        }

        // Someone else's ticket is not found.
        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($stranger)->get(route('tickets.calendar', $order))->assertNotFound();
    }
}
