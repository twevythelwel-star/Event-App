<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const TWILIO_TOKEN = 'test-twilio-auth-token';
    private const MAILGUN_KEY = 'test-mailgun-signing-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'events.webhooks.twilio_auth_token' => self::TWILIO_TOKEN,
            'events.webhooks.mailgun_signing_key' => self::MAILGUN_KEY,
            'events.webhooks.email_basic_auth_user' => 'postmark',
            'events.webhooks.email_basic_auth_password' => 'hook-secret',
        ]);
    }

    private function makeLog(string $providerMessageId): NotificationLog
    {
        return NotificationLog::create([
            'idempotency_key' => 'idem-' . $providerMessageId,
            'customer_name' => 'Test Guest',
            'recipient' => '+18765550000',
            'channel' => 'sms',
            'trigger' => 'order_received',
            'title' => 'Order received',
            'message' => 'We received your order.',
            'status' => 'sent',
            'provider_message_id' => $providerMessageId,
        ]);
    }

    private function twilioSignature(string $url, array $params, string $token = self::TWILIO_TOKEN): string
    {
        ksort($params, SORT_STRING);
        $payload = $url;
        foreach ($params as $key => $value) {
            $payload .= $key . $value;
        }

        return base64_encode(hash_hmac('sha1', $payload, $token, true));
    }

    public function test_twilio_callback_with_valid_signature_updates_log(): void
    {
        $log = $this->makeLog('SM123');
        $url = url('/api/webhooks/twilio/status-callback');
        $params = ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'];

        $this->post($url, $params, ['X-Twilio-Signature' => $this->twilioSignature($url, $params)])
            ->assertOk()
            ->assertJson(['updated_status' => 'delivered']);

        $this->assertSame('delivered', $log->fresh()->status);
    }

    public function test_twilio_callback_with_forged_signature_is_rejected(): void
    {
        $log = $this->makeLog('SM124');
        $url = url('/api/webhooks/twilio/status-callback');
        $params = ['MessageSid' => 'SM124', 'MessageStatus' => 'delivered'];

        $this->post($url, $params, ['X-Twilio-Signature' => $this->twilioSignature($url, $params, 'wrong-token')])
            ->assertForbidden();

        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_twilio_callback_is_rejected_when_params_are_tampered(): void
    {
        $this->makeLog('SM125');
        $url = url('/api/webhooks/twilio/status-callback');
        $signature = $this->twilioSignature($url, ['MessageSid' => 'SM125', 'MessageStatus' => 'failed']);

        $this->post($url, ['MessageSid' => 'SM125', 'MessageStatus' => 'delivered'], ['X-Twilio-Signature' => $signature])
            ->assertForbidden();
    }

    public function test_twilio_callback_fails_closed_without_configured_token(): void
    {
        config(['events.webhooks.twilio_auth_token' => null]);
        $url = url('/api/webhooks/twilio/status-callback');
        $params = ['MessageSid' => 'SM126', 'MessageStatus' => 'delivered'];

        $this->post($url, $params, ['X-Twilio-Signature' => $this->twilioSignature($url, $params, '')])
            ->assertForbidden();
    }

    private function mailgunSignature(int $timestamp, string $token, string $key = self::MAILGUN_KEY): array
    {
        return [
            'timestamp' => (string) $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp . $token, $key),
        ];
    }

    public function test_mailgun_webhook_with_valid_signature_is_processed(): void
    {
        $log = $this->makeLog('mg-1');

        $this->postJson('/api/webhooks/email', [
            'signature' => $this->mailgunSignature(time(), 'tok-1'),
            'MessageID' => 'mg-1',
            'event' => 'delivered',
        ])->assertOk();

        $this->assertSame('delivered', $log->fresh()->status);
    }

    public function test_mailgun_webhook_with_bad_signature_is_rejected(): void
    {
        $this->postJson('/api/webhooks/email', [
            'signature' => $this->mailgunSignature(time(), 'tok-2', 'wrong-key'),
            'MessageID' => 'mg-2',
            'event' => 'delivered',
        ])->assertForbidden();
    }

    public function test_mailgun_webhook_with_stale_timestamp_is_rejected(): void
    {
        $this->postJson('/api/webhooks/email', [
            'signature' => $this->mailgunSignature(time() - 3600, 'tok-3'),
            'MessageID' => 'mg-3',
            'event' => 'delivered',
        ])->assertForbidden();
    }

    public function test_postmark_webhook_requires_basic_auth_credentials(): void
    {
        $log = $this->makeLog('pm-1');
        $payload = ['MessageID' => 'pm-1', 'RecordType' => 'Delivery'];

        $this->postJson('/api/webhooks/email', $payload)->assertForbidden();
        $this->postJson('/api/webhooks/email', $payload, ['Authorization' => 'Basic ' . base64_encode('postmark:wrong')])
            ->assertForbidden();

        $this->postJson('/api/webhooks/email', $payload, ['Authorization' => 'Basic ' . base64_encode('postmark:hook-secret')])
            ->assertOk();

        $this->assertSame('delivered', $log->fresh()->status);
    }
}
