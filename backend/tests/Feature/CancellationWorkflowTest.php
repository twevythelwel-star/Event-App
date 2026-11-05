<?php

namespace Tests\Feature;

use App\Models\CancellationRequest;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CancellationService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CancellationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
    }

    private function user(string $name): User
    {
        return User::where('email', "{$name}@islandevents.example")->firstOrFail();
    }

    public function test_customer_can_request_cancellation_and_host_can_approve_an_unpaid_order(): void
    {
        $customer = $this->user('attendee');
        $host = $this->user('host');
        $order = Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->firstOrFail();
        $order->forceFill(['user_id' => $customer->id, 'status' => 'awaiting_payment', 'amount_paid_minor' => 0])->save();
        Event::withoutGlobalScopes()->whereKey($order->event_id)->update(['refund_policy_deadline_hours' => 0, 'refund_policy_notes' => 'Event-specific policy.']);

        $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/cancellation-requests", ['reason' => 'Plans changed'])
            ->assertOk()->assertJsonPath('data.cancellation.status', 'pending')
            ->assertJsonPath('data.cancellation.policyNotes', 'Event-specific policy.');
        $request = CancellationRequest::firstOrFail();
        $this->actingAs($host)->postJson("/api/v1/operations/cancellation-requests/{$request->id}/review", ['decision' => 'approved', 'reason' => 'Within event policy'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('security_audit_logs', ['action' => 'cancellation_approved', 'target_id' => $order->id]);
    }

    public function test_another_attendee_cannot_request_cancellation_for_the_order(): void
    {
        $order = Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->firstOrFail();
        $attacker = User::create(['name' => 'Other Customer', 'email' => 'other@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $this->actingAs($attacker)->postJson("/api/v1/orders/{$order->id}/cancellation-requests", ['reason' => 'IDOR'])
            ->assertNotFound();
        $this->assertDatabaseCount('cancellation_requests', 0);
    }

    public function test_provider_paid_orders_are_not_shown_as_eligible_for_in_app_refunds(): void
    {
        $order = Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->firstOrFail();
        $event = Event::withoutGlobalScopes()->findOrFail($order->event_id);
        $event->update(['event_date_iso' => now()->addDays(10)->toDateString(), 'start_time' => '18:00', 'refund_policy_deadline_hours' => 0]);
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();
        Payment::create([
            'order_id' => $order->id,
            'organizer_id' => $order->organizer_id,
            'provider' => 'stripe',
            'method' => 'card',
            'status' => Payment::SUCCEEDED,
            'amount_minor' => $order->total_minor,
            'amount_received_minor' => $order->total_minor,
            'currency' => $order->currency,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'provider_reference' => 'pi_refund_policy_test',
        ]);

        $status = app(CancellationService::class)->statusFor($order->fresh());

        $this->assertFalse($status['eligible']);
        $this->assertStringContainsString('Provider-backed refunds are not supported', $status['refundEligibilityReason']);
    }

    public function test_non_refundable_ticket_categories_block_order_cancellation_requests(): void
    {
        $order = Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->firstOrFail();
        $event = Event::withoutGlobalScopes()->findOrFail($order->event_id);
        $event->update([
            'event_date_iso' => now()->addDays(10)->toDateString(),
            'start_time' => '18:00',
            'refund_policy_deadline_hours' => 0,
            'ticket_tiers' => [['id' => 'tier-vip-policy-test', 'category' => 'VIP']],
            'non_refundable_ticket_categories' => ['VIP'],
        ]);
        $order->items()->firstOrFail()->update(['ticket_tier_id' => 'tier-vip-policy-test']);
        $order->forceFill(['status' => 'paid', 'amount_paid_minor' => $order->total_minor])->save();

        $status = app(CancellationService::class)->statusFor($order->fresh());

        $this->assertFalse($status['eligible']);
        $this->assertStringContainsString('VIP ticket type is non-refundable', $status['refundEligibilityReason']);
    }
}
