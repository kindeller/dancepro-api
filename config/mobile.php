<?php

return [
    'app' => [
        'minimum_supported_version' => env('MOBILE_MINIMUM_SUPPORTED_VERSION', '1.0.0'),
        'latest_version' => env('MOBILE_LATEST_VERSION', '1.0.0'),
        'maintenance' => (bool) env('MOBILE_MAINTENANCE', false),
        'support_url' => env('MOBILE_SUPPORT_URL'),
        'privacy_url' => env('MOBILE_PRIVACY_URL'),
    ],
    'push' => [
        'gateway_url' => env('MOBILE_PUSH_GATEWAY_URL'),
        'gateway_token' => env('MOBILE_PUSH_GATEWAY_TOKEN'),
    ],
];
