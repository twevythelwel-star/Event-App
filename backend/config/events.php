<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Event Browsing Access Policy
    |--------------------------------------------------------------------------
    |
    | When set to false, unauthenticated visitors can browse public event listings
    | and view public event details/flyers. When set to true, visitors must
    | register and sign in before accessing even public event browsing.
    | Note: Personal, order, payment, and event management operations ALWAYS require auth.
    |
    */
    'require_login_for_browsing' => (bool) env('REQUIRE_LOGIN_FOR_EVENT_BROWSING', false),

    /*
    |--------------------------------------------------------------------------
    | Delivery Webhook Credentials
    |--------------------------------------------------------------------------
    |
    | Secrets used to authenticate provider delivery callbacks. Webhooks are
    | rejected (403) whenever the relevant secret is left unset.
    |
    */
    'webhooks' => [
        'twilio_auth_token' => env('TWILIO_AUTH_TOKEN'),
        'mailgun_signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),
        'email_basic_auth_user' => env('EMAIL_WEBHOOK_BASIC_AUTH_USER'),
        'email_basic_auth_password' => env('EMAIL_WEBHOOK_BASIC_AUTH_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operating Market & Regional Configuration (US-Only Initial Launch)
    |--------------------------------------------------------------------------
    |
    | Island Event is configured to operate in the United States only for initial
    | launch. United States is the default country for event venues, addresses,
    | account-related location fields, and business operations.
    | Architecture remains modular for future international expansion.
    |
    */
    'market' => [
        'default_country' => 'US',
        'allowed_countries' => ['US'], // Strictly United States only for initial launch
        'country_name' => 'United States',
        'default_currency' => 'USD',
        'allowed_currencies' => ['USD'], // USD ($) default and initial operating currency
        'currency_symbol' => '$',
        'currency_minor_unit_factor' => 100, // Cents
        'default_timezone' => 'America/New_York',
        'supported_timezones' => [
            'America/New_York',    // Eastern Time (ET)
            'America/Chicago',     // Central Time (CT)
            'America/Denver',      // Mountain Time (MT)
            'America/Los_Angeles', // Pacific Time (PT)
            'America/Phoenix',     // Arizona (MST, no DST)
            'America/Anchorage',   // Alaska Time (AKT)
            'Pacific/Honolulu',    // Hawaii Standard Time (HST, no DST)
        ],
        'phone_country_code' => '+1',
        'phone_pattern' => '/^\+?1?\s*\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})$/',
        'address_format' => [
            'street' => ['required' => true, 'min' => 3, 'max' => 255],
            'city' => ['required' => true, 'min' => 2, 'max' => 100],
            'state' => ['required' => true, 'pattern' => '/^[A-Z]{2}$/'], // 2-letter US State/Territory code
            'zip' => ['required' => true, 'pattern' => '/^\d{5}(-\d{4})?$/'], // 5-digit US ZIP or ZIP+4
            'country' => ['required' => true, 'default' => 'US', 'allowed' => ['US']],
        ],
        // Supported Payment Gateways for US Launch
        'payments' => [
            'supported_methods' => ['cash', 'bank_transfer', 'zelle', 'ach'],
            'active_gateway' => env('PAYMENT_GATEWAY', 'manual_us'), // 'manual_us', 'stripe_us'
            'require_manual_verification' => true, // Uploaded screenshot is NEVER proof of funds
        ],
    ],
];
