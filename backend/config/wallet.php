<?php

/*
| Apple Wallet event-ticket passes (.pkpass). See docs/eventbite-apple-wallet.md.
|
| Nothing secret lives in this file or in the repository. The pass certificate (a .p12 holding the Apple-issued
| Pass Type ID certificate and its private key) and its password come from the environment only, as a file path
| outside the repository or as base64 in a secret environment variable (for hosts without a persistent disk).
*/

return [
    'apple' => [
        // Off until the certificate is installed; the "Add to Apple Wallet" button only shows when this is on and
        // the configuration below is complete (php artisan wallet:check).
        'enabled' => (bool) env('APPLE_WALLET_ENABLED', false),

        // Pass Type ID registered in the Apple Developer account, e.g. pass.com.example.eventbite.ticket.
        'pass_type_id' => env('APPLE_WALLET_PASS_TYPE_ID'),

        // The 10-character Team ID of that Apple Developer account.
        'team_id' => env('APPLE_WALLET_TEAM_ID'),

        // Shown by Wallet as who issued the pass.
        'organization_name' => env('APPLE_WALLET_ORGANIZATION_NAME', 'EventBite'),

        // The Pass Type ID certificate with its private key, exported as .p12. Use one of the two.
        'certificate_path' => env('APPLE_WALLET_CERTIFICATE_PATH'),
        'certificate_base64' => env('APPLE_WALLET_CERTIFICATE_BASE64'),
        'certificate_password' => env('APPLE_WALLET_CERTIFICATE_PASSWORD'),

        // Apple's Worldwide Developer Relations intermediate (G4), a public certificate. When unset, the copy
        // shipped with the pkpass/pkpass library is used.
        'wwdr_certificate_path' => env('APPLE_WALLET_WWDR_CERTIFICATE_PATH'),

        // Where devices register for updates and fetch new versions of a pass. Must be HTTPS; Wallet appends
        // /v1/... to it. Defaults to APP_URL/wallet. Without HTTPS, passes are issued without live updates.
        'web_service_url' => env('APPLE_WALLET_WEB_SERVICE_URL'),

        // Update pushes go to Apple Push Notification service with the same pass certificate.
        'push' => [
            'enabled' => (bool) env('APPLE_WALLET_PUSH_ENABLED', true),
            'host' => env('APPLE_WALLET_APNS_HOST', 'https://api.push.apple.com'),
            'timeout' => (int) env('APPLE_WALLET_APNS_TIMEOUT', 10),
        ],

        // Rendered pass images (icon, logo, strip) are cached here, keyed by their source.
        'image_cache_path' => storage_path('app/private/wallet-images'),
    ],
];
