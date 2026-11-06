<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Support\ImageSubjects;
use App\Support\GeneratedFlyer;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Images appear only where they are true: a sample dish photo only on that dish, never stock photography as an
 * event's cover, the host's own flyer never replaced, and a clearly labelled, category-specific fallback otherwise.
 */
class ImageAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function item(array $attributes): MenuItem
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', self::EVENT)->firstOrFail()->replicate(['id']);

        return $item->forceFill($attributes + ['image_path' => null, 'image_moderation_status' => 'approved', 'item_type' => 'item']);
    }

    private function card(MenuItem $item): string
    {
        return view('components.food-card', ['item' => $item, 'currency' => 'USD', 'layout' => 'card',
            'slot' => new \Illuminate\Support\HtmlString(''), 'attributes' => new \Illuminate\View\ComponentAttributeBag()])->render();
    }

    public function test_a_sample_dish_photo_appears_only_on_the_dish_it_shows(): void
    {
        $this->assertSame('/images/jerk_chicken.jpg', $this->item(['name' => 'Jerk Chicken Plate', 'image_url' => '/images/jerk_chicken.jpg'])->display_image_url);
        $this->assertSame('/images/escovitch_fish.jpg', $this->item(['name' => 'MoBay Escovitch Snapper & Festivals', 'image_url' => '/images/escovitch_fish.jpg'])->display_image_url);

        // The same photos on other food are not shown: jerk chicken is not sushi, escovitch fish is not lobster, a
        // side of bammy is not coconut water.
        $this->assertNull($this->item(['name' => 'Salmon Sushi Roll', 'image_url' => '/images/jerk_chicken.jpg'])->display_image_url);
        $this->assertNull($this->item(['name' => 'Grilled Caribbean Spiny Lobster Tail', 'image_url' => '/images/escovitch_fish.jpg'])->display_image_url);
        $this->assertNull($this->item(['name' => 'Ice Cold Jelly Coconut Water', 'image_url' => '/images/bammy.jpg'])->display_image_url);
        $this->assertNull($this->item(['name' => 'Jerk Pork Plate', 'image_url' => '/images/jerk_chicken.jpg'])->display_image_url, 'Pork is not chicken.');

        // A bundled file nobody catalogued is never shown, whatever it is called.
        $this->assertNull($this->item(['name' => 'Festival', 'image_url' => '/images/dish_jm_festival.jpg'])->display_image_url);
    }

    public function test_a_mismatched_or_missing_photo_shows_a_labelled_fallback_for_that_kind_of_food(): void
    {
        $sushi = $this->card($this->item(['name' => 'Salmon Sushi Roll', 'category' => 'Seafood', 'image_url' => '/images/jerk_chicken.jpg']));
        $this->assertStringNotContainsString('jerk_chicken.jpg', $sushi);
        $this->assertStringContainsString('data-fallback="fish"', $sushi);
        $this->assertStringContainsString('<strong>Seafood</strong> · No photo yet', $sushi);

        $drink = $this->card($this->item(['name' => 'Sorrel', 'category' => 'Drinks', 'image_url' => null]));
        $this->assertStringContainsString('data-fallback="cup"', $drink);
        $this->assertStringContainsString('<strong>Drinks</strong> · No photo yet', $drink);

        $this->assertSame(['icon' => 'cake', 'label' => 'Desserts'], ImageSubjects::menuFallback($this->item(['category' => 'Desserts'])));
        $this->assertSame(['icon' => 'leaf', 'label' => 'Vegan'], ImageSubjects::menuFallback($this->item(['category' => 'Vegan'])));
        $this->assertSame('package', ImageSubjects::menuFallback($this->item(['item_type' => 'package']))['icon']);

        // The matching sample still shows, labelled as illustrative.
        $jerk = $this->card($this->item(['name' => 'Jerk Chicken Plate', 'image_url' => '/images/jerk_chicken.jpg']));
        $this->assertStringContainsString('/images/jerk_chicken.jpg', $jerk);
        $this->assertStringContainsString('Illustrative photo', $jerk);
    }

    public function test_stock_photography_never_stands_in_for_an_event_and_the_fallback_says_what_it_is(): void
    {
        $event = Event::allTenants()->whereKey(self::EVENT)->firstOrFail();

        foreach (['/images/island_music_reggae_concert.jpg', '/images/island_festival_sunset.jpg', '/images/event_family_community.jpg'] as $stock) {
            $event->flyer_path = $stock;
            $this->assertFalse($event->hasFlyer(), "{$stock} is not this event's flyer");
            $this->assertStringContainsString('/flyer.svg', $event->display_image_url);
            $cover = $event->coverImage();
            $this->assertTrue($cover->generated);
            $this->assertStringContainsString('no flyer uploaded', $cover->alt);
        }

        // The generated artwork names the event's category and says it is artwork, not a flyer or a photo.
        $event->flyer_path = null;
        $event->category = 'Family Picnic';
        $svg = GeneratedFlyer::svg($event);
        $this->assertStringContainsString('Family Picnic', $svg);
        $this->assertStringContainsString('Event artwork · no flyer uploaded yet', $svg);
        $this->assertStringNotContainsString('<image', $svg, 'No photograph is embedded.');
    }

    public function test_the_hosts_uploaded_flyer_is_always_what_is_shown(): void
    {
        $event = Event::allTenants()->whereKey(self::EVENT)->firstOrFail();
        $event->forceFill(['flyer_path' => 'tenants/org-kingston-youth/flyers/own.jpg', 'flyer_moderation_status' => 'approved']);

        $this->assertTrue($event->hasFlyer());
        $this->assertStringContainsString('/api/v1/media/events/' . self::EVENT . '/flyer', $event->display_image_url);
        $this->assertFalse($event->coverImage()->generated);

        // A host's own linked artwork is theirs too.
        $event->flyer_path = 'https://cdn.example.org/our-flyer.png';
        $this->assertSame('https://cdn.example.org/our-flyer.png', $event->display_image_url);
    }

    public function test_every_catalogued_sample_exists_and_names_the_dishes_it_may_stand_for(): void
    {
        foreach ((array) config('visual_assets.sample_photos') as $cuisine => $photos) {
            foreach ($photos as $photo) {
                $this->assertFileExists(public_path($photo['file']));
                $this->assertNotEmpty($photo['matches'], "{$photo['file']} lists the dishes it shows");
                $this->assertSame($photo['file'], ImageSubjects::sample('/' . $photo['file'])['file']);
            }
        }
        $this->assertNull(ImageSubjects::sample('/images/not-catalogued.jpg'));
    }
}
