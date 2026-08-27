<?php

return [
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
