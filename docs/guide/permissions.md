# Permissions & Roles

`BaseCrudPolicy` maps a clean config matrix in `config/crud.php` to standard Laravel Gate abilities (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `export`, `import`).

## Creating a Resource Policy

```php
<?php

namespace App\Policies;

use NagibMahfuj\Crud\BaseCrudPolicy;

class ProductPolicy extends BaseCrudPolicy
{
    protected function resourceKey(): string
    {
        return 'products';
    }
}
```

## Permission Matrix (`config/crud.php`)

```php
'resources' => [
    'products' => [
        'roles'       => ['super_admin', 'admin', 'manager', 'viewer'],
        'read_only'   => ['viewer'],   // View & export only (alias: 'read_only_roles')
        'create_only' => ['manager'],  // View, export & create; no edit/delete/import (alias: 'create_only_roles')
        'no_delete'   => ['admin'],    // Cannot delete (bool true disables delete for all; alias: 'no_delete_roles')
    ],
],

'read_only_roles' => ['viewer'],  // Global read-only roles across all resources
'no_delete_roles' => ['manager'], // Global no-delete roles across all resources
```

### Resolution Hierarchy

| Ability | Requirement |
| --- | --- |
| `viewAny`, `view`, `export` | User role is listed in `resources.{key}.roles` |
| `create` | Has access **and** not in `read_only` / `read_only_roles` (resource or global) |
| `update`, `import` | Has create access **and** not in `create_only` / `create_only_roles` |
| `delete`, `restore`, `forceDelete` | Has write access **and** not blocked by resource `no_delete` / `no_delete_roles` or global `no_delete_roles` |

::: tip Boolean Flags at Resource Level
You can pass `'no_delete' => true`, `'read_only' => true`, or `'create_only' => true` on an individual resource to apply that restriction to **all** roles (even `super_admin`) unless overridden in a custom policy method.
:::

---

## Role Resolvers

::: tip Quick Role Setup on New Projects
Run `php artisan crud:install --starter` (or `--roles=column` / `--roles=spatie`) to automatically scaffold either the `users.role` column migration + `$fillable` wiring or Spatie's `HasRoles` trait + `CrudAdminSeeder`.
:::

### 1. Automatic Spatie Detection (`SpatieRoleResolver`)

If `spatie/laravel-permission` is installed, the package automatically uses `$user->hasRole()` and `$user->getRoleNames()` — zero configuration required:

```bash
composer require spatie/laravel-permission
```

### 2. Default Eloquent Column (`EloquentRoleResolver`)

Without Spatie, the package reads `$user->role` (defaulting to `'viewer'` if unset). Add a `role` column to your `users` table if needed:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('viewer');
});
```

### 3. Custom `RoleResolver`

Implement `NagibMahfuj\Crud\Contracts\RoleResolver`:

```php
<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use NagibMahfuj\Crud\Contracts\RoleResolver;

class CustomRoleResolver implements RoleResolver
{
    public function getRoles(Authenticatable $user): array
    {
        return $user->roles->pluck('slug')->toArray();
    }

    public function hasRole(Authenticatable $user, string|array $roles): bool
    {
        return $user->roles->pluck('slug')->intersect((array) $roles)->isNotEmpty();
    }
}
```

Register in `config/crud.php`:

```php
'role_resolver' => \App\Services\CustomRoleResolver::class,
```

