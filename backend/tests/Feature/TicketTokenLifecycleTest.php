<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ticket token lifecycle: generation, hash-only storage, reissue and revocation.
 * Raw tokens never touch the database; only their SHA-256 hashes do.
 */
class TicketTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $organizer = Organizer::create(['name' => 'Token Org', 'slug' => 'token-org']);
        $this->event = Event::allTenants()->create([
            'organizer_id' => $organizer->id,
            'slug' => 'token-night',
            'title' => 'Token Night',
            'date_str' => 'Saturday, November 28, 2026',
            'time_str' => '6:00 PM - 11:00 PM',
            'venue_name' => 'Pier',
            'venue_address' => 'Kingston',
            'organizer_name' => 'Token Org',
            'organizer_phone' => '+18765550000',
            'advance_order_deadline' => now()->addWeek(),
            'status' => 'published',
        ]);
        $this->owner = User::create(['name' => 'Token Owner', 'email' => 'owner@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function paidOrder(): Order
    {
        $token = Order::generateSecureTicketToken();

        return Order::allTenants()->create([
            'organizer_id' => $this->event->organizer_id,
            'event_id' => $this->event->id,
            'user_id' => $this->owner->id,
            'ticket_code' => 'TKT-1',
            'order_number' => 'ORD-TKT-1',
            'customer_name' => 'Token Owner',
            'customer_phone' => '+1 876 555 0101',
            'email' => 'owner@example.test',
            'method' => 'bank',
            'subtotal' => 2000,
            'total_amount' => 2000,
            'amount_paid' => 2000,
            'currency' => 'JMD',
            'status' => 'paid',
            'secure_ticket_token' => $token,
        ]);
    }

    public function test_database_never_stores_the_raw_token(): void
    {
        $order = $this->paidOrder();
        $raw = (string) $order->secure_ticket_token;

        $this->assertStringStartsWith('tok_', $raw);
        $this->assertSame(68, strlen($raw)); // tok_ + 64 hex chars = 256 bits

        $stored = Order::withoutGlobalScopes()->whereKey($order->id)->first();
        $this->assertStringStartsWith('sha256:', (string) $stored->getRawOriginal('secure_ticket_token'));
        $this->assertSame(Order::hashTicketToken($raw), $stored->secure_ticket_token_hash);
        $this->assertStringNotContainsString($raw, json_encode($stored->getAttributes()));
    }

    public function test_reissue_rotates_token_invalidates_old_and_notifies_holder(): void
    {
        $order = $this->paidOrder();
        $oldToken = (string) $order->secure_ticket_token;
        $oldHash = Order::hashTicketToken($oldToken);
        $statusBefore = $order->status;

        $newToken = $order->reissueTicketToken($this->owner, 'Compromised screenshot');
        $order->refresh();

        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(Order::hashTicketToken($newToken), $order->secure_ticket_token_hash);
        $this->assertSame(2, (int) $order->token_version);
        $this->assertNotNull($order->token_reissued_at);
        $this->assertSame($statusBefore, $order->status, 'Reissue must not change payment status');

        // The old token resolves to nothing; the new one resolves to this order.
        $this->assertSame(0, Order::withoutGlobalScopes()->whereTicketToken($oldToken)->count());
        $this->assertSame(1, Order::withoutGlobalScopes()->whereTicketToken($newToken)->count());

        $this->assertDatabaseHas('notification_logs', [
            'order_id' => $order->id,
            'trigger' => 'ticket_reissued',
        ]);
    }

    public function test_reissue_is_refused_after_gate_redemption(): void
    {
        $order = $this->paidOrder();
        $order->forceFill(['checked_in_at' => now()])->save();

        $this->expectException(\App\Services\OrderPlacementException::class);
        $order->reissueTicketToken($this->owner, 'too late');
    }

    public function test_revocation_kills_token_immediately_and_preserves_history(): void
    {
        $order = $this->paidOrder();
        $token = (string) $order->secure_ticket_token;

        $order->revokeTicketToken($this->owner, 'Suspected fraud');
        $order->refresh();

        $this->assertTrue($order->isTicketRevoked());
        $this->assertNotNull($order->token_revoked_at);
        $this->assertSame('Suspected fraud', $order->revocation_reason);
        $this->assertSame(2, (int) $order->token_version);
        $this->assertSame(0, Order::withoutGlobalScopes()->whereTicketToken($token)->count());

        // Order row and redemption history are preserved, not deleted.
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_number' => 'ORD-TKT-1']);

        $this->assertDatabaseHas('notification_logs', [
            'order_id' => $order->id,
            'trigger' => 'ticket_revoked',
        ]);
    }

    public function test_revoked_order_status_also_revokes_ticket(): void
    {
        $order = $this->paidOrder();
        $order->forceFill(['status' => 'refunded'])->save();

        $this->assertTrue($order->fresh()->isTicketRevoked());
        $this->assertFalse($order->fresh()->isSettledForAdmission());
    }
}
