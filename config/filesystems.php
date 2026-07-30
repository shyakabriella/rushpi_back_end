<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | This disk is used when no disk is explicitly selected.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | The application has separate disks for private files, public files
    | and optional S3-compatible object storage.
    |
    */

    'disks' => [

        /*
         * General private application files.
         *
         * Files stored here are not publicly accessible through a URL.
         */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Seller verification documents.
         *
         * Business certificates, national IDs, tax documents and licences
         * must always be stored on this private disk.
         */
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        /*
         * Public files such as approved product images and public avatars.
         */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * S3 or S3-compatible storage.
         *
         * This can later be used with AWS S3, DigitalOcean Spaces,
         * Cloudflare R2, MinIO or another compatible provider.
         */
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env(
                'AWS_USE_PATH_STYLE_ENDPOINT',
                false
            ),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Only files inside storage/app/public should be linked publicly.
    | Private seller documents are intentionally excluded.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
