<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
        ], $attributes));
    }

    private function makeOrganizerWithOwner(string $key): array
    {
        $organizer = Organizer::create(['name' => "Org {$key}", 'slug' => "org-{$key}"]);
        $owner = $this->makeUser("owner-{$key}@example.test");
        OrganizerUser::create(['organizer_id' => $organizer->id, 'user_id' => $owner->id, 'role' => 'event_owner']);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'slug' => "event-{$key}",
            'title' => "Event {$key}",
            'date_str' => 'Sunday',
            'time_str' => '12:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => "Org {$key}",
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);

        return [$organizer, $owner, $event];
    }

    private function menuPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jerk Chicken',
            'category' => 'Mains',
            'pricing_type' => 'fixed',
            'price_usd' => 12,
            'price_jmd' => 1800,
        ], $overrides);
    }

    public function test_events_create_is_not_shadowed_by_event_detail_route(): void
    {
        $this->get('/events/create')->assertRedirect('/login');
    }

    public function test_attendee_cannot_reach_admin_or_system_admin_areas(): void
    {
        $attendee = $this->makeUser('guest@example.test');

        $this->actingAs($attendee)->getJson('/admin/dashboard')->assertForbidden();
        $this->actingAs($attendee)->getJson('/admin/host-applications')->assertForbidden();
        $this->actingAs($attendee)->postJson('/admin/host-applications/1/approve')->assertForbidden();
        $this->actingAs($attendee)->getJson('/system-admin/dashboard')->assertForbidden();
    }

    public function test_delegated_admin_cannot_reach_system_admin_area(): void
    {
        $admin = $this->makeUser('admin@example.test', ['current_context_role' => 'admin']);
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->getJson('/admin/host-applications')->assertOk();
        $this->actingAs($admin)->getJson('/system-admin/dashboard')->assertForbidden();
    }

    public function test_revoked_admin_role_stops_working_even_while_it_is_the_active_context(): void
    {
        $admin = $this->makeUser('revoked@example.test', ['current_context_role' => 'admin']);
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin', 'is_active' => false]);

        $this->actingAs($admin)->getJson('/admin/host-applications')->assertForbidden();
    }

    public function test_non_admin_approval_attempt_returns_forbidden_not_server_error(): void
    {
        $host = $this->makeUser('host@example.test');

        $this->actingAs($host)->postJson('/admin/host-applications/1/approve')->assertForbidden();
    }

    public function test_event_owner_can_manage_own_menu(): void
    {
        [, $owner, $event] = $this->makeOrganizerWithOwner('a');

        $this->actingAs($owner)->postJson("/events/{$event->slug}/menu", $this->menuPayload())->assertCreated();
        $this->getJson("/events/{$event->slug}/menu")->assertOk()->assertJsonCount(1, 'menu');
    }

    public function test_owner_cannot_create_menu_items_on_another_organizers_event(): void
    {
        [, , $eventA] = $this->makeOrganizerWithOwner('a');
        [$organizerB, $ownerB] = $this->makeOrganizerWithOwner('b');

        // Supplying their own organizer_id must not grant access to organizer A's event
        $this->actingAs($ownerB)
            ->postJson("/events/{$eventA->slug}/menu", $this->menuPayload(['organizer_id' => $organizerB->id]))
            ->assertForbidden();

        $this->assertSame(0, MenuItem::withoutGlobalScopes()->count());
    }

    public function test_owner_cannot_update_or_delete_another_organizers_menu_items(): void
    {
        [$organizerA, , $eventA] = $this->makeOrganizerWithOwner('a');
        [, $ownerB, $eventB] = $this->makeOrganizerWithOwner('b');

        $item = MenuItem::create(array_merge($this->menuPayload(), [
            'organizer_id' => $organizerA->id,
            'event_id' => $eventA->id,
        ]));

        $this->actingAs($ownerB)->deleteJson("/events/{$eventA->slug}/menu/{$item->id}")->assertForbidden();
        $this->actingAs($ownerB)->putJson("/events/{$eventA->slug}/menu/{$item->id}", $this->menuPayload(['name' => 'Hacked']))
            ->assertForbidden();

        // Routing the item through the attacker's own event must not reach it either
        $this->actingAs($ownerB)->deleteJson("/events/{$eventB->slug}/menu/{$item->id}")->assertNotFound();
        $this->actingAs($ownerB)->putJson("/events/{$eventB->slug}/menu/{$item->id}", $this->menuPayload(['name' => 'Hacked']))
            ->assertNotFound();

        $this->assertSame('Jerk Chicken', $item->fresh()->name);
    }
}
