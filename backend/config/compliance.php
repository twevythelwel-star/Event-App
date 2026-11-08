<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | US state and local compliance database (docs/eventbite-compliance-database.md)
    |--------------------------------------------------------------------------
    */
    'database' => [
        // A record is approved by a different administrator from the one who submitted it.
        'require_second_reviewer' => (bool) env('COMPLIANCE_REQUIRE_SECOND_REVIEWER', true),
        // Approved records are due for review again after this many months unless a review date is set.
        'review_interval_months' => (int) env('COMPLIANCE_REVIEW_INTERVAL_MONTHS', 12),
        // The Census Bureau's official 2020 FIPS code files: counties and places (cities, towns, villages, boroughs).
        'census_county_file_url' => 'https://www2.census.gov/geo/docs/reference/codes2020/national_county2020.txt',
        'census_place_file_url' => 'https://www2.census.gov/geo/docs/reference/codes2020/national_place2020.txt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Host Government-Issued Identity Verification Configuration
    |--------------------------------------------------------------------------
    |
    | Controls acceptable government-issued ID document types, private encrypted
    | storage, temporary signed access links, malware scanning, and legally
    | reviewed retention/deletion schedules.
    |
    */
    'host_verification' => [
        // Configurable acceptable government-issued identity document types
        'supported_document_types' => [
            'drivers_licence' => "Driver's licence",
            'state_id' => 'State-issued identification card',
            'passport' => 'Passport',
            'other_government_id' => 'Other government-issued identification approved by the compliance administrator',
        ],

        // Never store in public storage; always use private local/vault disk with encryption at rest
        'storage_disk' => env('HOST_ID_STORAGE_DISK', 'local'),
        'storage_prefix' => 'host-identity-documents',
        'encrypt_at_rest' => true,
        'encryption_cipher' => 'AES-256-CBC',

        // Strict file type, size, and content validation
        'max_size_bytes' => (int) env('HOST_ID_MAX_BYTES', 5 * 1024 * 1024), // 5 MB
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        'malware_scan_enabled' => (bool) env('HOST_ID_MALWARE_SCAN', true),

        // Temporary, authorized administrator-only document access links (minutes)
        'signed_url_ttl_minutes' => (int) env('HOST_ID_SIGNED_URL_TTL_MINUTES', 5),

        // Legally reviewed retention and deletion schedule (days).
        // Raw identity document blobs are deleted after retention expires; only minimal
        // verification evidence (status, document_type, last4, SHA-256 hash, reviewer, timestamps) is kept.
        'retention_days_after_approval' => (int) env('HOST_ID_RETENTION_DAYS_APPROVED', 90),
        'retention_days_after_rejection' => (int) env('HOST_ID_RETENTION_DAYS_REJECTED', 30),
        'retention_days_unreviewed_abandoned' => (int) env('HOST_ID_RETENTION_DAYS_ABANDONED', 60),
    ],
];

