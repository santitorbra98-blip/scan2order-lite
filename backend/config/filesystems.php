<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | HERE: set FILESYSTEM_DISK=s3 in production (Render) to use S3-compatible
    | cloud storage and avoid losing files on container restarts.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Image Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for restaurant and product image uploads.
    | In production set IMAGE_FILESYSTEM_DISK=s3 and configure AWS_* env vars.
    | Locally, leave unset to use the 'public' disk (storage/app/public).
    |
    */

    'image_disk' => env('IMAGE_FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'throw'  => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),           // CDN or custom endpoint URL
            'endpoint'                => env('AWS_ENDPOINT'),      // for R2: https://<account>.r2.cloudflarestorage.com
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'visibility'              => 'public',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
