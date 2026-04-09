<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Default Separator
	|--------------------------------------------------------------------------
	|
	| Used for multi-select filter values in URL query strings.
	| e.g. ?status=active--inactive
	|
	*/
	'default_separator' => '--',

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
	| Additional flags: 'read_only_roles', 'create_only_roles', 'no_delete'
	|
	| Example:
	| 'users' => [
	|     'roles' => ['super_admin', 'admin'],
	| ],
	| 'booths' => [
	|     'roles' => ['super_admin', 'admin', 'manager'],
	|     'create_only_roles' => ['manager'],
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
	| Generator Defaults
	|--------------------------------------------------------------------------
	|
	| Default namespaces and paths used by the `php artisan crud:generate` command.
	|
	*/
	'generator' => [
		'controller_namespace' => 'App\\Http\\Controllers\\Dashboard',
		'policy_namespace'     => 'App\\Policies',
		'pages_directory'      => 'resources/js/Pages/Dashboard',
		'model_path'           => 'app/Models',
	],
];
