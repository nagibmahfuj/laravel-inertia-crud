# Configuration (`config/crud.php`)

Publish the configuration file with:

```bash
php artisan vendor:publish --tag=crud-config
```

## Full Annotated Reference

```php
<?php

return [
    /*
    | Multi-select filter delimiter in URL query strings
    | e.g. ?filter_status=active--draft
    */
    'default_separator' => '--',

    /*
    | Fallback URL prefix when a named route is not registered,
    | and upper bound for the ?per_page query parameter.
    */
    'url_prefix'   => '/dashboard',
    'max_per_page' => 100,

    /*
    | Default root breadcrumb prepended to generated breadcrumbs.
    | Set 'root' => null to disable a root breadcrumb.
    */
    'breadcrumbs' => [
        'root' => [
            'title' => 'Dashboard',
            'href'  => '/dashboard',
        ],
    ],

    /*
    | Custom RoleResolver FQCN (null = auto-detect Spatie, else Eloquent)
    */
    'role_resolver' => null,

    /*
    | Per-resource permission matrix keyed by route_prefix / resourceKey.
    | Supported override keys:
    |   'roles'       => string[]
    |   'read_only'   => bool|string[]   (alias: 'read_only_roles')
    |   'create_only' => bool|string[]   (alias: 'create_only_roles')
    |   'no_delete'   => bool|string[]   (alias: 'no_delete_roles')
    */
    'resources' => [
        // 'products' => [
        //     'roles'       => ['super_admin', 'admin', 'manager', 'viewer'],
        //     'create_only' => ['manager'],
        //     'no_delete'   => ['admin'],
        // ],
    ],

    /*
    | Global role flags applied across all resources.
    */
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
    | Generator defaults for `php artisan crud:generate`.
    | Set 'pages_directory' => null to auto-detect `resources/js/pages`
    | vs `resources/js/Pages`.
    */
    'generator' => [
        'controller_namespace' => 'App\\Http\\Controllers\\Dashboard',
        'policy_namespace'     => 'App\\Policies',
        'pages_directory'      => null,
        'model_path'           => 'app/Models',
    ],
];
```

