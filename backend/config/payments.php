<?php

/*
 * Payment processors. Credentials come from the environment only and are never stored in the database
 * or shown in the admin console. A processor whose credentials are missing is treated as not configured,
 * and its methods are never offered at checkout. The platform administrator must also approve it
 * (Admin -> Platform settings) before any organiser can turn its methods on.
 */
return [

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com'),
        // Seconds a webhook signature timestamp may be old before the event is refused (replay protection).
        'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
        // Currencies this account settles; methods are hidden for events in any other currency.
        'currencies' => array_values(array_filter(array_map('trim', explode(',', (string) env('STRIPE_CURRENCIES', 'USD'))))),
        // Smallest charge Stripe accepts, in minor units, per currency.
        'minimum_minor' => ['USD' => 50],
        'reconciliation' => [
            'enabled' => (bool) env('STRIPE_RECONCILIATION_ENABLED', true),
            'stale_minutes' => (int) env('PAYMENT_RECONCILIATION_STALE_MINUTES', 5),
            'batch_size' => (int) env('PAYMENT_RECONCILIATION_BATCH_SIZE', 100),
        ],
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
        'currencies' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYPAL_CURRENCIES', 'USD'))))),
    ],

];
