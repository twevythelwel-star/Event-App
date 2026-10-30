<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Event;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security behaviour of the administration console (/admin/console): who can reach each area,
 * organizer scope, role-change limits, payment decisions, personal data exposure, exports and
 * audit-log integrity.
 */
class AdminConsoleSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const KINGSTON = 'org-kingston-youth';
    private const HELLSHIRE = 'org-hellshire-fishers';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
    }

    private function demo(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function systemAdmin(string $email = 'root@islandevents.example'): User
    {
        $user = User::create(['name' => 'Root Admin', 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);

        return $user;
    }

    /** A delegated administrator with exactly the given capabilities and organizers. */
    private function delegatedAdmin(array $flags = [], array $organizers = [], string $scope = 'assigned_events'): User
    {
        $user = User::create(['name' => 'Scoped Admin', 'email' => uniqid('admin') . '@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin', 'is_active' => true, 'assigned_at' => now()]);
        AdminPermission::create(array_merge([
            'user_id' => $user->id,
            'scope' => $scope,
            'can_manage_events' => false,
            'can_moderate_content' => false,
            'can_support_orders' => false,
            'can_view_payments' => false,
            'can_view_reports' => false,
            'can_access_audit_logs' => false,
            'assigned_organizer_ids' => $organizers,
        ], $flags));

        return $user->fresh();
    }

    private function eventOf(string $organizerId): Event
    {
        return Event::allTenants()->where('organizer_id', $organizerId)->firstOrFail();
    }

    private function orderOf(string $organizerId, ?string $status = null): Order
    {
        return Order::allTenants()->where('organizer_id', $organizerId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->firstOrFail();
    }

    private function hellshireOrder(): Order
    {
        $event = $this->eventOf(self::HELLSHIRE);

        return Order::allTenants()->create([
            'organizer_id' => self::HELLSHIRE,
            'event_id' => $event->id,
            'ticket_code' => 'SFF-HELL-01',
            'order_number' => 'ORD-HELL-0001',
            'customer_name' => 'Marcia Griffiths',
            'customer_phone' => '+1 876 555 0300',
            'method' => 'bank',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'currency' => 'JMD',
            'status' => 'payment_submitted',
            'secure_ticket_token' => 'tok_hell_0001',
        ]);
    }

    // ---- Unauthorized access ---------------------------------------------------------------

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/admin/console')->assertRedirect(route('login'));
        $this->get('/admin/console/users')->assertRedirect(route('login'));
    }

    public function test_attendees_and_hosts_cannot_open_any_admin_area(): void
    {
        foreach (['attendee', 'host'] as $who) {
            foreach (['/admin/console', '/admin/console/users', '/admin/console/orders', '/admin/console/audit-logs', '/admin/console/settings'] as $url) {
                $this->actingAs($this->demo($who))->get($url)->assertForbidden();
            }
        }
    }

    public function test_suspended_administrators_are_blocked(): void
    {
        $root = $this->systemAdmin();
        $root->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($root)->get('/admin/console')->assertForbidden();
        $this->actingAs($root)->get('/admin/console/settings')->assertForbidden();
    }

    public function test_admin_without_permissions_only_sees_the_overview(): void
    {
        $admin = $this->delegatedAdmin();

        $this->actingAs($admin)->get('/admin/console')->assertOk()
            ->assertSee('No administration areas are switched on')
            ->assertDontSee('href="' . route('admin.console.users.index') . '"', false)
            ->assertDontSee('href="' . route('admin.console.settings.edit') . '"', false);

        foreach (['/admin/console/users', '/admin/console/events', '/admin/console/orders', '/admin/console/reports', '/admin/console/audit-logs', '/admin/console/settings'] as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden();
        }
    }

    public function test_navigation_matches_permissions(): void
    {
        $admin = $this->delegatedAdmin(['can_view_payments' => true], [self::KINGSTON]);

        $this->actingAs($admin)->get('/admin/console')->assertOk()
            ->assertSee('href="' . route('admin.console.orders.index') . '"', false)
            ->assertDontSee('href="' . route('admin.console.audit.index') . '"', false)
            ->assertDontSee('href="' . route('admin.console.settings.edit') . '"', false);
    }

    // ---- Scope restrictions and direct URL access ------------------------------------------

    public function test_scoped_admin_only_lists_their_organizers_events(): void
    {
        $admin = $this->delegatedAdmin(['can_manage_events' => true], [self::KINGSTON]);

        $this->actingAs($admin)->get('/admin/console/events')->assertOk()
            ->assertSee($this->eventOf(self::KINGSTON)->title)
            ->assertDontSee($this->eventOf(self::HELLSHIRE)->title);
    }

    public function test_direct_urls_outside_scope_are_refused(): void
    {
        $admin = $this->delegatedAdmin(['can_manage_events' => true, 'can_moderate_content' => true, 'can_view_payments' => true], [self::KINGSTON]);
        $otherEvent = $this->eventOf(self::HELLSHIRE);
        $originalStatus = $otherEvent->status;
        $otherOrder = $this->hellshireOrder();

        $this->actingAs($admin)->get("/admin/console/events/{$otherEvent->id}")->assertForbidden();
        $this->actingAs($admin)->post("/admin/console/events/{$otherEvent->id}/moderate", ['action' => 'unpublish', 'reason' => 'Testing scope', 'confirm' => 1])->assertForbidden();
        $this->actingAs($admin)->get("/admin/console/orders/{$otherOrder->id}")->assertForbidden();
        $this->actingAs($admin)->get('/admin/console/orders')->assertOk()->assertDontSee('ORD-HELL-0001');
        $this->actingAs($admin)->get('/admin/console/reports?event=' . $otherEvent->id)->assertForbidden();

        $this->assertSame($originalStatus, $otherEvent->fresh()->status);
    }

    public function test_unknown_records_return_not_found(): void
    {
        $root = $this->systemAdmin();
        $this->actingAs($root)->get('/admin/console/events/not-a-real-id')->assertNotFound();
        $this->actingAs($root)->get('/admin/console/orders/not-a-real-id')->assertNotFound();
        $this->actingAs($root)->get('/admin/console/users/999999')->assertNotFound();
    }

    public function test_moderation_inside_scope_is_applied_and_audited(): void
    {
        $admin = $this->delegatedAdmin(['can_manage_events' => true, 'can_moderate_content' => true], [self::KINGSTON]);
        $event = $this->eventOf(self::KINGSTON);
        $event->forceFill(['status' => 'published'])->save();

        $this->actingAs($admin)->post("/admin/console/events/{$event->id}/moderate", ['action' => 'unpublish', 'reason' => 'Flyer reported as misleading', 'confirm' => 1])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('unpublished', $event->fresh()->status);
        $this->assertFalse($event->fresh()->is_ordering_open);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'event_moderated', 'target_id' => $event->id, 'organizer_id' => self::KINGSTON]);
    }

    public function test_viewing_events_does_not_allow_moderating_them(): void
    {
        $admin = $this->delegatedAdmin(['can_manage_events' => true], [self::KINGSTON]);
        $event = $this->eventOf(self::KINGSTON);

        $this->actingAs($admin)->post("/admin/console/events/{$event->id}/moderate", ['action' => 'unpublish', 'reason' => 'Should not work', 'confirm' => 1])
            ->assertForbidden();
    }

    // ---- System administrator privileges ---------------------------------------------------

    public function test_system_admin_reaches_every_area(): void
    {
        $root = $this->systemAdmin();
        foreach (['/admin/console', '/admin/console/users', '/admin/console/roles', '/admin/console/events', '/admin/console/orders', '/admin/console/reports', '/admin/console/audit-logs', '/admin/console/settings'] as $url) {
            $this->actingAs($root)->get($url)->assertOk();
        }
    }

    // ---- Role assignment restrictions ------------------------------------------------------

    public function test_delegated_admin_cannot_change_roles_or_permissions(): void
    {
        $admin = $this->delegatedAdmin(['can_support_orders' => true], [], 'all');
        $attendee = $this->demo('attendee');

        $this->actingAs($admin)->post("/admin/console/users/{$attendee->id}/roles", ['role' => 'admin', 'confirm' => 1])->assertForbidden();
        $this->actingAs($admin)->post("/admin/console/users/{$attendee->id}/roles", ['role' => 'system_admin', 'confirm' => 1])->assertForbidden();
        $this->actingAs($admin)->post("/admin/console/roles/administrators/{$admin->id}", ['scope' => 'all', 'can_access_audit_logs' => 1, 'confirm' => 1])->assertForbidden();

        $this->assertFalse($attendee->fresh()->hasRole('admin'));
        $this->assertFalse((bool) $admin->fresh()->adminPermissions->can_access_audit_logs);
    }

    public function test_nobody_can_change_their_own_roles_or_permissions(): void
    {
        $root = $this->systemAdmin();
        $this->actingAs($root)->post("/admin/console/users/{$root->id}/roles/system_admin/revoke", ['confirm' => 1])->assertForbidden();

        $admin = $this->delegatedAdmin(['can_support_orders' => true], [], 'all');
        $this->actingAs($admin)->post("/admin/console/users/{$admin->id}/roles", ['role' => 'system_admin', 'confirm' => 1])->assertForbidden();
    }

    public function test_new_administrators_start_with_no_permissions(): void
    {
        $root = $this->systemAdmin();
        $attendee = $this->demo('attendee');

        $this->actingAs($root)->post("/admin/console/users/{$attendee->id}/roles", ['role' => 'admin', 'confirm' => 1])->assertSessionHas('success');

        $permissions = $attendee->fresh()->adminPermissions;
        $this->assertTrue($attendee->fresh()->hasRole('admin'));
        $this->assertSame('assigned_events', $permissions->scope);
        $this->assertFalse($permissions->can_view_payments || $permissions->can_access_audit_logs || $permissions->can_support_orders);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'user_role_granted', 'target_id' => (string) $attendee->id]);
    }

    public function test_role_changes_need_confirmation(): void
    {
        $root = $this->systemAdmin();
        $attendee = $this->demo('attendee');

        $this->actingAs($root)->post("/admin/console/users/{$attendee->id}/roles", ['role' => 'admin'])->assertSessionHasErrors('confirm');
        $this->assertFalse($attendee->fresh()->hasRole('admin'));
    }

    public function test_system_admin_can_remove_another_but_never_themselves(): void
    {
        $root = $this->systemAdmin();
        $other = $this->systemAdmin('second@islandevents.example');

        $this->actingAs($root)->post("/admin/console/users/{$other->id}/roles/system_admin/revoke", ['confirm' => 1])->assertSessionHas('success');
        $this->assertFalse($other->fresh()->hasRole('system_admin'));

        // $root is now the only system admin: removing it needs another active system admin, and self-removal is refused.
        $this->actingAs($other->fresh())->post("/admin/console/users/{$root->id}/roles/system_admin/revoke", ['confirm' => 1])->assertForbidden();
        $this->actingAs($root)->post("/admin/console/users/{$root->id}/roles/system_admin/revoke", ['confirm' => 1])->assertForbidden();
        $this->assertTrue($root->fresh()->hasRole('system_admin'));
    }

    public function test_system_admin_updates_delegated_permissions_with_audit(): void
    {
        $root = $this->systemAdmin();
        $admin = $this->delegatedAdmin();

        $this->actingAs($root)->post("/admin/console/roles/administrators/{$admin->id}", [
            'scope' => 'assigned_events',
            'organizer_ids' => [self::KINGSTON],
            'can_view_payments' => 1,
            'confirm' => 1,
        ])->assertSessionHas('success');

        $permissions = $admin->fresh()->adminPermissions;
        $this->assertTrue($permissions->can_view_payments);
        $this->assertSame([self::KINGSTON], $permissions->assigned_organizer_ids);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'admin_permissions_changed', 'target_id' => (string) $admin->id]);
    }

    public function test_permission_update_rejects_unknown_organizers(): void
    {
        $root = $this->systemAdmin();
        $admin = $this->delegatedAdmin();

        $this->actingAs($root)->post("/admin/console/roles/administrators/{$admin->id}", [
            'scope' => 'assigned_events', 'organizer_ids' => ['org-does-not-exist'], 'confirm' => 1,
        ])->assertSessionHasErrors('organizer_ids.0');
    }

    // ---- Account status --------------------------------------------------------------------

    public function test_only_system_admin_suspends_accounts_with_reason_and_confirmation(): void
    {
        $attendee = $this->demo('attendee');
        $admin = $this->delegatedAdmin(['can_support_orders' => true], [], 'all');

        $this->actingAs($admin)->post("/admin/console/users/{$attendee->id}/status", ['status' => 'suspended', 'reason' => 'Chargeback fraud', 'confirm' => 1])->assertForbidden();

        $root = $this->systemAdmin();
        $this->actingAs($root)->post("/admin/console/users/{$attendee->id}/status", ['status' => 'suspended'])->assertSessionHasErrors(['reason', 'confirm']);
        $this->assertSame('active', $attendee->fresh()->status);

        $this->actingAs($root)->post("/admin/console/users/{$attendee->id}/status", ['status' => 'suspended', 'reason' => 'Chargeback fraud', 'confirm' => 1])->assertSessionHas('success');
        $this->assertSame('suspended', $attendee->fresh()->status);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'user_status_changed', 'target_id' => (string) $attendee->id]);
    }

    public function test_system_admins_cannot_be_suspended_from_the_console(): void
    {
        $root = $this->systemAdmin();
        $other = $this->systemAdmin('second@islandevents.example');

        $this->actingAs($root)->post("/admin/console/users/{$other->id}/status", ['status' => 'suspended', 'reason' => 'Testing protection', 'confirm' => 1])->assertForbidden();
        $this->actingAs($root)->post("/admin/console/users/{$root->id}/status", ['status' => 'suspended', 'reason' => 'Testing protection', 'confirm' => 1])->assertForbidden();
    }

    // ---- Payments --------------------------------------------------------------------------

    public function test_payment_oversight_does_not_grant_payment_decisions(): void
    {
        $admin = $this->delegatedAdmin(['can_view_payments' => true], [self::KINGSTON]);
        $order = $this->orderOf(self::KINGSTON, 'payment_submitted');

        $this->actingAs($admin)->get("/admin/console/orders/{$order->id}")->assertOk()->assertSee('You can see this order but not verify it');
        $this->actingAs($admin)->post("/admin/console/orders/{$order->id}/review", ['status' => 'paid', 'confirm' => 1])->assertForbidden();

        $this->assertSame('payment_submitted', $order->fresh()->status);
    }

    public function test_system_admin_payment_decision_uses_the_validated_path(): void
    {
        $root = $this->systemAdmin();
        $order = $this->orderOf(self::KINGSTON, 'payment_submitted');

        $this->actingAs($root)->post("/admin/console/orders/{$order->id}/review", ['status' => 'refunded', 'confirm' => 1])->assertSessionHasErrors('status');
        $this->actingAs($root)->post("/admin/console/orders/{$order->id}/review", ['status' => 'rejected', 'confirm' => 1])->assertSessionHasErrors('rejection_reason');
        $this->actingAs($root)->post("/admin/console/orders/{$order->id}/review", ['status' => 'paid'])->assertSessionHasErrors('confirm');
        $this->assertSame('payment_submitted', $order->fresh()->status);

        $this->actingAs($root)->post("/admin/console/orders/{$order->id}/review", ['status' => 'paid', 'confirm' => 1])->assertSessionHas('success');
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertEquals((float) $order->total_amount, (float) $order->fresh()->amount_paid);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'order_payment_status_changed', 'target_id' => $order->id, 'organizer_id' => self::KINGSTON]);
    }

    public function test_refunded_orders_cannot_be_reverified(): void
    {
        $root = $this->systemAdmin();
        $order = $this->orderOf(self::KINGSTON);
        $order->forceFill(['status' => 'refunded'])->save();

        $this->actingAs($root)->post("/admin/console/orders/{$order->id}/review", ['status' => 'paid', 'confirm' => 1])->assertSessionHas('error');
        $this->assertSame('refunded', $order->fresh()->status);
    }

    // ---- Sensitive information -------------------------------------------------------------

    public function test_pages_never_render_credentials(): void
    {
        $root = $this->systemAdmin();
        $attendee = $this->demo('attendee');
        $attendee->forceFill(['two_factor_secret' => 'TOTPSECRETVALUE123', 'remember_token' => 'remember-token-value'])->save();

        foreach (['/admin/console/users', "/admin/console/users/{$attendee->id}"] as $url) {
            $this->actingAs($root)->get($url)->assertOk()
                ->assertDontSee($attendee->password, false)
                ->assertDontSee('TOTPSECRETVALUE123')
                ->assertDontSee('remember-token-value');
        }
    }

    public function test_customer_contact_details_are_masked_for_delegated_admins(): void
    {
        $admin = $this->delegatedAdmin(['can_view_payments' => true], [self::KINGSTON]);
        $order = $this->orderOf(self::KINGSTON);
        $before = SecurityAuditLog::where('is_pii_access', true)->count();

        $this->actingAs($admin)->get("/admin/console/orders/{$order->id}?reveal=1")->assertOk()
            ->assertDontSee($order->customer_phone)
            ->assertSee('limited to System Administrators');

        $this->assertSame($before, SecurityAuditLog::where('is_pii_access', true)->count());
    }

    public function test_system_admin_contact_reveal_is_recorded(): void
    {
        $root = $this->systemAdmin();
        $order = $this->orderOf(self::KINGSTON);

        $this->actingAs($root)->get("/admin/console/orders/{$order->id}")->assertOk()->assertDontSee($order->customer_phone);
        $this->actingAs($root)->get("/admin/console/orders/{$order->id}?reveal=1")->assertOk()->assertSee($order->customer_phone);

        $this->assertDatabaseHas('security_audit_logs', ['action' => 'pii_record_viewed', 'target_id' => $order->id, 'is_pii_access' => true]);
    }

    public function test_audit_details_are_redacted_below_system_admin(): void
    {
        $admin = $this->delegatedAdmin(['can_access_audit_logs' => true], [], 'all');
        SecurityAuditLog::create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => null, 'actor_role' => 'system', 'action' => 'pii_record_viewed',
            'target_entity' => 'users', 'target_id' => '1', 'details' => 'Viewed jane.doe@example.com phone +1 (876) 555-0142',
            'is_pii_access' => true, 'created_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/console/audit-logs')->assertOk()
            ->assertDontSee('jane.doe@example.com')
            ->assertDontSee('555-0142')
            ->assertSee('j***@example.com');
    }

    // ---- Audit integrity -------------------------------------------------------------------

    public function test_audit_records_cannot_be_rewritten_or_deleted(): void
    {
        $log = SecurityAuditLog::create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'actor_role' => 'system', 'action' => 'test_action',
            'target_entity' => 'users', 'details' => 'original', 'created_at' => now(),
        ]);

        try {
            $log->update(['details' => 'tampered']);
            $this->fail('Audit record update should be refused.');
        } catch (\LogicException) {
        }

        try {
            $log->delete();
            $this->fail('Audit record delete should be refused.');
        } catch (\LogicException) {
        }

        $this->assertDatabaseHas('security_audit_logs', ['id' => $log->id, 'details' => 'original']);
    }

    public function test_there_is_no_route_to_change_audit_history(): void
    {
        $root = $this->systemAdmin();
        $this->actingAs($root)->delete('/admin/console/audit-logs')->assertStatus(405);
        $this->actingAs($root)->post('/admin/console/audit-logs')->assertStatus(405);
    }

    public function test_scoped_audit_view_hides_other_organizers_activity(): void
    {
        $admin = $this->delegatedAdmin(['can_access_audit_logs' => true], [self::KINGSTON]);
        foreach ([self::KINGSTON => 'kingston_marker_action', self::HELLSHIRE => 'hellshire_marker_action'] as $org => $action) {
            SecurityAuditLog::create([
                'id' => (string) \Illuminate\Support\Str::uuid(), 'actor_role' => 'system', 'action' => $action,
                'target_entity' => 'events', 'details' => $action, 'organizer_id' => $org, 'created_at' => now(),
            ]);
        }

        $this->actingAs($admin)->get('/admin/console/audit-logs')->assertOk()
            ->assertSee('kingston marker action')
            ->assertDontSee('hellshire marker action');
    }

    // ---- Exports ---------------------------------------------------------------------------

    public function test_export_needs_reports_and_payment_permissions(): void
    {
        $reportsOnly = $this->delegatedAdmin(['can_view_reports' => true], [self::KINGSTON]);
        $this->actingAs($reportsOnly)->get('/admin/console/reports')->assertOk();
        $this->actingAs($reportsOnly)->get('/admin/console/reports/export')->assertForbidden();
    }

    public function test_export_is_aggregate_scoped_and_audited(): void
    {
        $admin = $this->delegatedAdmin(['can_view_reports' => true, 'can_view_payments' => true], [self::KINGSTON]);
        $this->hellshireOrder();
        $customer = $this->orderOf(self::KINGSTON)->customer_name;

        $response = $this->actingAs($admin)->get('/admin/console/reports/export?days=90');
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Orders placed', $csv);
        $this->assertStringContainsString($this->eventOf(self::KINGSTON)->title, $csv);
        $this->assertStringNotContainsString($this->eventOf(self::HELLSHIRE)->title, $csv);
        $this->assertStringNotContainsString($customer, $csv);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'report_exported', 'user_id' => $admin->id]);
    }

    // ---- Invalid input ---------------------------------------------------------------------

    public function test_invalid_admin_input_is_rejected(): void
    {
        $root = $this->systemAdmin();
        $event = $this->eventOf(self::KINGSTON);

        $this->actingAs($root)->post("/admin/console/events/{$event->id}/moderate", ['action' => 'delete_everything', 'reason' => 'x', 'confirm' => 1])
            ->assertSessionHasErrors(['action', 'reason']);
        $this->actingAs($root)->get('/admin/console/users?role=superuser')->assertSessionHasErrors('role');
        $this->actingAs($root)->get('/admin/console/orders?from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
        $this->actingAs($root)->post("/admin/console/users/{$this->demo('attendee')->id}/roles", ['role' => 'root', 'confirm' => 1])->assertSessionHasErrors('role');
    }

    public function test_settings_validation_and_risky_change_confirmation(): void
    {
        $root = $this->systemAdmin();
        $this->actingAs($root)->get('/admin/console/settings')->assertOk();

        $valid = [
            'platform_name' => 'EventBite', 'default_timezone' => 'America/Jamaica', 'default_locale' => 'en-JM',
            'supported_currencies' => ['JMD', 'USD'], 'supported_payment_methods' => ['bank', 'cash'],
            'mandatory_2fa_for_admins' => 1, 'admin_session_timeout_minutes' => 30, 'attendee_session_timeout_days' => 30,
            'audit_retention_days' => 365, 'pii_scrub_days' => 90,
        ];

        $this->actingAs($root)->post('/admin/console/settings', array_merge($valid, ['audit_retention_days' => 10]))->assertSessionHasErrors('audit_retention_days');
        $this->actingAs($root)->post('/admin/console/settings', array_merge($valid, ['supported_payment_methods' => ['bitcoin']]))->assertSessionHasErrors('supported_payment_methods.0');

        $this->actingAs($root)->post('/admin/console/settings', $valid)->assertSessionHasNoErrors();

        // Turning off admin 2FA is flagged and not saved without explicit confirmation.
        $this->actingAs($root)->post('/admin/console/settings', array_merge($valid, ['mandatory_2fa_for_admins' => 0]))->assertSessionHasErrors('confirm_risky');
        $this->assertTrue((bool) \App\Models\SystemPlatformSetting::first()->mandatory_2fa_for_admins);

        $this->actingAs($root)->post('/admin/console/settings', array_merge($valid, ['mandatory_2fa_for_admins' => 0, 'confirm_risky' => 1]))->assertSessionHas('success');
        $this->assertFalse((bool) \App\Models\SystemPlatformSetting::first()->mandatory_2fa_for_admins);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'platform_settings_changed']);
    }

    public function test_delegated_admins_cannot_change_platform_settings(): void
    {
        $admin = $this->delegatedAdmin(array_fill_keys(['can_manage_events', 'can_moderate_content', 'can_support_orders', 'can_view_payments', 'can_view_reports', 'can_access_audit_logs'], true), [], 'all');

        $this->actingAs($admin)->get('/admin/console/settings')->assertForbidden();
        $this->actingAs($admin)->post('/admin/console/settings', ['platform_name' => 'Hijacked'])->assertForbidden();
    }

    // ---- Old dashboards and audit scope ----------------------------------------------------

    public function test_old_dashboards_send_people_to_the_console(): void
    {
        $root = $this->systemAdmin();
        $admin = $this->delegatedAdmin(['can_support_orders' => true], [], 'all');

        // The old system dashboard showed placeholder health numbers; browsers now land on the console instead.
        $this->actingAs($root)->get('/system-admin/dashboard')->assertRedirect(route('admin.console.overview'));
        $this->actingAs($admin)->get('/admin/dashboard')->assertRedirect(route('admin.console.overview'));

        // API clients keep their JSON responses.
        $this->actingAs($root)->getJson('/system-admin/dashboard')->assertOk()->assertJsonStructure(['metrics']);
    }

    public function test_audit_log_shows_the_event_and_organizer_scope(): void
    {
        $root = $this->systemAdmin();
        $event = $this->eventOf(self::KINGSTON);
        SecurityAuditLog::create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'actor_role' => 'system', 'action' => 'scope_marker_action',
            'target_entity' => 'events', 'target_id' => $event->id, 'details' => 'scope marker',
            'organizer_id' => self::KINGSTON, 'event_id' => $event->id, 'created_at' => now(),
        ]);
        SecurityAuditLog::create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'actor_role' => 'system', 'action' => 'platform_marker_action',
            'target_entity' => 'system_platform_settings', 'details' => 'platform marker', 'created_at' => now(),
        ]);

        $this->actingAs($root)->get('/admin/console/audit-logs?action=scope_marker_action')->assertOk()
            ->assertSee('Scope')
            ->assertSee($event->title)
            ->assertSee(\App\Models\Organizer::find(self::KINGSTON)->name);
        $this->actingAs($root)->get('/admin/console/audit-logs?action=platform_marker_action')->assertOk()->assertSee('Platform');
    }
}
