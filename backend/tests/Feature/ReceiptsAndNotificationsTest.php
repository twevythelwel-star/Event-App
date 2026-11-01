<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\ReceiptService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Customer messages for every order and event moment, in U.S. formatting, through the existing SMS and email
 * pipeline. Payment-confirmed messages only go out for verified payments; "sent" never becomes "delivered"
 * without the provider saying so.
 */
class ReceiptsAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = 'evt-kingston-harbour-fry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class, DemoOrdersSeeder::class]);
        Mail::fake();
        config(['services.twilio.account_sid' => 'ACtest', 'services.twilio.auth_token' => 'test-token', 'services.twilio.from' => '+13055550000']);
        Http::fake(['https://api.twilio.com/*' => Http::response(['sid' => 'SMtest'], 201)]);
    }

    private function user(string $who): User
    {
        return User::where('email', "{$who}@islandevents.example")->firstOrFail();
    }

    private function placeOrder(string $method = 'bank'): Order
    {
        $id = $this->actingAs($this->user('attendee'))->postJson('/api/v1/events/south-florida-jamaican-fish-fry/orders', [
            'customer_name' => 'Receipt Guest',
            'customer_phone' => '305 555 0123',
            'method' => $method,
            'currency' => 'USD',
            'items' => [['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        return Order::withoutGlobalScopes()->findOrFail($id);
    }

    /** @return \Illuminate\Support\Collection<int, NotificationLog> */
    private function sms(Order $order)
    {
        return NotificationLog::withoutGlobalScopes()->where('order_id', $order->id)->where('channel', 'sms')->get();
    }

    public function test_verified_payment_confirms_and_issues_the_ticket_once(): void
    {
        $order = $this->placeOrder();
        $this->assertSame(['order_received'], $this->sms($order)->pluck('trigger')->all());
        $this->assertStringContainsString('not confirmed yet', $this->sms($order)->first()->message);

        $host = $this->user('host');
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'paid'])->assertOk();
        $this->actingAs($host)->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'paid'])->assertOk();

        $sms = $this->sms($order);
        $this->assertSame(['order_received', 'payment_verified', 'ticket_issued'], $sms->pluck('trigger')->sort()->values()->all());
        $this->assertSame('Payment confirmed', $sms->firstWhere('trigger', 'payment_verified')->title);
        $this->assertStringContainsString('$', $sms->firstWhere('trigger', 'payment_verified')->message);
        $this->assertStringContainsString('Bank transfer', $sms->firstWhere('trigger', 'payment_verified')->message);
        // U.S. date in the event's own zone
        $this->assertMatchesRegularExpression('/(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday), [A-Z][a-z]+ \d{1,2}, \d{4}/', $sms->firstWhere('trigger', 'ticket_issued')->message);
    }

    public function test_payment_confirmed_is_never_sent_for_an_unverified_payment(): void
    {
        $order = $this->placeOrder();

        // Even a direct call cannot confirm money that was not verified
        $this->assertSame([], app(NotificationService::class)->queueForOrder($order, 'payment_verified'));
        $this->assertSame([], app(NotificationService::class)->queueForOrder($order, 'ticket_issued'));

        // A partial payment is not a confirmation either
        $this->actingAs($this->user('host'))->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'partially_paid', 'amount_paid' => 5])->assertOk();
        $this->assertFalse($this->sms($order)->contains('trigger', 'payment_verified'));
        $this->assertFalse($this->sms($order)->contains('trigger', 'ticket_issued'));
    }

    public function test_a_rejected_payment_reports_a_failure_and_no_ticket(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->user('host'))->postJson("/api/v1/operations/orders/{$order->id}/payment", ['status' => 'rejected', 'rejection_reason' => 'No transfer found'])->assertOk();

        $failed = $this->sms($order)->firstWhere('trigger', 'payment_rejected');
        $this->assertSame('Payment failed', $failed->title);
        $this->assertStringContainsString('No transfer found', $failed->message);
        $this->assertFalse($this->sms($order)->contains('trigger', 'ticket_issued'));
    }

    public function test_sending_a_transfer_reference_says_the_payment_is_awaiting_verification(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->user('attendee'))->post("/tickets/{$order->id}/payment-reference", ['payment_reference' => 'ZELLE-4411'])->assertRedirect();

        $log = $this->sms($order)->firstWhere('trigger', 'payment_awaiting_verification');
        $this->assertNotNull($log);
        $this->assertStringContainsString('not valid until', $log->message);
        $this->assertFalse($this->sms($order->fresh())->contains('trigger', 'payment_verified'));
    }

    public function test_cash_in_full_confirms_payment_but_part_cash_does_not(): void
    {
        $order = $this->placeOrder('cash');
        $host = $this->user('host');
        $half = round((int) $order->total_minor / 200, 2);

        $this->actingAs($host)->postJson('/api/v1/operations/cash', ['order_id' => $order->id, 'amount' => $half])->assertCreated();
        $this->assertFalse($this->sms($order)->contains('trigger', 'payment_verified'));
        $this->assertStringContainsString('Balance due', $this->sms($order)->firstWhere('trigger', 'cash_receipt_recorded')->message);

        $rest = round(((int) $order->total_minor - (int) round($half * 100)) / 100, 2);
        $this->actingAs($host)->postJson('/api/v1/operations/cash', ['order_id' => $order->id, 'amount' => $rest])->assertCreated();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertTrue($this->sms($order)->contains('trigger', 'payment_verified'));
        $this->assertTrue($this->sms($order)->contains('trigger', 'ticket_issued'));
    }

    public function test_refund_request_and_refund_completion_are_both_messaged(): void
    {
        $attendee = $this->user('attendee');
        $paid = Order::withoutGlobalScopes()->where('user_id', $attendee->id)->where('status', 'paid')->where('event_id', self::EVENT)->firstOrFail();

        $this->actingAs($attendee)->postJson('/api/v1/disputes', [
            'order_id' => $paid->id, 'dispute_type' => 'ticket_refund', 'reason' => 'I can no longer attend the event.', 'amount' => 5,
        ])->assertCreated();
        $requested = $this->sms($paid)->firstWhere('trigger', 'refund_requested');
        $this->assertNotNull($requested);
        $this->assertStringContainsString('no money has been returned yet', $requested->message);

        $this->actingAs($this->user('host'))->postJson("/api/v1/operations/orders/{$paid->id}/refund", ['amount' => '5.00', 'reason' => 'Customer cannot attend'])->assertOk();
        $done = $this->sms($paid)->firstWhere('trigger', 'refund_completed');
        $this->assertNotNull($done);
        $this->assertStringContainsString('$5.00', $done->message);
    }

    public function test_changing_when_or_where_messages_ticket_holders_once(): void
    {
        $order = $this->placeOrder();
        $event = Event::allTenants()->findOrFail(self::EVENT);
        $host = $this->user('host');

        $this->actingAs($host)->patchJson('/api/v1/host/events/' . self::EVENT, ['time_str' => '7:00 PM - 11:00 PM'])->assertOk();
        $this->actingAs($host)->patchJson('/api/v1/host/events/' . self::EVENT, ['time_str' => '7:00 PM - 11:00 PM'])->assertOk();
        $this->actingAs($host)->patchJson('/api/v1/host/events/' . self::EVENT, ['tagline' => 'A new tagline only'])->assertOk();

        $updates = $this->sms($order)->where('trigger', 'event_updated');
        $this->assertCount(1, $updates);
        $this->assertStringContainsString('7:00 PM - 11:00 PM', $updates->first()->message);
        $this->assertStringContainsString($event->city . ', ' . $event->state_code, $updates->first()->message);
    }

    public function test_receipt_carries_every_required_detail_in_us_format(): void
    {
        $order = $this->placeOrder();
        $order->forceFill(['payment_reference' => 'ACH-55821'])->save();
        $receipt = app(ReceiptService::class)->forOrder($order->fresh());
        $html = view('emails.notification', ['log' => new NotificationLog(['message' => 'Order received']), 'receipt' => $receipt])->render();
        $event = Event::allTenants()->findOrFail(self::EVENT);

        foreach ([
            $event->title,                                     // event name
            \Illuminate\Support\Carbon::parse($event->event_date_iso)->format('l, F j, Y'), // U.S. date
            $event->city . ', ' . $event->state_code,          // venue location
            $order->order_number,                              // order reference
            $order->ticket_code,                               // ticket details
            $order->items->first()->name,                      // itemized food
            '2 ×',
            'Total (USD)',                                     // currency and total
            'Bank transfer',                                   // payment method
            'ACH-55821',                                       // payment reference
            'not proof of payment',                            // unverified orders say so
        ] as $expected) {
            $this->assertStringContainsString(e($expected), $html, "receipt shows {$expected}");
        }
        $this->assertMatchesRegularExpression('/\$\d+\.\d{2}/', $html);
        $this->assertMatchesRegularExpression('/(EST|EDT|CST|CDT|MST|MDT|PST|PDT)/', $html, 'time carries its zone');
        foreach ($order->charges as $charge) {
            $this->assertStringContainsString(e($charge->label), $html, 'fees and taxes are itemized');
        }
        // The admission QR secret is never put in an email
        $this->assertStringNotContainsString($order->secure_ticket_token, $html);
        $this->assertStringNotContainsString($order->secure_ticket_token, app(ReceiptService::class)->toText($receipt));
    }

    public function test_messages_are_sent_not_delivered_until_the_provider_confirms(): void
    {
        $order = $this->placeOrder();
        $log = $this->sms($order)->first();

        $this->assertSame('sent', $log->status);
        $this->assertNull($log->delivered_at);
        $this->assertStringContainsString('Awaiting delivery confirmation', json_encode($log->attempts_history));
    }
}
