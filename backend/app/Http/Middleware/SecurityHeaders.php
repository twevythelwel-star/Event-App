<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every web response, so they hold wherever the app runs (Laravel Cloud has no
 * NGINX config of ours to set them). A header a response already sets (e.g. the QR artwork's own
 * Content-Security-Policy) is kept as it is.
 *
 *   Strict-Transport-Security  HTTPS requests only, outside local and testing (config security.hsts_max_age)
 *   X-Content-Type-Options     nosniff
 *   X-Frame-Options            SAMEORIGIN (no framing by other sites)
 *   Referrer-Policy            strict-origin-when-cross-origin
 *   Permissions-Policy         no camera, microphone, location, payment or USB access
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];
        $maxAge = (int) config('security.hsts_max_age', 31536000);
        if ($request->isSecure() && $maxAge > 0 && !app()->environment('local', 'testing')) {
            $defaults['Strict-Transport-Security'] = "max-age={$maxAge}; includeSubDomains";
        }

        foreach ($defaults as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        return $response;
    }
}
