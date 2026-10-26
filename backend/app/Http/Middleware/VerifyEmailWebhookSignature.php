<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailWebhookSignature
{
    /**
     * Maximum age of a Mailgun signature timestamp before it is treated as a replay.
     */
    private const MAX_SIGNATURE_AGE_SECONDS = 300;

    /**
     * Authenticates email provider webhooks:
     *  - Mailgun: payload carries signature.{timestamp, token, signature} = HMAC-SHA256(signing_key, timestamp . token).
     *  - Postmark: no payload signature; authenticated via HTTP Basic credentials embedded in the webhook URL.
     * Fails closed when the matching provider secret is not configured.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $verified = $request->has('signature.signature')
            ? $this->verifyMailgun($request)
            : $this->verifyBasicAuth($request);

        if (!$verified) {
            Log::warning('[EmailWebhook] Rejected webhook with missing or invalid credentials.');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }

    private function verifyMailgun(Request $request): bool
    {
        $signingKey = (string) config('events.webhooks.mailgun_signing_key', '');
        $timestamp = (string) $request->input('signature.timestamp', '');
        $token = (string) $request->input('signature.token', '');
        $signature = (string) $request->input('signature.signature', '');

        if ($signingKey === '' || $timestamp === '' || $token === '' || !ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::MAX_SIGNATURE_AGE_SECONDS) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp . $token, $signingKey), $signature);
    }

    private function verifyBasicAuth(Request $request): bool
    {
        $username = (string) config('events.webhooks.email_basic_auth_user', '');
        $password = (string) config('events.webhooks.email_basic_auth_password', '');

        if ($username === '' || $password === '') {
            return false;
        }

        return hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());
    }
}
