<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTwilioSignature
{
    /**
     * Rejects Twilio callbacks whose X-Twilio-Signature does not match.
     * Signature = Base64(HMAC-SHA1(auth_token, full URL + POST params sorted by key, each as key.value)).
     * Fails closed when no auth token is configured.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authToken = (string) config('events.webhooks.twilio_auth_token', '');
        $signature = (string) $request->header('X-Twilio-Signature', '');

        if ($authToken === '' || $signature === '') {
            Log::warning('[TwilioWebhook] Rejected callback: missing auth token configuration or signature header.');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $params = $request->post();
        ksort($params, SORT_STRING);

        $payload = $request->fullUrl();
        foreach ($params as $key => $value) {
            $payload .= $key . (is_array($value) ? implode('', $value) : $value);
        }

        $expected = base64_encode(hash_hmac('sha1', $payload, $authToken, true));

        if (!hash_equals($expected, $signature)) {
            Log::warning('[TwilioWebhook] Rejected callback with invalid signature.');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }
}
