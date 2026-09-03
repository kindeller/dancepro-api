<?php

return [
    'filesystem_disk' => env('OPERATIONS_FILESYSTEM_DISK', 'local'),
    'external_resource_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OPERATIONS_EXTERNAL_RESOURCE_HOSTS', '')),
    ))),
];
