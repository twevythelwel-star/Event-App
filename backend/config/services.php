<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // SMS (and optional WhatsApp) notifications. Leave TWILIO_ACCOUNT_SID empty to disable sending:
    // messages are then recorded as not sent instead of being simulated.
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        // Public base URL Twilio can reach for delivery callbacks (defaults to APP_URL when that is public)
        'status_callback_base' => env('TWILIO_STATUS_CALLBACK_BASE'),
    ],

    // Address lookup and map previews for event venues. Leave the provider empty to run without maps: hosts still
    // enter and confirm the address, and no map pin is shown. mapbox_token is used server-side for lookups;
    // mapbox_public_token (a URL-restricted "pk." token) is the one placed in map image URLs, when set.
    'maps' => [
        'provider' => env('MAPS_PROVIDER'),
        'mapbox_token' => env('MAPBOX_TOKEN'),
        'mapbox_public_token' => env('MAPBOX_PUBLIC_TOKEN'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
