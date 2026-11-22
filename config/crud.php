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
    |  - 'read_only'   (or 'read_only_roles'):   string[] — roles limited to read-only access
    |  - 'create_only' (or 'create_only_roles'): string[] — roles that can view & create, not update/delete
    |  - 'no_delete'   (or 'no_delete_roles'):   bool|string[] — true disables delete for all roles, or list of roles
    |
    | Example:
    | 'users' => [
    |     'roles' => ['super_admin', 'admin'],
    | ],
    | 'booths' => [
    |     'roles'       => ['super_admin', 'admin', 'manager'],
    |     'create_only' => ['manager'],
    |     'no_delete'   => ['admin'],
    | ],
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
    | Generator Defaults
    |--------------------------------------------------------------------------
    |
    | Default namespaces and paths used by the `php artisan crud:generate` command.
    | Set 'pages_directory' to null to auto-detect `resources/js/pages/Dashboard`
    | vs `resources/js/Pages/Dashboard`.
    |
    */
    'generator' => [
        'controller_namespace' => 'App\\Http\\Controllers\\Dashboard',
        'policy_namespace'     => 'App\\Policies',
        'pages_directory'      => null,
        'model_path'           => 'app/Models',
    ],
];
