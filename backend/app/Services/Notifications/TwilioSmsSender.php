<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;

/**
 * Sends SMS through Twilio's REST API and returns the message SID.
 *
 * Twilio only accepts a message here; delivery to the handset is confirmed later by the status callback
 * (/api/webhooks/twilio/status-callback), so a successful send means "sent", never "delivered".
 */
class TwilioSmsSender
{
    /**
     * Twilio error codes that will fail the same way on every automatic retry: bad or unreachable numbers,
     * opt-outs, unsupported regions, trial accounts texting an unverified number (21608), and the account's
     * daily message limit (63038), which only resets the next day. Hosts can still retry these by hand.
     */
    private const PERMANENT_ERRORS = [21211, 21214, 21408, 21606, 21608, 21610, 21612, 21614, 63038];

    public function isConfigured(): bool
    {
        $config = config('services.twilio');

        return !empty($config['account_sid']) && !empty($config['auth_token'])
            && (!empty($config['from']) || !empty($config['messaging_service_sid']));
    }

    public function send(string $to, string $body): string
    {
        if (!$this->isConfigured()) {
            throw new NotificationNotSent('SMS not sent: Twilio is not configured (set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN and TWILIO_FROM).');
        }

        $config = config('services.twilio');
        $payload = ['To' => self::toE164($to), 'Body' => $body];
        if (!empty($config['messaging_service_sid'])) {
            $payload['MessagingServiceSid'] = $config['messaging_service_sid'];
        } else {
            $payload['From'] = $config['from'];
        }
        if ($callback = self::statusCallbackUrl()) {
            $payload['StatusCallback'] = $callback;
        }

        $response = Http::asForm()
            ->withBasicAuth($config['account_sid'], $config['auth_token'])
            ->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config['account_sid']}/Messages.json", $payload);

        if ($response->successful()) {
            return (string) $response->json('sid');
        }

        $code = (int) $response->json('code');
        // Twilio's text can include the Account SID; hosts read these reasons, so keep it out
        $detail = preg_replace(
            ['/\bAccount AC[0-9a-f]{32}/i', '/AC[0-9a-f]{32}/i'],
            ['The Twilio account', 'the Twilio account'],
            (string) ($response->json('message') ?? $response->status())
        );
        $message = "Twilio error {$code}: {$detail}";
        if (in_array($code, self::PERMANENT_ERRORS, true) || $response->status() === 401) {
            throw new NotificationNotSent($message);
        }

        // Rate limits and server errors are worth retrying
        throw new \RuntimeException($message);
    }

    /**
     * Jamaican and US numbers share the +1 country code: 10 digits gain "+1", 11 digits starting with 1 gain "+".
     */
    public static function toE164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new NotificationNotSent("SMS not sent: '{$phone}' is not a valid phone number.");
        }

        return '+' . $digits;
    }

    /**
     * Twilio can only call back a public URL, so local development sends no callback.
     */
    private static function statusCallbackUrl(): ?string
    {
        $base = config('services.twilio.status_callback_base') ?: config('app.url');
        if (!$base || preg_match('#^https?://(localhost|127\.|\[::1\])#i', $base)) {
            return null;
        }

        return rtrim($base, '/') . '/api/webhooks/twilio/status-callback';
    }
}
