<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A host cancels their own event from its edit page; nobody else can, and a cancelled event stays cancelled. */
class EventCancellationTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function host(): User
    {
        return User::where('email', 'host@islandevents.example')->firstOrFail();
    }

    private function hostedEventId(): string
    {
        $organizer = \App\Models\OrganizerUser::where('user_id', $this->host()->id)->where('is_active', true)->value('organizer_id');

        return Event::allTenants()->where('organizer_id', $organizer)->value('id') ?? self::EVENT;
    }

    public function test_a_host_cancels_their_event_with_a_reason(): void
    {
        $id = $this->hostedEventId();
        Event::allTenants()->whereKey($id)->update(['status' => 'published', 'is_ordering_open' => true]);

        $this->actingAs($this->host())->get(route('events.edit', $id))->assertOk()->assertSee('Cancel this event');

        $this->actingAs($this->host())->post(route('events.cancel', $id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('published', Event::allTenants()->find($id)->status);

        $this->actingAs($this->host())->post(route('events.cancel', $id), ['reason' => 'Venue flooded'])
            ->assertRedirect(route('host.dashboard'));
        $event = Event::allTenants()->find($id);
        $this->assertSame('cancelled', $event->status);
        $this->assertFalse((bool) $event->is_ordering_open);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'event_cancelled', 'target_id' => $id]);

        // It cannot be reopened, and the edit page no longer offers to cancel it.
        $this->actingAs($this->host())->get(route('events.edit', $id))->assertOk()->assertDontSee('Cancel this event');
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\EventManagementService::class)->setStatus($this->host(), $id, 'published');
    }

    public function test_nobody_else_can_cancel_it(): void
    {
        $id = $this->hostedEventId();
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();

        $this->actingAs($attendee)->post(route('events.cancel', $id), ['reason' => 'Not mine to cancel'])->assertNotFound();
        $this->assertNotSame('cancelled', Event::allTenants()->find($id)->status);
    }
}
