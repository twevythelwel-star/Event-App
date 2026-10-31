<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\HostApplication;
use App\Models\Order;
use App\Models\OrganizerUser;
use App\Models\TicketDispute;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\DemoSupportRequestsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportRequestsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class, DemoSupportRequestsSeeder::class]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function pendingApplication(): HostApplication
    {
        return HostApplication::where('status', 'pending')->firstOrFail();
    }

    private function openDispute(): TicketDispute
    {
        return TicketDispute::where('status', 'open')->firstOrFail();
    }

    // ---- Host applications ------------------------------------------------------------------

    public function test_attendee_submits_one_pending_host_application_at_a_time(): void
    {
        $attendee = $this->user('attendee');
        $payload = ['organization_name' => 'Hope Road Fish Fry', 'country' => 'JM', 'contact_phone' => '+1 876 555 0199'];

        $this->actingAs($attendee)->postJson('/api/v1/host-applications', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.applicantEmail');
        $this->actingAs($attendee)->postJson('/api/v1/host-applications', $payload)->assertStatus(422);
        $this->actingAs($attendee)->getJson('/api/v1/host-applications')->assertOk()->assertJsonCount(1, 'data');

        // Existing hosts do not reapply
        $this->actingAs($this->user('host'))->postJson('/api/v1/host-applications', $payload)->assertStatus(422);
    }

    public function test_only_administrators_review_host_applications(): void
    {
        $id = $this->pendingApplication()->id;

        $this->actingAs($this->user('attendee'))->getJson('/api/v1/admin/host-applications')->assertForbidden();
        $this->actingAs($this->user('host'))->postJson("/api/v1/admin/host-applications/{$id}/approve")->assertForbidden();

        $this->actingAs($this->user('admin'))->getJson('/api/v1/admin/host-applications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.applicantEmail', 'applicant@islandevents.example');
    }

    public function test_approval_grants_host_role_and_a_new_organizer(): void
    {
        $application = $this->pendingApplication();
        $applicant = $this->user('applicant');

        $this->actingAs($this->user('admin'))
            ->postJson("/api/v1/admin/host-applications/{$application->id}/approve", ['notes' => 'Verified co-op letter'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewedBy', 'Demo Delegated Admin');

        $this->assertTrue(UserRole::where('user_id', $applicant->id)->where('role', 'event_host')->where('is_active', true)->exists());
        $membership = OrganizerUser::where('user_id', $applicant->id)->firstOrFail();
        $this->assertSame('event_owner', $membership->role->value);
        $this->assertSame('Miami Community Food & Arts Collective', $membership->organizer->name);
        $this->assertSame('US', $membership->organizer->business_location);
        $this->assertSame('USD', $membership->organizer->default_currency);

        // The new host's session now offers the host dashboard for their organizer
        $this->actingAs($applicant->fresh())->getJson('/api/v1/session')
            ->assertJsonPath('user.roles', ['attendee', 'event_host'])
            ->assertJsonPath('user.organizerIds', [$membership->organizer_id]);

        $this->actingAs($this->user('admin'))->postJson("/api/v1/admin/host-applications/{$application->id}/approve")
            ->assertStatus(422);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'host_application_approved']);
    }

    public function test_rejection_needs_a_reason_and_admins_cannot_approve_themselves(): void
    {
        $admin = $this->user('admin');
        $id = $this->pendingApplication()->id;

        $this->actingAs($admin)->postJson("/api/v1/admin/host-applications/{$id}/reject", [])->assertStatus(422);
        $this->actingAs($admin)->postJson("/api/v1/admin/host-applications/{$id}/reject", ['reason' => 'Could not verify the organization'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $own = HostApplication::create([
            'user_id' => $admin->id,
            'organization_name' => 'Admin Side Hustle',
            'country' => 'JM',
            'contact_phone' => '8765550100',
            'status' => 'pending',
        ]);
        $this->actingAs($admin)->postJson("/api/v1/admin/host-applications/{$own->id}/approve")->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/admin/host-applications/999999/approve')->assertNotFound();
    }

    // ---- Disputes ---------------------------------------------------------------------------

    public function test_attendee_files_disputes_only_on_their_own_orders(): void
    {
        $attendee = $this->user('attendee');
        $paid = Order::withoutGlobalScopes()->where('user_id', $attendee->id)->where('status', 'paid')->firstOrFail();

        $this->actingAs($attendee)->postJson('/api/v1/disputes', [
            'order_id' => $paid->id,
            'dispute_type' => 'customer_complaint',
            'reason' => 'One of the meals was missing at pickup.',
            'amount' => 5,
        ])->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.orderNumber', $paid->order_number);

        // Second open dispute on the same order, an excessive amount, and someone else's order are refused
        $this->actingAs($attendee)->postJson('/api/v1/disputes', ['order_id' => $paid->id, 'reason' => 'Still waiting on a reply.'])
            ->assertStatus(422);
        $other = Order::withoutGlobalScopes()->where('user_id', $attendee->id)->where('method', 'cash')->firstOrFail();
        $this->actingAs($attendee)->postJson('/api/v1/disputes', ['order_id' => $other->id, 'reason' => 'Charged twice for the same meal.', 'amount' => 999999])
            ->assertStatus(422);
        $this->actingAs($this->user('host'))->postJson('/api/v1/disputes', ['order_id' => $other->id, 'reason' => 'Not my order at all here.'])
            ->assertNotFound();

        $this->actingAs($attendee)->getJson('/api/v1/disputes')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_works_a_dispute_to_resolution(): void
    {
        $admin = $this->user('admin');
        $dispute = $this->openDispute();

        $this->actingAs($this->user('host'))->getJson('/api/v1/admin/disputes')->assertForbidden();

        $this->actingAs($admin)->getJson('/api/v1/admin/disputes')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->postJson("/api/v1/admin/disputes/{$dispute->id}/status", ['status' => 'under_review'])
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.assignedAdminName', 'Demo Delegated Admin');
        $this->actingAs($admin)->postJson("/api/v1/admin/disputes/{$dispute->id}/status", ['status' => 'resolved'])
            ->assertStatus(422);
        $this->actingAs($admin)->postJson("/api/v1/admin/disputes/{$dispute->id}/status", ['status' => 'resolved', 'notes' => 'Transfer confirmed with NCB; host notified to verify.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
        $this->actingAs($admin)->postJson("/api/v1/admin/disputes/{$dispute->id}/status", ['status' => 'rejected', 'notes' => 'Changed mind'])
            ->assertStatus(422);

        $this->assertDatabaseHas('security_audit_logs', ['action' => 'dispute_resolved', 'target_id' => $dispute->id]);
    }

    public function test_scoped_delegated_admins_only_see_their_assigned_organizers(): void
    {
        $admin = $this->user('admin');
        $dispute = $this->openDispute();

        AdminPermission::updateOrCreate(['user_id' => $admin->id], ['scope' => 'assigned_events', 'can_support_orders' => true, 'assigned_organizer_ids' => ['org-hellshire-fishers']]);
        $this->actingAs($admin->fresh())->getJson('/api/v1/admin/disputes')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin->fresh())->postJson("/api/v1/admin/disputes/{$dispute->id}/status", ['status' => 'under_review'])->assertNotFound();

        AdminPermission::where('user_id', $admin->id)->update(['assigned_organizer_ids' => json_encode(['org-kingston-youth'])]);
        $this->actingAs($admin->fresh())->getJson('/api/v1/admin/disputes')->assertOk()->assertJsonCount(1, 'data');

        AdminPermission::where('user_id', $admin->id)->update(['can_support_orders' => false]);
        $this->actingAs($admin->fresh())->getJson('/api/v1/admin/disputes')->assertOk()->assertJsonCount(0, 'data');
    }
}
