<?php

/*
| Where uploaded images live, and how they are cached.
|
| Storage: any Laravel filesystem disk. The default is the private local disk; set MEDIA_DISK to an S3-compatible
| disk (S3, Cloudflare R2, DigitalOcean Spaces, …, configured in config/filesystems.php) to keep images in object
| storage. The disk must stay private: every image is served through MediaController, which checks whether the
| event is published and whether an administrator hid the image before anything is sent.
|
| CDN: put the CDN in front of the application for the /api/v1/media/* paths. Public images (published events, not
| hidden) are sent with long-lived, immutable cache headers whenever the link is versioned (?v=), which every page
| link is, so the CDN serves repeat requests; private ones are always "private, no-store" and are never cached.
| Static assets (CSS, icons, the library artwork) follow Laravel's ASSET_URL, so they can come from the CDN too.
*/

return [
    'disk' => env('MEDIA_DISK', 'local'),

    // Seconds a public, versioned image may be cached by browsers and CDNs; unversioned links get a shorter time.
    'public_max_age' => (int) env('MEDIA_PUBLIC_MAX_AGE', 31536000),
    'unversioned_max_age' => (int) env('MEDIA_UNVERSIONED_MAX_AGE', 300),
];
