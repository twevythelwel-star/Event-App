<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSponsor;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOffer;
use App\Support\GeneratedFlyer;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vendor and exhibitor registration (booth types, applications, decisions, fees) and sponsor management (packages,
 * levels, logos, credits on the event page, generated flyer and tickets).
 */
class VendorsAndSponsorsTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
        Event::allTenants()->whereKey(self::EVENT)->update(['status' => 'published']);
    }

    private function host(): User
    {
        return User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    private function vendor(string $email = 'grill@example.test'): User
    {
        return User::firstOrCreate(['email' => $email], ['name' => 'Grill Owner', 'phone' => '+18765550199', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function application(array $overrides = [], ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->vendor())->post(route('vendors.apply.store', self::EVENT), $overrides + [
            'category' => 'food', 'business_name' => 'Smoke Pit Grill', 'contact_name' => 'Grill Owner', 'email' => 'grill@example.test',
            'phone' => '+18765550199', 'description' => 'Jerk pork and roast corn from a drum grill.', 'needs' => 'Power and water', 'show_publicly' => '1',
        ]);
    }

    public function test_vendors_apply_only_while_the_host_takes_applications(): void
    {
        $this->actingAs($this->vendor())->get(route('vendors.apply', self::EVENT))->assertOk()->assertSee('Not taking applications');
        $this->application()->assertSessionHasErrors('application');

        $this->actingAs($this->host())->post(route('events.partners.settings', self::EVENT), ['open' => '1', 'terms' => 'Bring your own tent.'])->assertSessionHas('status');
        $this->application()->assertSessionHasErrors('agree');
        $this->application(['agree' => '1'])->assertRedirect(route('vendors.mine'));
        $this->application(['agree' => '1'])->assertSessionHasErrors('application'); // one live application per business account

        $this->actingAs($this->vendor())->get(route('vendors.mine'))->assertOk()->assertSee('Smoke Pit Grill')->assertSee('Waiting for a decision');
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'vendor_application_submitted']);
    }

    public function test_hosts_sell_booths_and_decide_on_applications(): void
    {
        $host = $this->host();
        $this->actingAs($host)->post(route('events.partners.settings', self::EVENT), ['open' => '1']);
        $this->actingAs($host)->post(route('events.partners.offers.store', self::EVENT), ['name' => 'Food booth with power', 'category' => 'food', 'price' => '150', 'quantity' => 1])
            ->assertSessionHas('status');
        $offer = VendorOffer::where('event_id', self::EVENT)->firstOrFail();
        $this->assertSame(15000, $offer->price_minor);

        $this->application(['offer_id' => $offer->id]);
        $app = VendorApplication::where('event_id', self::EVENT)->firstOrFail();
        $this->actingAs($host)->get(route('events.partners', self::EVENT))->assertOk()->assertSee('Smoke Pit Grill')->assertSee('Power and water');

        $this->actingAs($host)->post(route('events.partners.applications.decide', [self::EVENT, $app->id]), ['decision' => 'approve', 'booth_label' => 'B4', 'note' => 'Set up by 2 PM.'])
            ->assertSessionHas('status');
        $app->refresh();
        $this->assertSame(['approved', 15000, 'unpaid', 'B4'], [$app->status, $app->fee_minor, $app->fee_status, $app->booth_label]);

        // The only booth of that type is taken: a second food vendor cannot get it.
        $other = $this->vendor('other@example.test');
        $this->application(['offer_id' => $offer->id, 'business_name' => 'Other'], $other)->assertSessionHasErrors('application');

        $this->actingAs($host)->post(route('events.partners.applications.fee', [self::EVENT, $app->id]), ['fee_status' => 'paid'])->assertSessionHas('status');
        $this->assertSame('paid', $app->fresh()->fee_status);
        $this->actingAs($this->vendor())->get(route('vendors.mine'))->assertSee('Food booth with power')->assertSee('B4')->assertSee('Set up by 2 PM.');
        // With the fee paid, the vendor withdraws through the host (refunds happen outside EventBite).
        $this->actingAs($this->vendor())->post(route('vendors.withdraw', $app->id))->assertSessionHas('error');

        // Approved vendors who agreed to be listed appear on the event page.
        $this->actingAs($other)->get(route('events.show', self::EVENT))->assertOk()->assertSee('Vendors & exhibitors', false)->assertSee('Smoke Pit Grill')->assertSee('Booth B4');
    }

    public function test_applicants_and_other_hosts_cannot_reach_the_hosts_tools(): void
    {
        $vendor = $this->vendor();
        $this->actingAs($vendor)->get(route('events.partners', self::EVENT))->assertNotFound();
        $this->actingAs($vendor)->post(route('events.partners.offers.store', self::EVENT), ['name' => 'Free', 'price' => 0])->assertNotFound();

        $this->actingAs($this->host())->post(route('events.partners.settings', self::EVENT), ['open' => '1']);
        $this->application();
        $app = VendorApplication::firstOrFail();
        $this->actingAs($vendor)->post(route('events.partners.applications.decide', [self::EVENT, $app->id]), ['decision' => 'approve'])->assertNotFound();
        $this->assertSame('pending', $app->fresh()->status);
        // Another applicant cannot withdraw someone else's application.
        $this->actingAs($this->vendor('nosy@example.test'))->post(route('vendors.withdraw', $app->id))->assertNotFound();
    }

    public function test_sponsors_are_credited_by_level_on_the_page_flyer_and_ticket(): void
    {
        $host = $this->host();
        $this->actingAs($host)->post(route('events.partners.packages.store', self::EVENT), ['level' => 'gold', 'name' => 'Gold partner', 'price' => '5000', 'quantity' => 1, 'benefits' => 'Logo on tickets'])
            ->assertSessionHas('status');
        $gold = \App\Models\SponsorshipPackage::where('event_id', self::EVENT)->firstOrFail();

        $this->actingAs($host)->post(route('events.partners.sponsors.store', self::EVENT), [
            'name' => 'Harbour Bank', 'level' => 'bronze', 'package_id' => $gold->id, 'website' => 'https://harbourbank.example',
            'logo' => UploadedFile::fake()->image('logo.png', 600, 200), 'logo_alt_text' => 'Harbour Bank logo',
            'show_on_page' => '1', 'show_on_flyer' => '1', 'show_on_ticket' => '1',
        ])->assertSessionHas('status');
        $bank = EventSponsor::where('event_id', self::EVENT)->firstOrFail();
        $this->assertSame('gold', $bank->level, 'The package sets the level.');
        $this->assertCount(3, $bank->logo_variants);

        $this->actingAs($host)->post(route('events.partners.sponsors.store', self::EVENT), ['name' => 'Second', 'level' => 'gold', 'package_id' => $gold->id])
            ->assertSessionHasErrors('partners'); // the Gold package had one place
        $this->actingAs($host)->post(route('events.partners.sponsors.store', self::EVENT), ['name' => 'Bad link', 'level' => 'silver', 'website' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('partners');
        $this->actingAs($host)->post(route('events.partners.sponsors.store', self::EVENT), ['name' => 'Corner Shop', 'level' => 'bronze', 'show_on_page' => '1']);

        $page = $this->actingAs($this->vendor())->get(route('events.show', self::EVENT))->assertOk();
        $page->assertSee('Sponsors')->assertSee('alt="Harbour Bank logo"', false)->assertSee('rel="noopener sponsored"', false);
        $page->assertSee('class="eb-sponsor-name">Corner Shop</span>', false); // no logo: the name, never a made-up logo
        $this->assertLessThan(strpos($page->getContent(), 'Corner Shop'), strpos($page->getContent(), 'Harbour Bank logo'), 'Gold before Bronze.');

        $svg = GeneratedFlyer::svg(Event::allTenants()->findOrFail(self::EVENT));
        $this->assertStringContainsString('With support from Harbour Bank', $svg);
        $this->assertStringNotContainsString('Corner Shop', $svg);

        auth()->logout();
        $this->get("/api/v1/media/sponsors/{$bank->id}/logo?variant=small")->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_an_approved_sponsor_application_becomes_a_sponsor_once(): void
    {
        $host = $this->host();
        $this->actingAs($host)->post(route('events.partners.settings', self::EVENT), ['open' => '1']);
        $this->application(['category' => 'sponsor', 'business_name' => 'Island Juice Co', 'website' => 'https://juice.example']);
        $app = VendorApplication::firstOrFail();
        $this->actingAs($host)->post(route('events.partners.applications.decide', [self::EVENT, $app->id]), ['decision' => 'approve']);

        $this->actingAs($host)->post(route('events.partners.applications.sponsor', [self::EVENT, $app->id]), ['level' => 'silver'])->assertSessionHas('status');
        $this->actingAs($host)->post(route('events.partners.applications.sponsor', [self::EVENT, $app->id]), ['level' => 'silver'])->assertSessionHasErrors('partners');
        $sponsor = EventSponsor::where('application_id', $app->id)->firstOrFail();
        $this->assertSame(['Island Juice Co', 'silver', 'https://juice.example'], [$sponsor->name, $sponsor->level, $sponsor->website]);
        $this->assertNull($sponsor->logo_path, 'No logo is invented; the host adds the real one.');
    }
}
