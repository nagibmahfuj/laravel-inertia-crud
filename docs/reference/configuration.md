# Configuration (`config/crud.php`)

Configure global defaults, role/resource permissions, chunked uploads, async options, CSV imports, and generator namespaces in `config/crud.php`.

Publish the configuration file with:

```bash
php artisan vendor:publish --tag=crud-config
```

## Full Annotated Reference

```php
<?php

return [
    'default_separator' => '--',
    'url_prefix'        => '/dashboard',
    'max_per_page'      => 100,

    'breadcrumbs' => [
        'root' => [
            'title' => 'Dashboard',
            'href'  => '/dashboard',
        ],
    ],

    'role_resolver' => null,

    'resources' => [
        // 'products' => [
        //     'roles'       => ['super_admin', 'admin', 'manager', 'viewer'],
        //     'read_only'   => ['viewer'],
        //     'create_only' => ['manager'],
        //     'no_delete'   => ['admin'],
        // ],
    ],

    'read_only_roles' => [],
    'no_delete_roles' => [],

    /*
    | Native chunked file upload endpoint & defaults.
    */
    'chunk_upload' => [
        'enabled'            => true,
        'route'              => '/crud/chunk-upload',
        'route_name'         => 'crud.chunk-upload',
        'middleware'         => ['web', 'auth'],
        'disk'               => 'public',
        'temp_directory'     => 'crud-chunks',
        'chunk_size_mb'      => 5,
        'max_chunk_size_mb'  => 25,
        'max_file_size_mb'   => 512,
        'max_retries'        => 3,
        'ttl_hours'          => 6,
        'allowed_extensions' => [],
    ],

    /*
    | Native signed async relationship options endpoint.
    */
    'options' => [
        'enabled'    => true,
        'route'      => '/crud/options',
        'route_name' => 'crud.options',
        'middleware' => ['web', 'auth'],
        'limit'      => 25,
        'ttl_hours'  => 6,
    ],

    /*
    | Built-in CSV import & background queue defaults.
    */
    'import' => [
        'enabled'            => true,
        'route'              => '/crud/imports',
        'route_name'         => 'crud.import',
        'middleware'         => ['web', 'auth'],
        'mode'               => 'auto',      // 'sync' | 'queued' | 'auto'
        'queue_threshold_kb' => 1024,        // Auto-queue files > 1 MB
        'queue'              => null,
        'connection'         => null,
        'chunk_size'         => 500,
        'disk'               => 'local',
        'temp_directory'     => 'crud-imports',
        'status_ttl_hours'   => 24,
        'max_errors_sample'  => 50,
        'tries'              => 3,
        'timeout'            => 600,
    ],

    'generator' => [
        'controller_namespace' => 'App\\Http\\Controllers\\Dashboard',
        'policy_namespace'     => 'App\\Policies',
        'pages_directory'      => null, // Auto-detects resources/js/pages vs resources/js/Pages
        'model_path'           => 'app/Models',
    ],
];
```
