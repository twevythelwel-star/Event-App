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
 *   Content-Security-Policy    see policy() and config security.csp
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
        if (config('security.csp.enabled', true) && !$headers->has('Content-Security-Policy')
            && !$headers->has('Content-Security-Policy-Report-Only')) {
            $name = config('security.csp.report_only', false) ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $defaults[$name] = self::policy($request);
        }
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

    /** The site-wide policy, as one header value. */
    public static function policy(?Request $request = null): string
    {
        $extra = (string) config('security.csp.extra_sources', '');
        $with = fn (string $sources) => trim($sources . ' ' . $extra);

        $directives = [
            'default-src' => "'self'",
            'script-src' => $with("'self' 'unsafe-inline'"),
            'style-src' => $with("'self' 'unsafe-inline' https://fonts.googleapis.com"),
            'font-src' => $with("'self' data: https://fonts.gstatic.com"),
            'img-src' => $with("'self' data: blob: https:"),
            'media-src' => $with("'self' blob: https:"),
            'connect-src' => $with("'self'"),
            'frame-src' => $with("'self'"),
            'worker-src' => "'self' blob:",
            'manifest-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
        ];
        $policy = implode('; ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($directives), $directives));
        if ($request?->isSecure()) {
            $policy .= '; upgrade-insecure-requests';
        }

        return $policy;
    }
}
