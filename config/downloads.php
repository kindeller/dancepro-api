<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Download Disks
    |--------------------------------------------------------------------------
    |
    | Client-submitted storage keys may only be resolved against disks listed
    | here. Keep this list narrow and add future bounded-context disks
    | deliberately.
    |
    */

    'allowed_disks' => array_filter(array_map(
        'trim',
        explode(',', env('DOWNLOAD_ALLOWED_DISKS', 's3_competitions,s3')),
    )),

    'default_disk' => env('DOWNLOAD_DEFAULT_DISK', 's3_competitions'),

    'signed_url_ttl_minutes' => (int) env('DOWNLOAD_SIGNED_URL_TTL_MINUTES', 5),

    'cloudfront' => [
        'domain' => env('DOWNLOAD_CLOUDFRONT_DOMAIN'),
        'key_pair_id' => env('DOWNLOAD_CLOUDFRONT_KEY_PAIR_ID'),
        'private_key' => env('DOWNLOAD_CLOUDFRONT_PRIVATE_KEY'),
        'private_key_path' => env('DOWNLOAD_CLOUDFRONT_PRIVATE_KEY_PATH'),
    ],
];
