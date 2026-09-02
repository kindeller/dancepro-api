<?php

return [
    'database' => [
        'enabled' => (bool) env('DATABASE_BACKUP_ENABLED', false),
        'directory' => env('DATABASE_BACKUP_DIRECTORY', 'backups/database'),
        'retention_days' => (int) env('DATABASE_BACKUP_RETENTION_DAYS', 30),
        'dump_binary' => env('DATABASE_BACKUP_DUMP_BINARY', 'mysqldump'),
        'timeout_seconds' => (int) env('DATABASE_BACKUP_TIMEOUT_SECONDS', 900),
    ],
];
