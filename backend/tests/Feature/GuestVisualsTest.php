<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\GeneratedFlyer;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guest-facing visual pass: generated cover artwork, the homepage hero and tradition cards, sample photos
 * labelled as illustrative, dietary and payment icons, and the checkout progress marks.
 */
class GuestVisualsTest extends TestCase
{
    use RefreshDatabase;

    private const KINGSTON = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    public function test_generated_covers_are_layered_artwork_with_their_own_palette_and_no_photography(): void
    {
        $events = Event::withoutGlobalScopes()->whereNull('flyer_path')->take(6)->get();
        $svgs = $events->map(fn (Event $event) => GeneratedFlyer::svg($event));

        foreach ($svgs as $i => $svg) {
            $this->assertStringContainsString('<svg', $svg);
            $this->assertStringContainsString('id="glow2"', $svg, 'Two lights, layered.');
            $this->assertStringContainsString('stroke-dasharray', $svg, 'Decorative arcs.');
            $this->assertStringNotContainsString('<image', $svg, 'No photography is embedded.');
            $this->assertStringContainsString(htmlspecialchars($events[$i]->title, ENT_XML1 | ENT_QUOTES), $svg);
            $this->assertNotFalse(simplexml_load_string($svg), 'Valid SVG.');
        }
        $this->assertGreaterThan(1, $svgs->map(fn ($svg) => preg_replace('/aria-label="[^"]*"|<text[^>]*>[^<]*<\/text>/', '', $svg))->unique()->count(),
            'Events get different compositions.');
    }

    public function test_the_homepage_has_the_cinematic_hero_step_icons_and_tradition_cards(): void
    {
        $page = $this->get(route('home'))->assertOk();

        $page->assertSee('eb-hero-cinematic', false)->assertSee('eb-hero-art', false)->assertSee('Featured: ', false)
            ->assertSee('Upcoming events')->assertSee('eb-step-icon', false)->assertSee('eb-tradition-card', false)
            ->assertSee('aria-hidden="true"', false);
        // The decorative layers are hidden from assistive technology; the featured cover has a real description.
        $this->assertMatchesRegularExpression('/class="eb-hero-backdrop" aria-hidden="true"/', $page->getContent());
    }

    public function test_bundled_sample_photos_are_always_labelled_illustrative_and_dietary_tags_carry_icons(): void
    {
        $item = MenuItem::allTenants()->where('event_id', self::KINGSTON)->where('image_url', 'like', '/images/%')->firstOrFail();
        $item->update(['image_is_illustrative' => false, 'dietary_tags' => ['vegan', 'gluten_free'], 'spice_level' => 'hot']);
        $this->assertTrue($item->fresh()->photoIsIllustrative(), 'A bundled sample is never presented as the dish.');

        $html = view('components.food-card', ['item' => $item->fresh(), 'currency' => 'USD', 'slot' => new \Illuminate\Support\HtmlString('')])->render();
        $this->assertStringContainsString('Illustrative photo', $html);
        $this->assertStringContainsString('alt="Illustrative photo"', $html);
        $this->assertStringContainsString('Vegan', $html);
        $this->assertStringContainsString('Gluten-free', $html);
        $this->assertStringContainsString('eb-badge-icon', $html);
        $this->assertStringContainsString('Hot', $html);

        $noPhoto = MenuItem::allTenants()->where('event_id', self::KINGSTON)->firstOrFail()->replicate(['id']);
        $noPhoto->forceFill(['image_url' => null, 'image_path' => null]);
        $placeholder = view('components.food-card', ['item' => $noPhoto, 'currency' => 'USD', 'slot' => new \Illuminate\Support\HtmlString('')])->render();
        $this->assertStringContainsString('Photo coming soon', $placeholder);
        $this->assertStringNotContainsString('<img', $placeholder);
    }

    public function test_payment_choices_have_their_own_illustrations_and_checkout_steps_mark_progress(): void
    {
        $icons = collect(['bank', 'zelle', 'cash'])->map(fn ($value) => view('components.payment-method', ['value' => $value, 'label' => $value, 'icon' => 'payment', 'attributes' => new \Illuminate\View\ComponentAttributeBag()])->render());
        $this->assertStringContainsString('M3 10 12 4l9 6', $icons[0], 'Bank building.');
        $this->assertStringContainsString('rx="2.5"', $icons[1], 'Phone for Zelle.');
        $this->assertStringContainsString('r="2.5"', $icons[2], 'Cash notes.');

        $event = Event::withoutGlobalScopes()->findOrFail(self::KINGSTON);
        $html = view('checkout._steps', ['event' => $event, 'current' => 'Food', 'steps' => [
            ['label' => 'Tickets', 'route' => 'checkout.tickets'], ['label' => 'Food', 'route' => 'checkout.food'], ['label' => 'Checkout', 'route' => 'checkout.review'],
        ]])->render();
        $this->assertStringContainsString('is-done', $html);
        $this->assertStringContainsString('Done:', $html);
        $this->assertStringContainsString('aria-current="step"', $html);
    }
}
