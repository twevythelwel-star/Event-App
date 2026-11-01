<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role boundaries found by the profile audit: who may open the host dashboard, an event's staff page and its
 * terminal, and who may revoke a staff assignment.
 */
class ProfileAccessAuditTest extends TestCase
{
    use RefreshDatabase;

    private const OWN_EVENT = 'evt-kingston-harbour-fry';      // organized by the demo host's organizer
    private const OTHER_EVENT = 'evt-treasure-beach-calabash'; // another organizer

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function host(): User
    {
        return $this->user('host@islandevents.example');
    }

    private function otherHost(): User
    {
        $other = Event::allTenants()->findOrFail(self::OTHER_EVENT);
        $user = User::create(['name' => 'Other Host', 'email' => 'other-host@audit.test', 'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now()]);
        UserRole::create(['user_id' => $user->id, 'role' => 'event_host', 'is_active' => true, 'assigned_at' => now()]);
        OrganizerUser::create(['organizer_id' => $other->organizer_id, 'user_id' => $user->id, 'role' => 'event_owner', 'is_active' => true]);

        return $user;
    }

    private function worker(string $role, array $overrides = [], string $eventId = self::OWN_EVENT): array
    {
        $event = Event::allTenants()->findOrFail($eventId);
        $user = User::create(['name' => "Staff {$role}", 'email' => uniqid($role) . '@audit.test', 'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now()]);
        $assignment = EventWorkerAssignment::create($overrides + [
            'organizer_id' => $event->organizer_id, 'event_id' => $event->id, 'user_id' => $user->id,
            'worker_role' => $role, 'station_assignment' => 'all', 'status' => 'active', 'is_active' => true,
        ]);

        return [$user, $assignment];
    }

    public function test_people_without_a_host_role_are_sent_back_from_the_host_dashboard(): void
    {
        foreach (['attendee@islandevents.example', 'admin@islandevents.example'] as $email) {
            $this->actingAs($this->user($email))->get('/host/dashboard')
                ->assertRedirect(route('attendee.dashboard'))
                ->assertSessionHas('error');
        }
        [$clerk] = $this->worker('payment_clerk');
        $this->actingAs($clerk)->get('/host/dashboard')->assertRedirect(route('attendee.dashboard'));
    }

    public function test_a_host_only_manages_staff_on_their_own_organizers_events(): void
    {
        $this->actingAs($this->host())->get('/host/events/' . self::OWN_EVENT . '/staff')->assertOk();

        $this->actingAs($this->host())->get('/host/events/' . self::OTHER_EVENT . '/staff')->assertForbidden();
        $this->actingAs($this->otherHost())->get('/host/events/' . self::OWN_EVENT . '/staff')->assertForbidden();
    }

    public function test_a_host_cannot_revoke_staff_on_another_organizers_event(): void
    {
        [, $assignment] = $this->worker('ticket_checker');

        $this->actingAs($this->otherHost())
            ->post('/host/events/' . self::OWN_EVENT . '/staff/' . $assignment->id . '/revoke')
            ->assertForbidden();
        $this->assertTrue($assignment->fresh()->isCurrentlyActive());

        $this->actingAs($this->host())
            ->from('/host/dashboard')
            ->post('/host/events/' . self::OWN_EVENT . '/staff/' . $assignment->id . '/revoke', ['reason' => 'Shift ended'])
            ->assertRedirect('/host/dashboard')
            ->assertSessionHas('status');
        $this->assertTrue($assignment->fresh()->isRevoked());
    }

    public function test_staff_reach_the_terminal_only_while_their_assignment_is_current(): void
    {
        $terminal = '/worker/events/' . self::OWN_EVENT . '/terminal';

        [$active] = $this->worker('ticket_checker', ['starts_at' => now()->subHour(), 'expires_at' => now()->addHours(4)]);
        $this->actingAs($active)->get($terminal)->assertOk();

        [$scheduled] = $this->worker('ticket_checker', ['starts_at' => now()->addDay(), 'expires_at' => now()->addDays(2)]);
        $this->actingAs($scheduled)->get($terminal)->assertForbidden();

        [$expired] = $this->worker('ticket_checker', ['starts_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);
        $this->actingAs($expired)->get($terminal)->assertForbidden();

        [$revoked, $assignment] = $this->worker('payment_clerk');
        $this->actingAs($revoked)->get($terminal)->assertOk();
        $assignment->revoke($this->host(), 'Left early');
        $this->actingAs($revoked)->get($terminal)->assertForbidden();

        // An assignment on one event never opens another event's terminal.
        $this->actingAs($active)->get('/worker/events/' . self::OTHER_EVENT . '/terminal')->assertForbidden();
    }

    public function test_an_expired_supervisor_cannot_manage_staff(): void
    {
        [$current] = $this->worker('event_supervisor', ['starts_at' => now()->subHour(), 'expires_at' => now()->addHours(4)]);
        $this->actingAs($current)->get('/host/events/' . self::OWN_EVENT . '/staff')->assertOk();

        [$expired] = $this->worker('event_supervisor', ['starts_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);
        $this->actingAs($expired)->get('/host/events/' . self::OWN_EVENT . '/staff')->assertForbidden();
    }

    public function test_staff_see_a_link_to_each_terminal_they_can_open(): void
    {
        [$checker] = $this->worker('ticket_checker');
        [$later] = $this->worker('ticket_checker', ['starts_at' => now()->addDay(), 'expires_at' => now()->addDays(2)], self::OTHER_EVENT);

        $this->actingAs($checker)->get('/attendee/dashboard')->assertOk()
            ->assertSee('/worker/events/' . self::OWN_EVENT . '/terminal', false);
        $this->actingAs($later)->get('/attendee/dashboard')->assertOk()
            ->assertDontSee('/worker/events/' . self::OTHER_EVENT . '/terminal', false);
    }
}
