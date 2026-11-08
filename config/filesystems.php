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
            'throw' => false,
            'report' => false,
        ],

        // Diseños de los clientes: privados, nunca servidos directamente (fase 4).
        'disenos' => [
            'driver' => 'local',
            'root' => storage_path('app/disenos'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // Archivos de los pedidos (D169): copia de cada diseño tal como se confirmó. Privado,
        // sin URL pública; aparte del disco «disenos» para que su limpieza no los toque.
        'pedidos' => [
            'driver' => 'local',
            'root' => storage_path('app/pedidos'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // Copias de seguridad en el propio servidor (fuera de storage/app).
        'copias' => [
            'driver' => 'local',
            'root' => storage_path('copias'),
            'throw' => true,
            'report' => false,
        ],

        // Copia fuera del servidor: OVH Object Storage (compatible con S3).
        'copias_externas' => [
            'driver' => 's3',
            'key' => env('COPIAS_EXTERNAS_KEY'),
            'secret' => env('COPIAS_EXTERNAS_SECRET'),
            'region' => env('COPIAS_EXTERNAS_REGION', 'gra'),
            'bucket' => env('COPIAS_EXTERNAS_BUCKET'),
            'endpoint' => env('COPIAS_EXTERNAS_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
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
            'throw' => false,
            'report' => false,
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
