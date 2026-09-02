<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => true,
            'report' => true,
            'persistent' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => true,
            'report' => true,
            'persistent' => false,
            'browser_accessible' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
            'persistent' => true,
        ],

        's3_public_uploads' => [
            'driver' => 's3',
            'key' => env('AWS_PUBLIC_UPLOADS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('AWS_PUBLIC_UPLOADS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('AWS_PUBLIC_UPLOADS_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('AWS_PUBLIC_UPLOADS_BUCKET'),
            'url' => env('AWS_PUBLIC_UPLOADS_URL'),
            'endpoint' => env('AWS_PUBLIC_UPLOADS_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('AWS_PUBLIC_UPLOADS_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'throw' => true,
            'report' => true,
            'persistent' => true,
            'browser_accessible' => true,
        ],

        's3_competitions' => [
            'driver' => 's3',
            'key' => env('AWS_COMPETITIONS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('AWS_COMPETITIONS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('AWS_COMPETITIONS_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('AWS_COMPETITIONS_BUCKET', env('AWS_BUCKET')),
            'url' => env('AWS_COMPETITIONS_URL'),
            'endpoint' => env('AWS_COMPETITIONS_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('AWS_COMPETITIONS_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'throw' => true,
            'report' => true,
        ],

        's3_concerts' => [
            'driver' => 's3',
            'key' => env('AWS_CONCERT_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('AWS_CONCERT_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('AWS_CONCERT_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('AWS_CONCERT_BUCKET', env('AWS_BUCKET')),
            'url' => env('AWS_CONCERT_URL', env('AWS_URL')),
            'endpoint' => env('AWS_CONCERT_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('AWS_CONCERT_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'throw' => true,
            'report' => true,
        ],

        's3_concerts_legacy' => [
            'driver' => 's3',
            'key' => env('AWS_CONCERT_LEGACY_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('AWS_CONCERT_LEGACY_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('AWS_CONCERT_LEGACY_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('AWS_CONCERT_LEGACY_BUCKET'),
            'url' => env('AWS_CONCERT_LEGACY_URL'),
            'endpoint' => env('AWS_CONCERT_LEGACY_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('AWS_CONCERT_LEGACY_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
