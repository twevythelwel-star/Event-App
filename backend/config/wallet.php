<?php

/*
| Wallet passes for tickets: Apple Wallet (.pkpass, docs/eventbite-apple-wallet.md), Google Wallet and Samsung Wallet
| (docs/eventbite-wallet-architecture.md, docs/eventbite-samsung-wallet.md). Every provider is off by default and its
| "Add to …" button stays hidden until it is configured and, where the provider requires it, approved and tested.
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

    // Provider operations (create, update, revoke, save checks) run on this queue, with retries and failed-job handling.
    'queue' => env('WALLET_QUEUE', 'default'),

    // Accounts (comma-separated emails) that see the "Add to …" button of a provider that is configured but not yet
    // approved or verified, to test it end to end (e.g. Samsung cards in Testing Mode). Everyone else sees nothing.
    'tester_emails' => env('WALLET_TESTER_EMAILS', ''),

    // Each provider's official "Add to … Wallet" badge, under public/. The artwork is the provider's, licensed under
    // its guidelines, and is not committed: download it from Apple (Add to Apple Wallet guidelines), Google (Google
    // Wallet brand guidelines) and Samsung (Samsung Wallet image resources) and place it at these paths. Until a
    // badge file is present the button is a plain text link with the provider's exact wording.
    'badges' => [
        'apple' => env('APPLE_WALLET_BADGE', 'images/wallet/add-to-apple-wallet.svg'),
        'google' => env('GOOGLE_WALLET_BADGE', 'images/wallet/add-to-google-wallet.svg'),
        'samsung' => env('SAMSUNG_WALLET_BADGE', 'images/wallet/add-to-samsung-wallet.png'),
    ],

    'google' => [
        'enabled' => (bool) env('GOOGLE_WALLET_ENABLED', false),

        // Google Pay & Wallet Console: an issuer account starts in demo mode, where only test users listed in the
        // console can save passes. Set to true only once Google has granted publishing access for the issuer.
        'publishing_approved' => (bool) env('GOOGLE_WALLET_PUBLISHING_APPROVED', false),

        // Our own end-to-end check on a real Android phone (save, update, revoke) has passed.
        'verified' => (bool) env('GOOGLE_WALLET_VERIFIED', false),

        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),

        // Service-account key JSON (from Google Cloud, granted access in the Wallet console): a file path outside the
        // repository, or the JSON base64-encoded in a secret environment variable. Never committed.
        'service_account_path' => env('GOOGLE_WALLET_SERVICE_ACCOUNT_PATH'),
        'service_account_base64' => env('GOOGLE_WALLET_SERVICE_ACCOUNT_BASE64'),

        // Web origins allowed to show the save link (defaults to APP_URL).
        'origins' => array_filter(explode(',', (string) env('GOOGLE_WALLET_ORIGINS', ''))),

        'api_base' => env('GOOGLE_WALLET_API_BASE', 'https://walletobjects.googleapis.com/walletobjects/v1'),
        'token_url' => env('GOOGLE_WALLET_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'save_url' => 'https://pay.google.com/gp/v/save/',
        'timeout' => (int) env('GOOGLE_WALLET_TIMEOUT', 10),
    ],

    'samsung' => [
        // Samsung Wallet partner integration ("Add to Samsung Wallet", ticket card). Three separate switches, all off
        // until each is true in fact: the button never shows before Samsung has approved the partner account and
        // launched the card, and we have tested it end to end on a Galaxy device. See docs/eventbite-samsung-wallet.md.
        'enabled' => (bool) env('SAMSUNG_WALLET_ENABLED', false),
        'partner_approved' => (bool) env('SAMSUNG_WALLET_PARTNER_APPROVED', false),
        'verified' => (bool) env('SAMSUNG_WALLET_VERIFIED', false),

        // From the Samsung Wallet Partners Portal (partner.walletsvc.samsung.com).
        'partner_id' => env('SAMSUNG_WALLET_PARTNER_ID'),
        'card_id' => env('SAMSUNG_WALLET_CARD_ID'),
        'certificate_id' => env('SAMSUNG_WALLET_CERTIFICATE_ID'),

        // Ticket sub type from Samsung's event-ticket spec: performances, sports, movies, entrances or others.
        'card_sub_type' => env('SAMSUNG_WALLET_CARD_SUB_TYPE', 'entrances'),

        // Our RSA private key (the one the CSR uploaded to the portal was made from): PEM file path or base64 PEM.
        'private_key_path' => env('SAMSUNG_WALLET_PRIVATE_KEY_PATH'),
        'private_key_base64' => env('SAMSUNG_WALLET_PRIVATE_KEY_BASE64'),
        'private_key_password' => env('SAMSUNG_WALLET_PRIVATE_KEY_PASSWORD'),

        // Samsung's public certificate from onboarding: encrypts card data to Samsung and verifies Samsung's calls.
        'samsung_certificate_path' => env('SAMSUNG_WALLET_SAMSUNG_CERTIFICATE_PATH'),
        'samsung_certificate_base64' => env('SAMSUNG_WALLET_SAMSUNG_CERTIFICATE_BASE64'),

        'link_base' => env('SAMSUNG_WALLET_LINK_BASE', 'https://a.swallet.link/atw/v3/'),
        'api_base' => env('SAMSUNG_WALLET_API_BASE', 'https://tsapi-card.walletsvc.samsung.com'),

        // Accept Samsung's calls to our partner endpoints only when their token is this fresh (seconds).
        'token_max_age' => (int) env('SAMSUNG_WALLET_TOKEN_MAX_AGE', 300),
        'timeout' => (int) env('SAMSUNG_WALLET_TIMEOUT', 10),
    ],
];
