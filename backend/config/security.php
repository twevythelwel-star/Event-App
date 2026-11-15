<?php

return [
    /*
    | Strict-Transport-Security max-age in seconds, sent on HTTPS responses outside local and testing
    | (App\Http\Middleware\SecurityHeaders). One year by default; 0 turns the header off.
    */
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    /*
    | Proxies whose X-Forwarded-* headers are trusted, so the app knows a request arrived over HTTPS and the
    | visitor's real IP. "*" trusts the platform's load balancer wherever it is (Laravel Cloud terminates TLS
    | there); on a self-managed server behind a local NGINX, set the proxy's address, e.g. 127.0.0.1.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),

    /*
    | Content-Security-Policy sent on every web response (App\Http\Middleware\SecurityHeaders). Scripts, styles,
    | fonts, frames, form posts and connections are limited to this site (plus Google Fonts). Images may come from
    | any HTTPS host, because event photos can be stored on object storage.
    |
    | Scripts still need 'unsafe-inline': the views use inline <script> blocks and on*="" handlers. Moving those
    | into files is what lets 'unsafe-inline' be dropped.
    |
    | CSP_EXTRA_SOURCES adds origins to every fetch directive (e.g. a CDN in front of storage), space-separated.
    | CSP_REPORT_ONLY=true sends the policy as Content-Security-Policy-Report-Only instead (log, don't block).
    */
    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),
        'extra_sources' => trim((string) env('CSP_EXTRA_SOURCES', '')),
    ],
];
