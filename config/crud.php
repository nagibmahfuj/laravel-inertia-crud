<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Separator
    |--------------------------------------------------------------------------
    |
    | Used for multi-select filter values in URL query strings.
    | e.g. ?filter_status=active--inactive
    |
    */
    'default_separator' => '--',

    /*
    |--------------------------------------------------------------------------
    | URL & Pagination Defaults
    |--------------------------------------------------------------------------
    |
    | 'url_prefix'   — Fallback URL prefix when a named route is not found.
    | 'max_per_page' — Upper bound for the ?per_page query parameter.
    |
    */
    'url_prefix'   => '/dashboard',
    'max_per_page' => 100,

    /*
    |--------------------------------------------------------------------------
    | Default Breadcrumbs
    |--------------------------------------------------------------------------
    |
    | Root breadcrumb automatically prepended to generated resource breadcrumbs.
    | Set 'root' to null to omit a root breadcrumb.
    |
    */
    'breadcrumbs' => [
        'root' => [
            'title' => 'Dashboard',
            'href'  => '/dashboard',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Resolver
    |--------------------------------------------------------------------------
    |
    | Override the auto-detected role resolver.
    | Set to null to use auto-detection (Spatie if installed, else Eloquent).
    | Or provide a fully qualified class name implementing
    | NagibMahfuj\Crud\Contracts\RoleResolver.
    |
    */
    'role_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions
    |--------------------------------------------------------------------------
    |
    | Each key maps to a route_prefix / resourceKey used by policies.
    | The 'roles' array determines which roles can access the resource.
    |
    | Optional per-resource overrides:
    |  - 'read_only'   (or 'read_only_roles'):   bool|string[] — roles limited to read-only access
    |  - 'create_only' (or 'create_only_roles'): bool|string[] — roles that can view & create, not update/delete
    |  - 'no_delete'   (or 'no_delete_roles'):   bool|string[] — true disables delete for all roles, or list of roles
    |
    */
    'resources' => [
        // 'users' => ['roles' => ['super_admin', 'admin']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Role Behaviour Flags
    |--------------------------------------------------------------------------
    |
    | 'read_only_roles'  — Roles that can only view data across all resources.
    | 'no_delete_roles'  — Roles that cannot delete any record.
    |
    */
    'read_only_roles' => [],
    'no_delete_roles' => [],

    /*
    |--------------------------------------------------------------------------
    | Chunked File Uploads
    |--------------------------------------------------------------------------
    |
    | Native chunked upload endpoint and defaults for `chunked-file` form fields.
    | Individual fields in resourceConfig() can override chunk_size_mb,
    | max_size_mb, max_retries, accept, disk, directory, and column mappings.
    |
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
    |--------------------------------------------------------------------------
    | Async Relationship Options
    |--------------------------------------------------------------------------
    |
    | Native signed endpoint powering async searchable select fields/filters.
    |
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
    |--------------------------------------------------------------------------
    | CSV Import & Background Processing
    |--------------------------------------------------------------------------
    |
    | Defaults for the built-in CSV import engine, auto-queueing threshold,
    | Cache-backed progress polling, and downloadable error CSV reports.
    |
    */
    'import' => [
        'enabled'            => true,
        'route'              => '/crud/imports',
        'route_name'         => 'crud.import',
        'middleware'         => ['web', 'auth'],
        'mode'               => 'auto',      // 'sync' | 'queued' | 'auto'
        'queue_threshold_kb' => 1024,        // Auto-queue files larger than 1 MB
        'queue'              => null,        // null = default queue
        'connection'         => null,        // null = default connection
        'chunk_size'         => 500,         // DB insert/upsert batch size
        'disk'               => 'local',     // Disk where uploaded import CSVs & error reports are stored
        'temp_directory'     => 'crud-imports',
        'status_ttl_hours'   => 24,          // How long import progress stays in Cache
        'max_errors_sample'  => 50,          // Max row errors included inline in JSON status
        'tries'              => 3,
        'timeout'            => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generator Defaults
    |--------------------------------------------------------------------------
    |
    | Default namespaces and paths used by the `php artisan crud:generate` command.
    | Set 'pages_directory' to null to auto-detect `resources/js/pages`
    | vs `resources/js/Pages`.
    |
    */
    'generator' => [
        'controller_namespace' => 'App\\Http\\Controllers\\Dashboard',
        'policy_namespace'     => 'App\\Policies',
        'pages_directory'      => null,
        'model_path'           => 'app/Models',
    ],
];
