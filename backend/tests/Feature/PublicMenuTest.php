<?php

namespace Tests\Feature;

use App\Models\Event;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public menu feed: published events only, customer-facing fields only. */
class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FishFryEventsSeeder::class);
    }

    public function test_a_published_events_menu_shows_only_what_customers_see(): void
    {
        $slug = Event::allTenants()->findOrFail(self::EVENT)->slug;
        $menu = $this->getJson("/events/{$slug}/menu")->assertOk()->json('menu');

        $this->assertNotEmpty($menu);
        foreach (['image_path', 'image_variants', 'preparation_notes', 'vendor_assignment', 'image_moderation_status', 'organizer_id', 'deleted_at'] as $internal) {
            $this->assertArrayNotHasKey($internal, $menu[0], "{$internal} is internal");
        }
        $this->assertArrayHasKey('name', $menu[0]);
    }

    public function test_a_draft_events_menu_is_not_public(): void
    {
        $event = Event::allTenants()->findOrFail(self::EVENT);
        $event->forceFill(['status' => 'draft'])->save();

        $this->getJson("/events/{$event->slug}/menu")->assertNotFound();
    }
}
