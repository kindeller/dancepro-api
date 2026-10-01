<?php

return [
    'terms_url' => env('CUSTOMER_TERMS_URL', 'https://www.dancepro.com.au/faq'),
    'privacy_url' => env('CUSTOMER_PRIVACY_URL', 'https://www.dancepro.com.au/faq'),
    'policy_version' => env('CUSTOMER_POLICY_VERSION', 'faq-2026-09-22'),
    // Set published store URLs when the customer apps launch. Hidden until then.
    'mobile_apps' => ['ios_url' => null, 'android_url' => null],
];
