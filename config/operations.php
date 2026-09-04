<?php

return [
    'filesystem_disk' => env('OPERATIONS_FILESYSTEM_DISK', 'local'),
    'malware_scan' => [
        'enabled' => (bool) env('UPLOAD_MALWARE_SCAN_ENABLED', false),
        'host' => env('UPLOAD_MALWARE_SCAN_HOST', 'clamav'),
        'port' => (int) env('UPLOAD_MALWARE_SCAN_PORT', 3310),
        'timeout_seconds' => (float) env('UPLOAD_MALWARE_SCAN_TIMEOUT_SECONDS', 15),
    ],
    'external_resource_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OPERATIONS_EXTERNAL_RESOURCE_HOSTS', '')),
    ))),
];
