<?php

return [
    'booking_duplicate_window_minutes' => (int) env('BOOKING_DUPLICATE_WINDOW_MINUTES', 10),

    'rate_limits' => [
        'playback_per_minute' => (int) env('CONCERT_PLAYBACK_RATE_LIMIT_PER_MINUTE', 60),
        'media_per_minute' => (int) env('CONCERT_MEDIA_RATE_LIMIT_PER_MINUTE', 180),
        'download_per_minute' => (int) env('CONCERT_DOWNLOAD_RATE_LIMIT_PER_MINUTE', 20),
    ],

    'public_api' => [
        'rate_limit_per_minute' => (int) env('PUBLIC_CATALOGUE_RATE_LIMIT_PER_MINUTE', 120),
        'studio_limit' => (int) env('PUBLIC_CATALOGUE_STUDIO_LIMIT', 500),
        'concert_limit_per_studio' => (int) env('PUBLIC_CATALOGUE_CONCERT_LIMIT', 250),
    ],

    'playback' => [
        'signed_url_ttl_minutes' => (int) env('CONCERT_PLAYBACK_SIGNED_URL_TTL_MINUTES', 15),

        'cloudfront' => [
            'domain' => env('CLOUDFRONT_CONCERT_DOMAIN'),
            'key_pair_id' => env('CLOUDFRONT_CONCERT_KEY_PAIR_ID'),
            'private_key' => env('CLOUDFRONT_CONCERT_PRIVATE_KEY'),
            'private_key_path' => storage_path(env(
                'CLOUDFRONT_CONCERT_PRIVATE_KEY_PATH',
                'app/private/keys/dancepro-concerts-private.pem',
            )),
            'cookie_domain' => env('CLOUDFRONT_CONCERT_COOKIE_DOMAIN'),
            'cookie_path' => env('CLOUDFRONT_CONCERT_COOKIE_PATH', '/'),
            'cookie_secure' => (bool) env('CLOUDFRONT_CONCERT_COOKIE_SECURE', true),
            'cookie_same_site' => env('CLOUDFRONT_CONCERT_COOKIE_SAME_SITE', 'lax'),
        ],
    ],
];
