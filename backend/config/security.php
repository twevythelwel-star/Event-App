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
];
