<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    private const TWILIO_URL = 'https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages.json';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
        Mail::fake();
    }

    private function configureTwilio(): void
    {
        config([
            'services.twilio.account_sid' => 'ACtest',
            'services.twilio.auth_token' => 'test-token',
            'services.twilio.from' => '+18765550000',
            'app.url' => 'https://events.example.com',
        ]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function placeOrder(array $overrides = []): array
    {
        return $this->actingAs($this->user('attendee'))->postJson('/api/v1/events/south-florida-jamaican-fish-fry/orders', array_merge([
            'customer_name' => 'Notify Guest',
            'customer_phone' => '305 555 0123',
            'method' => 'bank',
            'currency' => 'USD',
            'payment_reference' => 'ACH-777',
            'items' => [['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 1]],
        ], $overrides))->assertCreated()->json('data');
    }

    private function logsFor(string $orderId)
    {
        return NotificationLog::withoutGlobalScopes()->where('order_id', $orderId)->get();
    }

    public function test_new_order_sends_a_real_sms_through_twilio(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SM123abc'], 201)]);

        $order = $this->placeOrder();

        $log = $this->logsFor($order['id'])->firstWhere('trigger', 'order_received');
        $this->assertSame('sent', $log->status);
        $this->assertSame('SM123abc', $log->provider_message_id);

        Http::assertSent(function (HttpRequest $request) use ($order) {
            return $request->url() === self::TWILIO_URL
                && $request['To'] === '+13055550123'
                && $request['From'] === '+18765550000'
                && str_contains($request['Body'], $order['orderNumber'])
                && $request['StatusCallback'] === 'https://events.example.com/api/webhooks/twilio/status-callback'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_without_twilio_messages_are_recorded_as_not_sent_never_simulated(): void
    {
        Http::fake();

        $order = $this->placeOrder();

        $log = $this->logsFor($order['id'])->firstWhere('channel', 'sms');
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Twilio is not configured', $log->failure_reason);
        $this->assertNull($log->provider_message_id);
        Http::assertNothingSent();
    }

    public function test_orders_with_an_email_also_get_an_email(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SM1'], 201)]);

        // The order email comes from the signed-in attendee's account
        $order = $this->placeOrder();

        $email = $this->logsFor($order['id'])->firstWhere('channel', 'email');
        $this->assertSame('attendee@islandevents.example', $email->recipient);
        $this->assertSame('sent', $email->status);
    }

    public function test_permanent_twilio_errors_fail_without_retrying(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response(['code' => 21211, 'message' => "The 'To' number is not valid."], 400)]);

        $order = $this->placeOrder();

        $log = $this->logsFor($order['id'])->firstWhere('channel', 'sms');
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('21211', $log->failure_reason);
        Http::assertSentCount(1);
    }

    public function test_daily_limit_fails_at_once_without_exposing_the_account_sid(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response([
            'code' => 63038,
            'message' => 'Account AC' . '0123456789abcdef0123456789abcdef' . ' exceeded the 50 daily messages limit',
        ], 429)]);

        $order = $this->placeOrder();

        $log = $this->logsFor($order['id'])->firstWhere('channel', 'sms');
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('63038', $log->failure_reason);
        $this->assertStringNotContainsString('AC0123456789abcdef', $log->failure_reason . json_encode($log->attempts_history));
        Http::assertSentCount(1);
    }

    public function test_retry_clears_the_previous_provider_message_id(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::sequence()
            ->push(['code' => 63038, 'message' => 'daily limit'], 429)
            ->push(['sid' => 'SMsecondTry'], 201)]);
        $order = $this->placeOrder();
        $log = $this->logsFor($order['id'])->firstWhere('channel', 'sms');
        $log->forceFill(['provider_message_id' => 'SMoldAttempt'])->save();

        $this->actingAs($this->user('host'))->postJson("/api/v1/operations/notifications/{$log->id}/retry")->assertOk();

        $this->assertSame('SMsecondTry', $log->fresh()->provider_message_id);
    }

    public function test_temporary_twilio_outage_does_not_break_ordering(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response(['message' => 'Service unavailable'], 503)]);

        // The order still succeeds; the message waits for a retry
        $order = $this->placeOrder();

        $log = $this->logsFor($order['id'])->firstWhere('channel', 'sms');
        $this->assertNotSame('sent', $log->status);
        $this->assertStringContainsString('Will retry', json_encode($log->attempts_history));
    }

    public function test_payment_and_pickup_moments_each_message_once(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SMx'], 201)]);
        $order = $this->placeOrder();
        $host = $this->user('host');

        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order['id']}/payment", ['status' => 'paid'])->assertOk();
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order['id']}/payment", ['status' => 'paid'])->assertOk();
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order['id']}/fulfillment", ['fulfillment_status' => 'ready_for_pickup'])->assertOk();
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order['id']}/close")->assertOk();

        $sms = $this->logsFor($order['id'])->where('channel', 'sms');
        $this->assertSame(
            ['order_collection_completed', 'order_received', 'payment_verified', 'pickup_instructions'],
            $sms->pluck('trigger')->sort()->values()->all()
        );
        $this->assertStringContainsString('ready', $sms->firstWhere('trigger', 'pickup_instructions')->message);
    }

    public function test_cash_receipts_message_with_the_amount(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SMc'], 201)]);
        $cashOrder = Order::withoutGlobalScopes()->where('event_id', 'evt-kingston-harbour-fry')->where('method', 'cash')->firstOrFail();

        $this->actingAs($this->user('host'))->postJson('/api/v1/operations/cash', ['order_id' => $cashOrder->id, 'amount' => 5])->assertCreated();

        $log = $this->logsFor($cashOrder->id)->firstWhere('trigger', 'cash_receipt_recorded');
        $this->assertStringContainsString('$5.00', $log->message);
    }

    public function test_host_sees_and_retries_messages_but_staff_and_other_hosts_cannot(): void
    {
        Http::fake();
        $order = $this->placeOrder();
        $failed = $this->logsFor($order['id'])->firstWhere('channel', 'sms');

        $list = $this->actingAs($this->user('host'))->getJson('/api/v1/operations/notifications')->assertOk()->json('data');
        $this->assertTrue(collect($list)->contains('id', $failed->id));
        $this->assertSame('failed', collect($list)->firstWhere('id', $failed->id)['status']);

        $this->actingAs($this->user('staff'))->getJson('/api/v1/operations/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->user('staff'))->postJson("/api/v1/operations/notifications/{$failed->id}/retry")->assertNotFound();

        // Once Twilio is configured, a retry goes out
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SMretry'], 201)]);
        $this->actingAs($this->user('host'))->postJson("/api/v1/operations/notifications/{$failed->id}/retry")->assertOk();
        $this->assertSame('sent', $failed->fresh()->status);

        $this->actingAs($this->user('host'))->postJson("/api/v1/operations/notifications/{$failed->id}/retry")->assertStatus(422);
    }

    public function test_event_reminders_go_to_ticket_holders_once_a_day(): void
    {
        $this->configureTwilio();
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SMr'], 201)]);
        $host = $this->user('host');

        // Seeded Kingston orders: one paid, one cash (authorized unpaid), two awaiting verification
        $this->actingAs($host)->postJson('/api/v1/operations/events/evt-kingston-harbour-fry/reminders')
            ->assertOk()
            ->assertJsonPath('reminded', 2);
        $count = NotificationLog::withoutGlobalScopes()->where('trigger', 'event_reminders')->count();

        $this->actingAs($host)->postJson('/api/v1/operations/events/evt-kingston-harbour-fry/reminders')->assertOk();
        $this->assertSame($count, NotificationLog::withoutGlobalScopes()->where('trigger', 'event_reminders')->count());

        $this->actingAs($this->user('staff'))->postJson('/api/v1/operations/events/evt-kingston-harbour-fry/reminders')->assertStatus(422);
    }

    public function test_delivery_callback_marks_a_sent_sms_delivered(): void
    {
        $this->configureTwilio();
        config(['events.webhooks.twilio_auth_token' => 'test-token']);
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SMdeliver'], 201)]);
        $order = $this->placeOrder();

        $url = url('/api/webhooks/twilio/status-callback');
        $params = ['MessageSid' => 'SMdeliver', 'MessageStatus' => 'delivered'];
        ksort($params);
        $payload = $url;
        foreach ($params as $k => $v) {
            $payload .= $k . $v;
        }
        $signature = base64_encode(hash_hmac('sha1', $payload, 'test-token', true));

        $this->post($url, $params, ['X-Twilio-Signature' => $signature])->assertOk();
        $this->assertSame('delivered', $this->logsFor($order['id'])->firstWhere('channel', 'sms')->status);
    }
}
