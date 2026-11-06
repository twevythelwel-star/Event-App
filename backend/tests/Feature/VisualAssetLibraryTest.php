<?php

namespace Tests\Feature;

use App\Support\VisualAssets;
use Tests\TestCase;

/**
 * The visual asset library: every manifest entry exists, is self-contained vector artwork, resolves through the
 * helper, and the bundled photos are catalogued as samples.
 */
class VisualAssetLibraryTest extends TestCase
{
    public function test_every_library_asset_exists_and_is_self_contained_svg(): void
    {
        $groups = ['heroes', 'illustrations', 'empty', 'cuisines', 'backgrounds'];
        $count = 0;
        foreach ($groups as $group) {
            foreach ((array) config("visual_assets.{$group}") as $name => $asset) {
                $path = public_path($asset['file']);
                $this->assertFileExists($path, "{$group}.{$name}");
                $svg = file_get_contents($path);
                $xml = simplexml_load_string($svg);
                $this->assertNotFalse($xml, "{$group}.{$name} is valid SVG");
                $this->assertSame('img', (string) $xml['role'], "{$group}.{$name} declares role=img");
                $this->assertNotSame('', trim((string) $xml['aria-label']), "{$group}.{$name} describes itself");
                $this->assertStringNotContainsString('<image', $svg, "{$group}.{$name} embeds no raster image");
                $this->assertDoesNotMatchRegularExpression('/(href|src)="(https?:|data:)/', $svg, "{$group}.{$name} loads nothing external");
                $this->assertLessThan(20 * 1024, strlen($svg), "{$group}.{$name} stays small");
                $this->assertSame('original', $asset['source']);
                $count++;
            }
        }
        // 12 event heroes, 13 illustrations, 4 empty states, 8 cuisines, 2 backgrounds.
        $this->assertSame(39, $count);
    }

    public function test_every_event_subject_and_illustration_subject_in_the_brief_has_artwork(): void
    {
        $subjects = [
            'Fish Fry' => 'fish-fry', 'Caribbean Festival' => 'festival', 'Reggae & Dancehall' => 'sound-system',
            'Cultural Celebration' => 'cultural-celebration', 'Outdoor Concert' => 'concert', 'Food Festival' => 'food-festival',
            'Community Gathering' => 'community', 'Nightlife' => 'party', 'Family Fun' => 'family-day',
            'Live Performance' => 'live-performance', 'Private Celebration' => 'private-celebration', 'Food and Music' => 'food-and-music',
        ];
        foreach ($subjects as $category => $hero) {
            $this->assertStringEndsWith("heroes/{$hero}.svg", VisualAssets::heroFor($category)['url'], $category);
        }

        foreach (['discover', 'buy-tickets', 'ticket-verification', 'qr-scan', 'food-customization', 'secure-payment', 'order-fulfillment',
            'event-management', 'event-analytics', 'staff-management', 'notifications', 'success', 'refund-processing'] as $subject) {
            $this->assertNotNull(VisualAssets::illustration($subject), $subject);
        }
        $this->assertStringEndsWith('illustrations/ticket.svg', VisualAssets::illustration('buy-tickets')['url']);
    }

    public function test_cuisine_artwork_covers_many_cuisines_and_is_only_mapped_where_it_fits(): void
    {
        foreach (['caribbean', 'african', 'latin-american', 'asian', 'american', 'mediterranean', 'plant-based', 'desserts-drinks'] as $cuisine) {
            $this->assertNotNull(VisualAssets::cuisine($cuisine), $cuisine);
        }

        // By tradition key or by its label, as the homepage passes it.
        $this->assertStringEndsWith('cuisines/latin-american.svg', VisualAssets::cuisineForTradition('latin_american')['url']);
        $this->assertStringEndsWith('cuisines/latin-american.svg', VisualAssets::cuisineForTradition('Latin American')['url']);
        $this->assertStringEndsWith('cuisines/caribbean.svg', VisualAssets::cuisineForTradition('Caribbean')['url']);

        // Traditions the artwork would misrepresent get none, rather than someone else's food.
        $this->assertNull(VisualAssets::cuisineForTradition('south_asian'));
        $this->assertNull(VisualAssets::cuisineForTradition('Indigenous & Native American'));
        $this->assertNull(VisualAssets::cuisineForTradition('Brazilian & Portuguese'), 'Tacos are not Brazilian food.');
        $this->assertNull(VisualAssets::cuisineForTradition('unknown'));
        $this->assertNull(VisualAssets::cuisineForTradition(null));

        // Every mapping points at a real tradition and a real cuisine.
        foreach ((array) config('visual_assets.tradition_cuisines') as $tradition => $cuisine) {
            $this->assertNotNull(config("traditions.{$tradition}"), $tradition);
            $this->assertNotNull(VisualAssets::cuisine($cuisine), $cuisine);
        }
    }

    public function test_a_menu_item_without_a_photo_never_borrows_cuisine_artwork(): void
    {
        $item = new \App\Models\MenuItem(['name' => 'Garden salad', 'price_usd' => 900, 'category' => 'Vegetarian']);
        $html = view('components.food-card', ['item' => $item, 'currency' => 'USD', 'layout' => 'card',
            'slot' => new \Illuminate\Support\HtmlString(''), 'attributes' => new \Illuminate\View\ComponentAttributeBag()])->render();

        $this->assertStringContainsString('No photo yet', $html);
        $this->assertStringNotContainsString('images/library/cuisines', $html);
    }

    public function test_categories_map_to_fitting_artwork_and_unknown_ones_fall_back(): void
    {
        $this->assertStringEndsWith('heroes/food-festival.svg', VisualAssets::heroFor('Food & drink')['url']);
        $this->assertStringEndsWith('heroes/concert.svg', VisualAssets::heroFor('Music')['url']);
        $this->assertStringEndsWith('heroes/family-day.svg', VisualAssets::heroFor('Family Day')['url']);
        $this->assertStringEndsWith('heroes/festival.svg', VisualAssets::heroFor('Something new')['url']);
        $this->assertStringEndsWith('heroes/festival.svg', VisualAssets::heroFor(null)['url']);
        $this->assertNotSame('', VisualAssets::heroFor('Music')['alt']);

        $this->assertStringEndsWith('illustrations/payment.svg', VisualAssets::illustration('payment')['url']);
        $this->assertNull(VisualAssets::illustration('missing'));
        $this->assertNull(VisualAssets::emptyState(null));
    }

    public function test_empty_states_show_their_artwork_as_decoration_beside_the_text(): void
    {
        $html = view('components.empty-state', ['illustration' => 'orders', 'title' => 'No orders yet', 'description' => 'They appear here.',
            'slot' => new \Illuminate\Support\HtmlString(''), 'attributes' => new \Illuminate\View\ComponentAttributeBag()])->render();

        $this->assertStringContainsString('images/library/empty/no-orders.svg', $html);
        $this->assertStringContainsString('alt=""', $html, 'The title already says what is empty.');
        $this->assertStringContainsString('No orders yet', $html);

        $plain = view('components.empty-state', ['title' => 'Nothing', 'slot' => new \Illuminate\Support\HtmlString(''),
            'attributes' => new \Illuminate\View\ComponentAttributeBag()])->render();
        $this->assertStringNotContainsString('images/library', $plain, 'Without an illustration the icon tile stays.');
    }

    public function test_bundled_sample_photos_are_catalogued_and_exist(): void
    {
        $photos = collect((array) config('visual_assets.sample_photos'))->flatten(1);
        $this->assertNotEmpty($photos);
        foreach ($photos as $photo) {
            $this->assertFileExists(public_path($photo['file']));
            $this->assertStringStartsWith('images/', $photo['file'], 'Bundled under /images, so photoIsIllustrative() labels them.');
        }
    }
}
