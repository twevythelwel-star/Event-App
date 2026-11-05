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
        $groups = ['heroes', 'illustrations', 'empty'];
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
        $this->assertSame(14, $count);
    }

    public function test_categories_map_to_fitting_artwork_and_unknown_ones_fall_back(): void
    {
        $this->assertStringEndsWith('heroes/food-festival.svg', VisualAssets::heroFor('Food & drink')['url']);
        $this->assertStringEndsWith('heroes/concert.svg', VisualAssets::heroFor('Music')['url']);
        $this->assertStringEndsWith('heroes/community.svg', VisualAssets::heroFor('Family Day')['url']);
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
