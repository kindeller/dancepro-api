<?php

$appUrl = rtrim((string) env('APP_URL', ''), '/');

return [
    'healthcheck_url' => env('DEPLOY_HEALTHCHECK_URL', $appUrl.'/up'),
    'vite_hot_file' => public_path('hot'),
];
