<?php

return [
    'driver' => env('STUDIO_PAYMENT_DRIVER', in_array(env('APP_ENV'), ['local', 'testing'], true) ? 'fake' : 'disabled'),
    'braintree' => [
        'environment' => env('BRAINTREE_ENVIRONMENT', 'sandbox'),
        'merchantId' => env('BRAINTREE_MERCHANT_ID'),
        'publicKey' => env('BRAINTREE_PUBLIC_KEY'),
        'privateKey' => env('BRAINTREE_PRIVATE_KEY'),
    ],
    'merchant_account_id' => env('BRAINTREE_AUD_MERCHANT_ACCOUNT_ID'),
];
