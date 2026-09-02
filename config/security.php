<?php

return [
    'two_factor' => [
        'enabled' => (bool) env('TWO_FACTOR_ENABLED', false),
        'enforced' => (bool) env('TWO_FACTOR_ENFORCED', false),
        'issuer' => env('TWO_FACTOR_ISSUER', 'DancePro Crew'),
    ],
];
