<?php

return [
    'mobile_token_expiration' => (int) env('MOBILE_TOKEN_EXPIRATION', 10080),

    'two_factor' => [
        'enabled' => (bool) env('TWO_FACTOR_ENABLED', false),
        'enforced' => (bool) env('TWO_FACTOR_ENFORCED', false),
        'issuer' => env('TWO_FACTOR_ISSUER', 'DancePro Crew'),
    ],

    'content_security_policy' => [
        'enabled' => (bool) env('CSP_ENABLED', true),
        'report_only' => (bool) env('CSP_REPORT_ONLY', true),
    ],
];
